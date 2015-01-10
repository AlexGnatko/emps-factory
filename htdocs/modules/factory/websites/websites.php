<?php

require_once $emps->common_module('ited/ited.class.php');

class EMPS_WebsitesEditor extends EMPS_ImprovedTableEditor {
	public $ref_type = DT_EF_WEBSITE;
	public $ref_sub = 1;

	public $track_props = P_EF_WEBSITE;	

	public $table_name = "ef_websites";
	
	public $credentials = "admin";
	
	public $form_name = "db:_factory/websites,form";	
	
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
		global $emps,$ss,$key, $ef;
		
		$ra = $ef->explain_website($ra);
		
		return parent::handle_row($ra);
	}
}

$ited = new EMPS_WebsitesEditor;

$ited->ref_id = $key;

$ited->add_pad_template("factory/websites/pads,%s");

$ited->handle_request();

?>