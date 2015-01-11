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
}elseif($_POST['install_local_php']){
	$row = $this->row;
		
	$data = array();
	$data['overwrite'] = false;
	
	$ef->custom_command("init-project", $row['id'], json_encode($data));
	
	$cfg = $ef->site_defaults($this->row);
	$smarty->assign("cfg", $cfg);
	$text = $smarty->fetch("db:_factory/temps,local_php");
	
	$text = '<'.'?'."php\r\n".$text."\r\n?".'>';
	
	$file_name = $ef->temporary_file("local.php-".$row['id'], $text);
	
	$data = array();
	$data['file_name'] = $file_name;
	$data['htdocs'] = $cfg['path'];
	$data['owner'] = $row['user']['username'];
	
	$ef->custom_command("install-local-php", $row['id'], json_encode($data));
}else{

	if($_POST['post_save']){
		$_POST['local_cfg'] = $_REQUEST['local_cfg'] = json_encode($_REQUEST['cfg']);
	}
	
	$this->handle_post();
}

?>