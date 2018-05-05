<?php
$emps->no_smarty = true;

if($emps->auth->credentials("admin")){
    require_once $emps->page_file_name('_factory,factory.class', 'controller');
    require_once $emps->page_file_name('_factory,factory_worker.class', 'controller');

    $ef = new EMPS_Factory;
    $efw = new EMPS_FactoryWorker;

    $ef->load_defaults();

    $emps->save_setting("_last_db_stats", 0);
    $efw->db_stats_cycle(true);
}else{
    $emps->deny_access("AdminNeeded");
}
