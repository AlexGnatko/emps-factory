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

    if ($_GET['checkip']) {
        $ip = $_GET['ip'];
        $ip = $aws->sanitize_ip($ip);
        $command_id = $ef->add_command("iptables -L | grep -E 'DROP.*{$ip}'");
        $rv = $ef->command_result($command_id);
        if (strstr($rv, $ip) !== false) {
            $emps->json_ok(['banned' => true]); exit;
        } else {
            $emps->json_ok(['banned' => false]); exit;
        }
    }

    if ($_GET['banip']) {
        $ip = $_GET['ip'];
        $ip = $aws->sanitize_ip($ip);
        $mode = intval($_GET['mode']);
        if ($mode == 1) {
            $command_id = $ef->add_command("iptables -A INPUT -s {$ip} -j DROP");
        } else {
            $command_id = $ef->add_command("iptables -D INPUT -s {$ip} -j DROP");
        }

        $rv = $ef->command_result($command_id);
        $emps->json(["rv" => $rv]); exit;
    }
} else {
    $emps->deny_access("AdminNeeded");
}