<?php

if ($emps->auth->credentials("admin")) {
    $emps->page_property("vuejs", 1);
    $emps->page_property("toastr", 1);
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
            $emps->json_error("No report for the selected month / website");
        }
        $emps->json_ok(['index' => $index]); exit;
    }
} else {
    $emps->deny_access("AdminNeeded");
}