<?php

$emps->page_property("vuejs", true);
$emps->page_property("toastr", true);

if ($_POST['post_add_service']) {
    $payload = $_POST['payload'];
    $nr = $payload;
    $nr['user_id'] = $emps->auth->USER_ID;
    $nr['website_id'] = $this->ref_id;

    $emps->db->sql_insert_row("ef_services", ['SET' => $nr]);

    $emps->json_ok([]); exit;
}

if ($_POST['post_save_service']) {
    $payload = $_POST['payload'];
    $nr = [];
    $emps->copy_values($nr, $payload, "path,command,name");
    $id = $payload['id'];
    $emps->db->sql_update_row("ef_services", ['SET' => $nr], "id = {$id}");

    $emps->json_ok([]); exit;
}

if ($_GET['load_list']) {
    $r = $emps->db->query("select * from ".TP."ef_services where website_id = {$this->ref_id} order by id asc");
    $lst = [];
    while ($ra = $emps->db->fetch_named($r)) {
        $lst[] = $ra;
    }
    $emps->json_ok(['lst' => $lst]); exit;
}