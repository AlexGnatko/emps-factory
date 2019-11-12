<?php

if($emps->auth->credentials("users")){
    if ($emps->auth->credentials("admin")) {
        if($_POST['post_add_command']){
            $ef->add_command($_POST['command']);
            $emps->redirect_elink();exit();
        }
        $smarty->assign("AdminMode", 1);
    } else {
        $ef->user_filter = ['user_id' => $emps->auth->USER_ID];
    }

	$ef->perpage = 50;

	$lst = $ef->list_commands();
	
	$smarty->assign("lst", $lst);
}else{
	$emps->deny_access("AdminNeeded");
}
