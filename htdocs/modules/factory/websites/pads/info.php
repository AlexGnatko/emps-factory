<?php

$this->handle_view_row();

$this->handle_post();

if($_GET['close_website']){
    $update = [];
    $update['SET'] = ['status' => 50];
    $emps->db->sql_update_row($this->table_name, $update, "id = ".$this->row['id']);
    $emps->redirect_elink(); exit;
}

if($_GET['activate_website']){
    $update = [];
    $update['SET'] = ['status' => '00'];
    $emps->db->sql_update_row($this->table_name, $update, "id = ".$this->row['id']);
    $emps->redirect_elink(); exit;
}

//trying another checkout