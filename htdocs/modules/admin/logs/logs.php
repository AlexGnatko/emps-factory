<?php
$emps->page_property("adminpage", false);
$emps->page_property("mainapp", 1);

$emps->page_property("vuejs", true);
$emps->page_property("toastr", true);
$emps->page_property("css_fw", "bulma");

require_once $emps->page_file_name('_factory,factory.class', 'controller');
require_once $emps->page_file_name('_factory,factory_worker.class', 'controller');

$ef = new EMPS_Factory();
$efw = new EMPS_FactoryWorker();

if ($emps->auth->credentials("users")) {
    $emps->page_property("simple", 1);

    $website_id = intval($key);

    $website = $ef->load_website($website_id);
    if (!$website) {
        $emps->not_found();
        exit;
    }

    $smarty->assign("website", $website);

    $hostname = $website['hostname'];

    if ($_GET['tick']) {

        $logpath = "/var/log/nginx/{$hostname}.log";
        if ($_GET['path']) {
            if ($_GET['path'] == "error") {
                $logpath = "/var/log/nginx/{$hostname}.error.log";
            }
        }
        $log = shell_exec("tail -n 200 {$logpath}");
        //$log = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x80-\xFF]/', '?', $log);
        $log = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F]/u', '?', $log);
        $json_data = json_encode(['log' => $log], JSON_INVALID_UTF8_IGNORE);
        $data = json_decode($json_data, true);
        $emps->json_ok($data); exit;
    }
} else {
    $emps->deny_access("UserNeeded");
}