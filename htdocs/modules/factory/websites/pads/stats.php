<?php

global $SET, $ef;

$this->handle_view_row();

$row = $this->row;

if($_GET['enable']){
    $hb = $emps->db->get_row("ef_stats", "ef_website_id = ".$row['id']);
    if(!$hb){
        $SET = array();
        $SET['ef_website_id'] = $row['id'];
        $SET['status'] = 10;
        $SET['user_id'] = $emps->auth->USER_ID;
        $emps->db->sql_insert("ef_stats");
    }else{
        $SET = array();
        $SET['status'] = 10;
        $emps->db->sql_update("ef_stats", "id = ".$hb['id']);
    }
    $emps->redirect_elink();exit();
}

if($_GET['disable']){
    $hb = $emps->db->get_row("ef_stats", "ef_website_id = ".$row['id']);
    if($hb){
        $SET = array();
        $SET['status'] = '00';
        $emps->db->sql_update("ef_stats", "id = ".$hb['id']);
    }
    $emps->redirect_elink();exit();
}

$hb = $emps->db->get_row("ef_stats", "ef_website_id = ".$row['id']);

if($hb){
    $hb['ntime'] = $emps->form_time($hb['nedt']);
}

$smarty->assign("stats", $hb);

$smarty->assign("stat_list", $ef->list_stats($hb['id']));
