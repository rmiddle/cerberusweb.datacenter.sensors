<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// datacenter_sensor

if(!isset($tables['datacenter_sensor'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS datacenter_sensor (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			tag VARCHAR(255) DEFAULT '' NOT NULL,
			name VARCHAR(255) DEFAULT '' NOT NULL,
			extension_id VARCHAR(255) DEFAULT '' NOT NULL,
			server_id INT UNSIGNED NOT NULL,
			status CHAR(1) DEFAULT 'O' NOT NULL,
			updated INT UNSIGNED DEFAULT 0 NOT NULL,
			fail_count TINYINT DEFAULT 0 NOT NULL,
			is_disabled TINYINT(1) DEFAULT 0 NOT NULL,
			params_json TEXT,
			metric TEXT,
			metric_type VARCHAR(32) DEFAULT '' NOT NULL,
			metric_delta VARCHAR(64) DEFAULT '' NOT NULL,
			output TEXT,
			PRIMARY KEY (id),
			INDEX extension_id (extension_id),
			INDEX status (status),
			INDEX server_id (server_id),
			INDEX updated (updated)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);

	$tables['datacenter_sensor'] = 'datacenter_sensor';
}

// ===========================================================================
// Enable scheduled task and give defaults

if(null != ($cron = DevblocksPlatform::getExtension('cerberusweb.datacenter.sensors.cron', true, true))) {
	$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
	$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '1');
	$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
	$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday 23:55'));
}

return TRUE;
