<?php
global $emps, $ef, $ef_defaults, $smarty;

require_once $emps->page_file_name('_factory,factory.class', 'controller');

$ef = new EMPS_Factory();

$ef_defaults = $ef->load_defaults();

$smarty->assign("ef_defaults", $ef_defaults);

