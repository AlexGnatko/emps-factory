<?php

if ($emps->auth->credentials("admin")) {
    $emps->page_property("vuejs", true);
    $emps->page_property("toastr", true);
    $emps->page_property("css_fw", "bulma");

    require_once $emps->page_file_name('_factory,factory.class', 'controller');
    require_once $emps->page_file_name('_factory,factory_worker.class', 'controller');

    $ef = new EMPS_Factory();
    $efw = new EMPS_FactoryWorker();

    $export_key = $emps->get_setting("export_key");

    if ($_POST['post_initiate']) {
        $payload = $_POST['payload'];
        $hostname = $payload['remote_factory'];
        $website = $payload['remote_hostname'];

        $url = "http://{$hostname}/factory-export/{$website}/{$export_key}/start/";

        $rv = file_get_contents($url);

        $rv = json_decode($rv, true);

        if ($rv['code'] == "OK") {
            $emps->json_ok($rv); exit;
        } else {
            if ($rv['code'] == "Error") {
                if ($rv['message'] == "no_access") {
                    $emps->json_error("Access keys do not match!"); exit;
                }
            }
            $emps->json_error("Unknown error!"); exit;
        }
    }

    if ($_POST['post_continue']) {
        $payload = $_POST['payload'];

        $command_payload = $payload['import_payload'];
        $website_id = $payload['local_website_id'];

        $id = $ef->custom_command("import-website", $website_id, json_encode($command_payload));

        $emps->json_ok(["command" => $id]); exit;
    }

    if ($_GET['load_status']) {
        $hostname = $_GET['factory'];
        $website = $_GET['hostname'];
        $command = intval($_GET['command']);

        $url = "http://{$hostname}/factory-export/{$website}/{$export_key}/status/{$command}/";

        error_log($url);

        $rv = file_get_contents($url);

        $rv = json_decode($rv, true);

        if ($rv['code'] == "OK") {
            $emps->json_ok($rv); exit;
        } else {
            if ($rv['code'] == "Error") {
                if ($rv['message'] == "no_access") {
                    $emps->json_error("Access keys do not match!"); exit;
                }
            }
            $emps->json_error("Unknown error!"); exit;
        }
    }

    if ($_GET['load_local_status']) {
        $id = intval($_GET['command']);

        $cmd = $emps->db->get_row("ef_commands", "id = {$id}");

        $website_id = $cmd['ef_website_id'];
        $data = [];
        $data['website_id'] = $website_id;
        $data['status'] = $cmd['status'];

        $website = $ef->load_website($website_id);

        $cfg = $ef->site_defaults($website);


        $emps->json_ok($data); exit;
    }
} else {
    $emps->deny_access("AdminNeeded");
}