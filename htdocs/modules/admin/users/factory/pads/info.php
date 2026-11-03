<?php

$this->handle_view_row();

//dump($this->row['authorized_keys_idx']);

if($_POST['post_save'] ?? false){
	$_POST['authorized_keys'] = $_REQUEST['authorized_keys'] = $_POST['authorized_keys_idx'] ?? null;
//	dump($_POST);exit();
	$_POST['cfg'] = $_REQUEST['cfg'] = json_encode($_REQUEST['cfg'] ?? null);
}

$this->handle_post();

?>