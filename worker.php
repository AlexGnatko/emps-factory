<?php
// this script is supposed to be run as root

// testing checkout under gnatko

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

unlink(EMPS_SCRIPT_PATH."/restart");

// Is the database connection still usable? A lost connection (MySQL crashed or
// was restarted behind our back) cannot be recovered here: the worker exits and
// systemd starts a fresh one, which connects again.
function ef_db_alive(&$error = ""){
	global $emps;
	try {
		$r = $emps->db->query("select 1");
		if(!$r){
			$error = (string)$emps->db->sql_error();
			return false;
		}
		$emps->db->free($r);
	} catch (\Throwable $e) {
		$error = $e->getMessage();
		return false;
	}
	return true;
}

while(true){
//    echo "Cycle...\r\n";
	if(!ef_db_alive($db_error)){
		echo "MySQL connection lost ({$db_error}), exiting; systemd will start a new worker\r\n";
		exit(1);
	}
	$efw->cycle();
	if(time() > $off_time){
		break;
	}
	if($GLOBALS['die_now'] ?? false){
		break;
	}
	if (file_exists(EMPS_SCRIPT_PATH."/restart")) {
	    break;
    }
	sleep(round(rand(1, 5)));
}
