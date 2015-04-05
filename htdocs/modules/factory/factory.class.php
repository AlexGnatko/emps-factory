<?php

define('DT_EF_WEBSITE', 10010);

define('P_EF_WEBSITE', 'local_cfg:t,status_data:t,prefix:c');

class EMPS_Factory {
	public $defaults;
	
	public $perpage = 25;
	
	public function save_setting($context_id, $code, $value){
		global $emps;
		$x = explode(':', $code);
		$name = $x[0];
		$a = array($name=>$value);
		$emps->p->save_properties($a, $context_id, $code);
	}
	
	public function regex_escape($txt){
		$txt = str_replace(".", "\.", $txt);
		$txt = str_replace("-", "\-", $txt);
		return $txt;
	}
	
	public function set_status($context_id, $arr){
		global $emps;
	
		$ra = $emps->p->read_properties(array(), $context_id);	
		
		if($ra['status_data']){
			$data = json_decode($ra['status_data'], true);
		}else{
			$data = array();
		}
				
		$data = array_merge($data, $arr);
		
		$this->save_setting($context_id, "status_data", json_encode($data));
	}
	
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
		if(!$rv['nginx_conf_path']){
			$rv['nginx_conf_path'] = '/etc/nginx';
		}
		if(!$rv['server_type']){
			$rv['server_type'] = 'nginx';
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

	public function explain_user($ra){
		global $emps;
		
		$ra['context_id'] = $emps->p->get_context(DT_USER, 1, $ra['id']);
		$ra = $emps->p->read_properties($ra, $ra['context_id']);
		
		if($ra['cfg']){
			$ra['cfg'] = json_decode($ra['cfg'], true);
		}
		
		return $ra;
		
	}
	
	public function load_user($id){
		global $emps;
		
		$user = $emps->auth->load_user(intval($id));
		if($user){
			$user = $this->explain_user($user);
			return $user;	
		}else{
			return false;
		}
	}
	
	public function explain_website($ra){
		global $emps;
		
		$ra['context_id'] = $emps->p->get_context(DT_EF_WEBSITE, 1, $ra['id']);
		$ra = $emps->p->read_properties($ra, $ra['context_id']);
		
		if($ra['user_id']){
			$user = $emps->auth->load_user(intval($ra['user_id']));
			if($user){
				$ra['user'] = $this->explain_user($user);
			}
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
		
		if($ra['status_data']){
			$ra['sd'] = json_decode($ra['status_data'], true);		
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
		$ra['user'] = $emps->auth->load_user($ra['user_id']);

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
		if(!$cfg['db']['user']){
			$cfg['db']['user'] = $ra['user']['username'];
		}
		if(!$cfg['db']['password']){
			$cfg['db']['password'] = $ra['user']['cfg']['mysql_password'];
		}
		if(!$cfg['db']['host']){
			$cfg['db']['host'] = 'localhost';
		}
		
		if(!$cfg['include_path']){
			$cfg['include_path'] = $this->defaults['main_path'].'/lib';
		}
		
		if(!$cfg['awstats_url']){
			$cfg['awstats_url'] = "http://awstats.".$this->defaults['hostname_short']."/awstats/awstats.pl?config=".$cfg['hostname'];
		}
		
		$cfg['prefix'] = $ra['prefix'];
		
		if(!$cfg['prefix']){
			$cfg['prefix'] = '00';
		}
		
		if(!$cfg['hostname_regex']){
			$cfg['hostname_regex'] = $this->regex_escape($cfg['hostname']);
		}
		
		return $cfg;
	}
	
	public function user_defaults($ra){
		global $emps;

		if(!$ra['userdir']){
			$ra['userdir'] = $this->defaults['home'].'/'.$ra['username'];
		}
		
		$ra['home'] = $ra['userdir'];
		
		if(!$ra['www_dir']){
			$ra['www_dir'] = $this->defaults['main_path'].'/'.$ra['username'];			
		}
	
		return $ra;
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