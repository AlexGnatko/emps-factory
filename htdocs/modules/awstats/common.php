<?php

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
    $emps->json_ok(["rv" => $rv]); exit;
}
