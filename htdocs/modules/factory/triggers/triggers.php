<?php

if ($emps->auth->credentials("admin")) {

    if ($_POST['post_create']) {
        $nr = $_POST;
        $nr['user_id'] = $emps->auth->USER_ID;
        $nr['apikey'] = md5(json_encode($nr).time());
        $emps->db->sql_insert_row("ef_remote_commands", ['SET' => $nr]);
        $emps->redirect_elink(); exit;
    }

    if ($_GET['delete']) {
        $id = intval($_GET['delete']);
        $emps->db->query("delete from ".TP."ef_remote_commands where id = {$id}");
        $emps->redirect_elink(); exit;
    }

    $r = $emps->db->query("select * from ".TP."ef_remote_commands order by id asc");
    $lst = [];
    while ($ra = $emps->db->fetch_named($r)) {
        $lst[] = $ra;
    }
    $smarty->assign("lst", $lst);
} else {
    $emps->deny_access("AdminNeeded");
}