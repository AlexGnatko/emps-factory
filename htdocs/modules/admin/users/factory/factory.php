<?php

require_once $emps->common_module('ited/ited.class.php');

class EMPS_UsersEditor extends EMPS_ImprovedTableEditor {
	public $ref_type = DT_USER;
	public $ref_sub = 1;

	public $track_props = P_USER;	

	public $table_name = "e_users";
	
	public $credentials = "admin";
	
	public $form_name = "db:_admin/users/factory,form";	
	
	public $order = " order by id asc ";
	
	public $v;	
	
	public $pads = array(
		'info'=>'General Info',
		'props'=>'Properties',
		);
		

	public function __construct(){
		parent::__construct();
	}	
		
	public function handle_row($ra){
		global $emps,$ss,$key;
		
		return parent::handle_row($ra);
	}
}

$ited = new EMPS_UsersEditor;

$ited->ref_id = $key;

$ited->add_pad_template("admin/users/factory/pads,%s");

$ited->handle_request();

?>