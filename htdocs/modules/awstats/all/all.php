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

    if ($ss) {
        $filename = $aws->report_filename();
        if (!$filename) {
            return false;
        }
        $aws->filename = $filename;

        $aws->read_map();

        $lst = $aws->read_awstats_section($ss, 10000000);
        usort($lst, function($a, $b) {
            return $b['cols'][0] - $a['cols'][0];
        });
        $smarty->assign("lst", $lst);
        $smarty->assign("ss", $ss);

    }

    require_once $emps->page_file_name('_awstats,common', 'controller');
} else {
    $emps->deny_access("AdminNeeded");
}