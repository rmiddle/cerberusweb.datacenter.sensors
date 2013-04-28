<?php
abstract class Extension_Sensor extends DevblocksExtension {
	const ID = 'cerberusweb.datacenter.sensor';
	
	static function getAll($as_instances=false) {
		$results = DevblocksPlatform::getExtensions('cerberusweb.datacenter.sensor', $as_instances);
		
		// Sorting
		if($as_instances)
			DevblocksPlatform::sortObjects($results, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($results, 'name');
		
		return $results;
	}
	
	abstract function renderConfig($params=array());
	abstract function run($params, &$fields);
};

class WgmDatacenterSensorsSensorExternal extends Extension_Sensor {
	function renderConfig($params=array()) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/external/config.tpl');
	}
	
	function run($params, &$fields) {
		return TRUE;
	}
};

class WgmDatacenterSensorsSensorHttp extends Extension_Sensor {
	function renderConfig($params=array()) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/http/config.tpl');
	}
	
	function run($params, &$fields) {
		if(!extension_loaded('curl')) {
			$error = "The 'curl' PHP extension is required.";
			$fields[DAO_DatacenterSensor::STATUS] = 'C';
			$fields[DAO_DatacenterSensor::METRIC] = $error;
			$fields[DAO_DatacenterSensor::OUTPUT] = $error;
			return FALSE;
		}
		
		$ch = curl_init();
		$success = false;
		
		@$url = $params['url'];
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_exec($ch);
		
		$info = curl_getinfo($ch);
		$status = $info['http_code'];
		
		if(200 == $status) {
			$success = true;
			$output = $status;
		} else {
			$success = false;
			$output = curl_error($ch);
		}
		
		curl_close($ch);
		
		$fields = array(
			DAO_DatacenterSensor::STATUS => ($success?'O':'C'),
			DAO_DatacenterSensor::METRIC => ($success?1:0),
			DAO_DatacenterSensor::OUTPUT => $output,
		);		
		
		return $success;
	}
};

class WgmDatacenterSensorsSensorPort extends Extension_Sensor {
	function renderConfig($params=array()) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/port/config.tpl');
	}
	
	function run($params, &$fields) {
		if(!extension_loaded('curl')) {
			$error = "The 'curl' PHP extension is required.";
			$fields[DAO_DatacenterSensor::STATUS] = 'C';
			$fields[DAO_DatacenterSensor::METRIC] = $error;
			$fields[DAO_DatacenterSensor::OUTPUT] = $error;
			return FALSE;
		}
		
		$errno = null;
		$errstr = null;
		
		@$host = $params['host'];
		@$port = intval($params['port']);
		
		if(false !== (@$conn = fsockopen($host, $port, $errno, $errstr, 10))) {
			$success = true;
			$output = fgets($conn);
			fclose($conn);
		} else {
			$success = false;
			$output = $errstr;
		}
		
		$fields = array(
			DAO_DatacenterSensor::STATUS => ($success?'O':'C'),
			DAO_DatacenterSensor::METRIC => ($success?1:0),
			DAO_DatacenterSensor::OUTPUT => $output,
		);		
		
		return $success;
	}
};

if (class_exists('CerberusCronPageExtension')):
class Cron_WgmDatacenterSensors extends CerberusCronPageExtension {
	public function run() {
		$logger = DevblocksPlatform::getConsoleLog("Sensors");
		$logger->info("Started");

		// Only non-disabled sensors that need to run, up to a max number, longest since updated, not external
		$sensors = DAO_DatacenterSensor::getWhere(
			sprintf("%s = 0 AND %s != %s",
				DAO_DatacenterSensor::IS_DISABLED,
				DAO_DatacenterSensor::EXTENSION_ID,
				C4_ORMHelper::qstr('cerberusweb.datacenter.sensor.external')
			),
			DAO_DatacenterSensor::UPDATED,
			true,
			100
		);
		
		foreach($sensors as $sensor) {
			$pass = $sensor->run();
			$logger->info($sensor->name . ': ' . ($pass===true ? 'PASS' : 'FAIL'));
		}
		
		$logger->info("Finished");
	}
	
	public function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		//$tpl->display('devblocks:cerberusweb.datacenter.sensors::cron/config.tpl');
	}
	
	public function saveConfigurationAction() {
		//@$example_waitdays = DevblocksPlatform::importGPC($_POST['example_waitdays'], 'integer');
		//$this->setParam('example_waitdays', $example_waitdays);
	}
};
endif;

// Controller
class Page_Sensors extends CerberusPageExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}
	
	function render() {
	}
	
	function savePeekAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		@$tag = DevblocksPlatform::importGPC($_REQUEST['tag'],'string','');
		@$name = DevblocksPlatform::importGPC($_REQUEST['name'],'string','');
		@$extension_id = DevblocksPlatform::importGPC($_REQUEST['extension_id'],'string','');
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'],'integer',0);
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if($do_delete && !empty($id)) { // delete
			// [TODO] ACL
			DAO_DatacenterSensor::delete($id);
			
		} else {
			$tag = strtolower($tag);
			
			// Make sure the tag is unique
			if(!empty($tag)) {
				$result = DAO_DatacenterSensor::getByTag($tag);
				// If we matched the tag and it's not this object
				if(!empty($result) && $result->id != $id)
					$tag = null;
			}
			
			$fields = array(
				DAO_DatacenterSensor::NAME => $name,
				DAO_DatacenterSensor::TAG => (!empty($tag) ? $tag : uniqid()),
				DAO_DatacenterSensor::EXTENSION_ID => $extension_id,
				DAO_DatacenterSensor::PARAMS_JSON => json_encode($params),
			);
			
			if(!empty($id)) { // update
				DAO_DatacenterSensor::update($id, $fields);
				
			} else { // create
				$id = DAO_DatacenterSensor::create($fields);
				
				@$is_watcher = DevblocksPlatform::importGPC($_REQUEST['is_watcher'],'integer',0);
				if($is_watcher)
					CerberusContexts::addWatchers('cerberusweb.contexts.datacenter.sensor', $id, $active_worker->id);
			}
			
			// Custom field saves
			@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', array());
			DAO_CustomFieldValue::handleFormPost('cerberusweb.contexts.datacenter.sensor', $id, $field_ids);
		}
		
		// Reload view?
		if(!empty($view_id)) {
			if(null != ($view = C4_AbstractViewLoader::getView($view_id)))
				$view->render(); 
		}
	}	
};

if(class_exists('Extension_ServerTab', true)):
class WgmDatacenterSensorsServerTab extends Extension_ServerTab {
	function showTab(Model_Server $server) {
		$tpl = DevblocksPlatform::getTemplateService();

		// View
		$view_id = 'server_sensor';
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = 'View_DatacenterSensor';
		
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->id = $view_id;
		$view->name = 'Sensor';
		$tpl->assign('view', $view);
		
		$view->addParamsHidden(array(
			SearchFields_DatacenterSensor::SERVER_ID,
		), true);
		$view->addParamsRequired(array(
			SearchFields_DatacenterSensor::SERVER_ID => new DevblocksSearchCriteria(SearchFields_DatacenterSensor::SERVER_ID, '=', $server->id),
		), true);
		
		C4_AbstractViewLoader::setView($view_id, $view);

		// Template
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::server_tab/index.tpl');
	}
};

class WgmDatacenterSensorsLogServerTab extends Extension_ServerTab {
	function showTab(Model_Server $server) {
		$tpl = DevblocksPlatform::getTemplateService();
                
                // View
		$view_id = 'server_sensor_log';
		
                $sensor_group = DAO_DatacenterSensor::getWhere(sprintf("server_id = %d", $server->id));
                
		$params = array(
			SearchFields_ContextActivityLog::TARGET_CONTEXT => new DevblocksSearchCriteria(SearchFields_ContextActivityLog::TARGET_CONTEXT,'=',"cerberusweb.contexts.datacenter.sensor"),
			SearchFields_ContextActivityLog::TARGET_CONTEXT_ID => new DevblocksSearchCriteria(SearchFields_ContextActivityLog::TARGET_CONTEXT_ID,'in',array_keys($sensor_group)),
		);

		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = 'View_ContextActivityLog';
		$defaults->view_columns = array(
			SearchFields_ContextActivityLog::CREATED
		);
		$defaults->renderLimit = 10;
		$defaults->renderSortBy = SearchFields_ContextActivityLog::CREATED;
		$defaults->renderSortAsc = false;
		
		if(null != ($view = C4_AbstractViewLoader::getView($defaults->id, $defaults))) {
			$view->addColumnsHidden(array(
				SearchFields_ContextActivityLog::ACTOR_CONTEXT_ID,
				SearchFields_ContextActivityLog::TARGET_CONTEXT_ID,
				SearchFields_ContextActivityLog::ID,
			), true);
			
			$view->addParamsHidden(array(
				SearchFields_ContextActivityLog::ACTOR_CONTEXT_ID,
				SearchFields_ContextActivityLog::TARGET_CONTEXT_ID,
				SearchFields_ContextActivityLog::ID,
			), true);
			
			$view->addParamsRequired($params, true);
			
			C4_AbstractViewLoader::setView($view->id, $view);
			
			$tpl->assign('view', $view);
		}
		
		$tpl->display('devblocks:cerberusweb.core::internal/activity_log/tab.tpl');
                
	}
};
endif;
