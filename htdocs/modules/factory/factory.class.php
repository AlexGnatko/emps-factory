<?php

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
		
		return $ra;
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
}

?>