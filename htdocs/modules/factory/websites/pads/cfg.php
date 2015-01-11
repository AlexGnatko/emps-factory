<?php
global $ef;

$this->handle_view_row();

if($_GET['preview_local_php']){
	$emps->no_smarty = true;
	header("Content-Type: text/plain; charset=utf-8");
	
	$cfg = $ef->site_defaults($this->row);
	$smarty->assign("cfg", $cfg);
	$text = $smarty->fetch("db:_factory/temps,local_php");
	
	echo $text;
}else{

	if($_POST['post_save']){
		$_POST['local_cfg'] = $_REQUEST['local_cfg'] = json_encode($_REQUEST['cfg']);
	}
	
	$this->handle_post();
}

?>