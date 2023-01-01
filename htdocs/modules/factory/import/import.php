<?php

if ($emps->auth->credentials("admin")) {
    $emps->page_property("vuejs", true);
    $emps->page_property("toastr", true);
    $emps->page_property("css_fw", "bulma");

    if ($_POST['post_initiate']) {
        $payload = $_POST['payload'];
        $hostname = $payload['remote_factory'];
        $website = $payload['remote_hostname'];
        $export_key = $emps->get_setting("export_key");
        $url = "http://{$hostname}/factory-export/{$website}/{$export_key}/start/";
        error_log($url);
        $rv = file_get_contents($url);
        error_log($rv);
        $rv = json_decode($rv);
        if ($rv['code'] == "OK") {
            $emps->json_ok([]); exit;
        } else {
            if ($rv['code'] == "Error") {
                if ($rv['message'] == "no_access") {
                    $emps->json_error("Access keys do not match!"); exit;
                }
            }
            $emps->json_error("Unknown error!"); exit;
        }

    }
} else {
    $emps->deny_access("AdminNeeded");
}