<?php

global $ef;

$this->handle_view_row();

if($_GET['enable_https']){
	$ef->set_status($this->row['context_id'], array("ssl_mode"=>true));		
	$emps->redirect_elink();exit();
}

if($_GET['disable_https']){
	$ef->set_status($this->row['context_id'], array("ssl_mode"=>false));		
	$emps->redirect_elink();exit();
}

?>