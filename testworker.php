<?php
$emps_start_time = microtime(true);

set_time_limit(0);
ini_set('display_errors', false);
ini_set('memory_limit', -1);

error_reporting(E_ERROR);

$emps_worker_mode = true;

require_once "htdocs/local/local.php";								// local settings for configuration

require_once "EMPS/4.5/emps_worker_bootstrap.php";			// The bootstrap script for worker daemons

require_once $emps->page_file_name('_factory,factory.class', 'controller');
require_once $emps->page_file_name('_factory,factory_worker.class', 'controller');

$ef = new EMPS_Factory;
$efw = new EMPS_FactoryWorker;

$off_time = time() + 2*60*60;

$ef->load_defaults();

$emps->select_website();

while(true){
    $efw->services_cycle();
    if(time() > $off_time){
        break;
    }
    if($GLOBALS['die_now']){
        break;
    }
    sleep(2);
}
