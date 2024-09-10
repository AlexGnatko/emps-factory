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
        $files = [];

        $files[] = "/var/log/nginx/{$hostname}.log";
        $files[] = "/var/log/nginx/{$hostname}.log.1";
        for ($i = 2; $i <= 14; $i++) {
            $files[] = "/var/log/nginx/{$hostname}.log.{$i}.gz";
        }

        $log = "";
        $cmds = [];
        foreach ($files as $idx => $file) {
            if ($idx > 1) {
                $cmd = "zcat {$file} | grep \"{$text}\"";
            } else {
                $cmd = "cat {$file} | grep \"{$text}\"";
            }
            $cmds[] = $cmd;

            $log .= shell_exec($cmd);
        }

        $ips = [];
        $x = explode("\n", $log);
        foreach ($x as $v) {
            $v = trim($v);
            $x = explode(" ", $v, 2);
            $ip = $x[0];
            if (!$ip) {
                continue;
            }
            $ips[$ip] = $ip;
        }
        $ilst = [];
        foreach ($ips as $ip) {
            $ilst[] = $ip;
        }

        $emps->json_ok(['log' => $log, 'ips' => $ips, 'cmds' => $cmds]); exit;
    }
} else {
    $emps->deny_access("AdminNeeded");
}