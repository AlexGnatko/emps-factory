<?php

if ($emps->auth->credentials("admin")) {
    $emps->page_property("vuejs", 1);
    $emps->page_property("toastr", 1);

    $hostname = $key;
    $smarty->assign("hostname", $hostname);

    require_once $emps->page_file_name('_awstats,awstats.class', 'controller');

    $aws = new EMPS_AWStats();

    $aws->hostname = $hostname;

    if ($_GET['load_index']) {
        $emps->json_ok(['index' => $aws->index()]); exit;
    }
} else {
    $emps->deny_access("AdminNeeded");
}