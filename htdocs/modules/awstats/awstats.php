<?php
global $ef;

if ($emps->auth->credentials("admin")) {
    $emps->page_property("vuejs", 1);
    $emps->page_property("toastr", 1);
    $emps->page_property("momentjs", 1);
    $emps->page_property("css_fw", "bulma");

    $hostname = $key;
    $smarty->assign("hostname", $hostname);

    require_once $emps->page_file_name('_awstats,awstats.class', 'controller');
    $aws = new EMPS_AWStats();

    require_once $emps->common_module('datetime/dates.class.php');
    $dates = new EMPS_Dates();

    $aws->hostname = $hostname;
    if ($start) {
        $aws->period = $start;
    } else {
        $aws->period = $aws->dt_to_dperiod(time());
    }
    $smarty->assign("period", $aws->period);
    $dt = $aws->dperiod_to_dt($aws->period);
    $pdt = $dates->prev_period("month", $dt);
    $ndt = $dates->next_period("month", $dt);
    $emps->loadvars();
    $start = $aws->dt_to_dperiod($pdt);
    $smarty->assign("prev", $emps->elink());
    $start = $aws->dt_to_dperiod($ndt);
    $smarty->assign("next", $emps->elink());
    $emps->loadvars();

    if ($_GET['load_index']) {
        $index = $aws->index();
        if (!$index) {
            $emps->json_error("No report for the selected month / website"); exit;
        }
        $emps->json_ok(['index' => $index]); exit;
    }

    require_once $emps->page_file_name('_awstats,common', 'controller');
} else {
    $emps->deny_access("AdminNeeded");
}