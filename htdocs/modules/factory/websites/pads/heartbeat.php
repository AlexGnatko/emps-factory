<?php

global $SET, $ef;

$this->handle_view_row();

$row = $this->row;

if($_GET['enable']){
	$hb = $emps->db->get_row("ef_heartbeat", "ef_website_id = ".$row['id']);
	if(!$hb){
		$SET = array();
		$SET['ef_website_id'] = $row['id'];
		$SET['status'] = 10;
		$SET['user_id'] = $emps->auth->USER_ID;
		$emps->db->sql_insert("ef_heartbeat");
	}
	$emps->redirect_elink();exit();
}

$hb = $emps->db->get_row("ef_heartbeat", "ef_website_id = ".$row['id']);

if($hb){
	$hb['ntime'] = $emps->form_time($hb['nedt']);
}

$smarty->assign("hb", $hb);

$cfg = $ef->site_defaults($this->row);
$smarty->assign("cfg", $cfg);

if($_POST['post_save']){
	$cfg['heartbeat_interval'] = $_POST['heartbeat_interval'];
	$_POST['local_cfg'] = $_REQUEST['local_cfg'] = json_encode($cfg);
}

$this->handle_post();

?>