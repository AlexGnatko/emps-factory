<?php

require_once $emps->common_module('ited/ited.class.php');

class EMPS_WebsitesEditor extends EMPS_ImprovedTableEditor {
	public $ref_type = DT_EF_WEBSITE;
	public $ref_sub = 1;

	public $track_props = P_EF_WEBSITE;	

	public $table_name = "ef_websites";
	
	public $credentials = "users";
	
	public $form_name = "db:_factory/websites,form";	
	
	public $order = " order by id asc ";
	
	public $v;	
	
	public $pads = array(
		'info'=>'General Info',
		'cfg'=>'Config',
		'ssl'=>'SSL',
		'install'=>'Installation',
		'heartbeat'=>'Heartbeat',
        'stats'=>'Stats',
        'services'=>'Services'
//		'props'=>'Properties',
		);
		

	public function __construct(){
		parent::__construct();
	}	
		
	public function handle_row($ra){
		global $emps,$ss,$key, $ef, $IDN;
		
		$ra = $ef->explain_website($ra);
		
		$ra['cfg'] = $ef->site_defaults($ra);
        $ra['hostname_decoded'] = $IDN->convert(mb_strtolower($ra['hostname']));

        $dt = time() - 24*60*60;

        if (!$ra['current_ip'] || $ra['current_ip_dt'] < $dt) {
            if (!($ra['current_ip_dt'] > $dt)) {
                $ra['current_ip'] = shell_exec("getent ahostsv4 {$ra['hostname']} | awk '{ print ".`$1`."; exit }'");
                $emps->p->save_properties(['current_ip' => $ra['current_ip']], $ra['context_id'], "current_ip:c");
                $emps->p->save_properties(['current_ip_dt' => time()], $ra['context_id'], "current_ip_dt:i");
            }
        }

		return parent::handle_row($ra);
	}
}

$can_do = false;

$OwnerMode = false;
$ClientMode = false;

if ($emps->auth->credentials("admin")) {
    $can_do = true;
} else {
    $ClientMode = true;
    $id = intval($key);
    $row = $emps->db->get_row("ef_websites", "id = {$id}");
    if ($row) {
        if ($row['user_id'] = $emps->auth->USER_ID) {
            $can_do = true;
            $smarty->assign("OwnerMode", 1);
            $OwnerMode = true;
        }
    }
}

if ($can_do) {
    if($_POST['install_local_php'] && $key){
        $row = $ef->load_website(intval($key));

        $data = array();
        if($_POST['overwrite']){
            $data['overwrite'] = true;
        }else{
            $data['overwrite'] = false;
        }

        $ef->custom_command("init-project", $row['id'], json_encode($data));
        $ef->set_status($row['context_id'], array("init_project"=>"started"));

        $cfg = $ef->site_defaults($row);
        $smarty->assign("cfg", $cfg);
        if ($cfg['emps_version'] == "WordPress") {
            $text = $smarty->fetch("db:_factory/temps,wpconfig");
        } else {
            $text = $smarty->fetch("db:_factory/temps,local_php");
        }

        $text = '<'.'?'."php\r\n".$text;

        $file_name = $ef->temporary_file("local.php-".$row['id'], $text);

        $data = array();
        $data['file_name'] = $file_name;
        $data['htdocs'] = $cfg['path'];
        $data['www_dir'] = $row['www_dir'];
        $data['owner'] = $row['user']['username'];

        $ef->custom_command("install-local-php", $row['id'], json_encode($data));
        $ef->set_status($row['context_id'], array("local_php"=>"started"));
        $emps->redirect_elink();exit();
    }

    if($_POST['post_pem'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $file_name = $ef->temporary_file("pemfile-".$row['id'], $_POST['pemfile']);
            $key_file_name = $ef->temporary_file("keyfile-".$row['id'], $_POST['keyfile']);
            $data = array();
            $data['file_name'] = $file_name;
            $data['key_file_name'] = $key_file_name;
            $data['website_id'] = $row['id'];

            $ef->custom_command("install-pemfile", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("pemfile"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['copy_ssl'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();
            $data['website_id'] = $row['id'];

            $ef->custom_command("copy-pemfile", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("copy_pemfile"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['setup_git'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();

            $data['website_id'] = $row['id'];

            $ef->custom_command("setup-project-git", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("setup_git"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['install_httpd'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();

            $data['website_id'] = $row['id'];

            $ef->custom_command("configure-httpd", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("setup_httpd"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['install_mysql'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();

            $data['website_id'] = $row['id'];

            $ef->custom_command("install-database", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("setup_mysql"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['init_website'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();

            $data['website_id'] = $row['id'];

            $ef->custom_command("init-website", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("init_website"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['certbot'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();

            $data['website_id'] = $row['id'];

            $ef->custom_command("certbot", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("certbot"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['setup_awstats'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();

            $data['website_id'] = $row['id'];

            $ef->custom_command("setup-awstats", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("setup_awstats"=>"started"));

            $emps->redirect_elink();exit();
        }
    }

    if($_POST['move_uploads'] && $key){
        $row = $ef->load_website(intval($key));

        if($row){
            $data = array();

            $data['website_id'] = $row['id'];

            $ef->custom_command("move-uploads", $row['id'], json_encode($data));
            $ef->set_status($row['context_id'], array("move_uploads"=>"started"));

            $emps->redirect_elink();exit();
        }
    }
}

if($_POST['hostname']){
	$_REQUEST['name'] = $_POST['hostname'];
}

$ited = new EMPS_WebsitesEditor;

if(isset($_GET['set_closed'])){
    $_SESSION['websites_closed'] = $_GET['set_closed'];
}

$smarty->assign("status_closed", $_SESSION['websites_closed']);

$ited->where = " where 1=1 ";

if ($ClientMode) {
    $ited->where .= " and user_id = {$emps->auth->USER_ID} ";
}

if($_SESSION['websites_closed']){
    $ited->where .= " and status = 50 ";
}else{
    $ited->where .= " and status <> 50 ";
}


use Algo26\IdnaConvert\ToUnicode;
$IDN = new ToUnicode();

$ited->ref_id = $key;

$ited->add_pad_template("factory/websites/pads,%s");

$ited->handle_request();

