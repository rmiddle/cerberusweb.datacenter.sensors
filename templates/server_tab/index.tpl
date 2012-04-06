<form action="{devblocks_url}{/devblocks_url}" style="margin-bottom:5px;">
{if 1 || $active_worker->hasPriv('xxx')}<button type="button" onclick="genericAjaxPopup('peek','c=datacenter&a=handleTabAction&tab=cerberusweb.datacenter.tab.sensors&action=showPeek&id=0&view_id={$view->id}',null,false,'550');"><span class="cerb-sprite2 sprite-plus-circle-frame"></span> {$translate->_('common.add')|capitalize}</button>{/if}
</form>

{include file="devblocks:cerberusweb.core::internal/views/search_and_view.tpl" view=$view}
