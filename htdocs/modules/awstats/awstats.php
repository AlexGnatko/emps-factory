<?php

if ($emps->auth->credentials("admin")) {
    $emps->page_property("vuejs", 1);
    $emps->page_property("toastr", 1);
    $emps->page_property("momentjs", 1);
    $emps->page_property("css_fw", "bulma");

    $hostname = $key;
    $smarty->assign("hostname", $hostname);

    require_once $emps->page_file_name('_awstats,awstats.class', 'controller');

    $aws = new EMPS_AWStats();

    $aws->hostname = $hostname;
    $aws->period = date("mY", time());

    if ($_GET['load_index']) {
        $index = $aws->index();
        if (!$index) {
            $emps->json_error("No report for the selected month / website"); exit;
        }
        $emps->json_ok(['index' => $index]); exit;
    }

    if ($_GET['load_log']) {
        $text = str_replace("\"", "'", $_GET['text']);
        $filename = "/var/log/nginx/{$hostname}.log";
        $cmd = "cat {$filename} | grep \"{$text}\"";
        $log = shell_exec($cmd);
        $emps->json_ok(['log' => $log, 'cmd' => $cmd]); exit;
    }
} else {
    $emps->deny_access("AdminNeeded");
}