<?php

global $key, $start, $ss, $sd;

$emps->plaintext_response();

$secret = $start;

$real_secret = $emps->get_setting("export_key");

if ($real_secret != $secret) {
    $emps->json_error("no_access");
    exit;
}

require_once $emps->page_file_name('_factory,factory.class', 'controller');
require_once $emps->page_file_name('_factory,factory_worker.class', 'controller');

$ef = new EMPS_Factory();
$efw = new EMPS_FactoryWorker();

$ef->load_defaults();

$efw->stats_cycle();

$hostname = $emps->db->sql_escape($key);

$row = $ef->load_website_by_hostname($hostname);

if ($ss == "start") {
    $id = $ef->custom_command("export-website", $row['id'], "");

    $emps->json_ok(["command" => $id]); exit;
}

if ($ss == "status") {
    $id = intval($sd);
    $cmd = $emps->db->get_row("ef_commands", "id = {$id}");

    $website_id = $cmd['ef_website_id'];
    $data = [];
    $data['website_id'] = $website_id;
    $data['status'] = $cmd['status'];

    $website = $ef->load_website($website_id);

    $cfg = $ef->site_defaults($website);

    $export_path = EMPS_SCRIPT_WEB."/export/{$website_id}";

    if ($cmd['status'] == 10) {
        $data['htdocs'] = $export_path."/website.tar.gz";
        $data['uploads'] = $export_path."/uploads.tar.gz";
        $data['sql'] = $export_path."/{$cfg['db']['database']}.sql.gz";
        $data['cfg'] = $export_path."/cfg.txt";
        $data['git'] = $export_path."/git.tar.gz";
    }

    $emps->json_ok($data); exit;
}