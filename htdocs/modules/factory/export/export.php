<?php

global $key, $start, $ss, $sd;

$emps->plaintext_response();

$secret = $start;

$real_secret = $emps->get_setting("export_key");

if ($real_secret != $secret) {
    echo "No access!";
    exit;
}

//require_once $emps->page_file_name('_factory,factory.class', 'controller');
//require_once $emps->page_file_name('_factory,factory_worker.class', 'controller');

$ef = new EMPS_Factory();
$efw = new EMPS_FactoryWorker();

$ef->load_defaults();

$efw->stats_cycle();

$hostname = $emps->db->sql_escape($key);

$row = $ef->load_website_by_hostname($hostname);

if ($ss == "start") {
    $id = $ef->custom_command("export-website", $row['id'], "");

    echo "command: {$id}\r\n";
}

if ($ss == "status") {
    $id = intval($sd);
    $cmd = $emps->db->get_row("ef_commands", "id = {$id}");
    var_dump($cmd);
}