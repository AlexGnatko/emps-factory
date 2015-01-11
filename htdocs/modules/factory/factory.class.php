<?php

define('DT_EF_WEBSITE', 10010);

define('P_EF_WEBSITE', 'local_cfg:t');

class EMPS_Factory {
	public $defaults;
	
	public $perpage = 25;
	
	public function load_defaults(){
		global $emps;
		
		$data = $emps->get_setting("setup_data");

		$rv = json_decode($data, true);
		$rv['php_version'] = PHP_VERSION;
		$rv['self'] = $_SERVER['HTTP_HOST'];
		$rv['self_ip'] = $_SERVER['SERVER_ADDR'];
		
		if(!$rv['main_path']){
			$rv['main_path'] = '/srv/www';
		}
		if(!$rv['lighttpd_conf_path']){
			$rv['lighttpd_conf_path'] = '/etc/lighttpd';
		}
		if(!$rv['git_path']){
			$rv['git_path'] = '/opt/git';
		}
		if(!$rv['www_group']){
			$rv['www_group'] = 'www-data';
		}
		
		$this->defaults = $rv;
		
		return $rv;
	}
	
	public function explain_website($ra){
		global $emps;
		
		$ra['context_id'] = $emps->p->get_context(DT_EF_WEBSITE, 1, $ra['id']);
		$ra = $emps->p->read_properties($ra, $ra['context_id']);
		
		if($ra['user_id']){
			$ra['user'] = $emps->auth->load_user(intval($ra['user_id']));
		}
		
		if(!$ra['www_dir']){
			$prefix = $ra['user']['www_dir'];
			if(!$prefix){
				$prefix = $this->defaults['main_path'];
			}
			$ra['www_dir'] = $prefix.'/'.$ra['user']['username'].'/'.$ra['hostname'];
		}
		
		if(!$ra['htdocs']){
			$ra['htdocs'] = 'htdocs';
		}
		
		if(!$ra['git_repo']){
			$ra['git_repo'] = $ra['user']['username'].'@'.$this->defaults['hostname'].':'.$this->defaults['git_path'].'/'.$ra['user']['username'].'/'.$ra['hostname'].'.git';
		}
		
		if($ra['local_cfg']){
			$ra['cfg'] = json_decode($ra['local_cfg'], true);
		}
		
		return $ra;
	}
	
	public function load_website($id){
		global $emps;
		
		$ra = $emps->db->get_row("ef_websites", "id = ".$id);
		if($ra){
			$ra = $this->explain_website($ra);
			
			$cfg = $this->site_defaults($ra);
			$ra['cfg'] = $cfg;
			
			return $ra;
		}else{
			return false;
		}
	}
	
	public function explain_command($ra){
		global $emps;
		
		if($ra['sdt']){
			$ra['stime'] = $emps->form_time($ra['sdt']);
		}
		if($ra['edt']){
			$ra['etime'] = $emps->form_time($ra['edt']);
		}
		$ra['ctime'] = $emps->form_time($ra['cdt']);

		return $ra;
	}
	
	public function list_commands(){
		global $emps, $start, $perpage;
		
		$perpage = $this->perpage;
		
		$start = intval($start);
		
		$lst = array();
		$r = $emps->db->query("select * from ".TP."ef_commands order by id desc limit $start, $perpage");
		while($ra = $emps->db->fetch_named($r)){
			$ra = $this->explain_command($ra);
			$lst[] = $ra;
		}
		
		return $lst;
	}
	
	public function add_command($command){
		global $emps, $SET;
		
		$SET = array();
		$SET['command'] = $command;
		$SET['user_id'] = $emps->auth->USER_ID;
		$emps->db->sql_insert("ef_commands");
		
		$id = $emps->db->last_insert();
		
		return $id;
	}
	
	public function custom_command($command, $website_id, $payload){
		global $emps, $SET;
		
		$SET = array();
		$SET['command'] = 'cc '.$command;
		$SET['user_id'] = $emps->auth->USER_ID;
		$SET['ef_website_id'] = $website_id;
		$SET['payload'] = $payload;
		$emps->db->sql_insert("ef_commands");
		
		$id = $emps->db->last_insert();
		
		return $id;
	}
	
	public function site_defaults($ra){
		global $emps;
		
		$cfg = $ra['cfg'];
		
		if(!$cfg['hostname']){
			$cfg['hostname'] = $ra['hostname'];
		}
		if(!$cfg['path']){
			$cfg['path'] = $ra['www_dir'].'/'.$ra['htdocs'];
		}
		
		return $cfg;
	}
	
	public function temporary_file($name_prefix, $content){
		global $emps;
		
		$dir = EMPS_SCRIPT_PATH.'/local/temp';
		
		if(!is_dir($dir)){
			mkdir($dir);
			chmod($dir, 0777);
		}
		
		$md5 = md5(uniqid(time(), true));
		
		$file_name = $name_prefix.'-'.$md5;
		
		$full_path = $dir.'/'.$file_name;
		
		file_put_contents($full_path, $content);
		
		return $full_path;
	}
}

?>