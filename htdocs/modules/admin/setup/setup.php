<?php

if($emps->auth->credentials("admin")){
	if($_POST['post_update']){
		$vals = $_POST;
		unset($vals['post_update']);
		$data = json_encode($vals);
		$emps->save_setting("setup_data", $data);
		$emps->redirect_elink();exit();
	}
	
	$data = $emps->get_setting("setup_data");
	$vals = json_decode($data, true);
	
	$smarty->assign("data", $vals);
}else{
	$emps->deny_access("AdminNeeded");
}

?>