<?php

define('DT_EF_WEBSITE', 10010);

define('P_EF_WEBSITE', 'local_cfg:t,status_data:t,prefix:c,emps_version:c,www_dir_alt:t,color:i');

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
		
		if($ra['www_dir_alt']){
			$ra['www_dir'] = $ra['www_dir_alt'];
		}else{
			if(!$ra['www_dir']){
				$ra['www_dir'] = $ra['www_dir_alt'];
				if(!$ra['www_dir']){
					$prefix = $ra['user']['www_dir'];
					if(!$prefix){
						$prefix = $this->defaults['main_path'];
					}
					$ra['www_dir'] = $prefix.'/'.$ra['user']['username'].'/'.$ra['hostname'];
				}
			}
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
        if(!$cfg['emps_version']){
            $cfg['emps_version'] = $ra['emps_version'];
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
		
		$cfg['awstats_url'] = "http://awstats.".$this->defaults['hostname_short']."/cgi-bin/awstats.pl?config=".$cfg['hostname'];
		
		$cfg['prefix'] = $ra['prefix'];
		
		if(!$cfg['prefix']){
			$cfg['prefix'] = '00';
		}
		
		if(!$cfg['hostname_regex']){
			$cfg['hostname_regex'] = $this->regex_escape($cfg['hostname']);
		}

		$cfg['username'] = $ra['user']['username'];
		
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

	public function list_stats($stats_id){
	    global $emps;

	    $rv = [];
	    $r = $emps->db->query("select * from ".TP."ef_stats_values where stats_id = {$stats_id} order by period desc");
	    while($ra = $emps->db->fetch_named($r)){
	        $period = $ra['period'];
	        if(!isset($rv[$period])){
	            $rv[$period] = [];
            }
            $rv[$period][$ra['code']] = $ra['value'];
        }
        $lst = [];
	    foreach($rv as $n => $v){
	        $year = mb_substr($n, 0, 4);
	        $month = mb_substr($n, 4, 2);
	        $v['period'] = $month.".".$year;
	        $lst[] = $v;
        }

        return $lst;
    }

    public function stats_class_by_visits($visits){
	    $class = $visits / 3000;
        return $class;
    }

    public function analyse_stats($stat){
        $class = $this->stats_class_by_visits($stat['visits']);

	    if($stat['visits'] > 0){
            $stat['pages_by_visits'] = $stat['pages'] / $stat['visits'];
            if($stat['pages_by_visits'] > 0){
                $pv_class = pow($stat['pages_by_visits'] / 3, 1/2);
                $class *= $pv_class;
            }
        }
	    if($stat['pages'] > 0){
            $stat['hits_by_pages'] = $stat['hits'] / $stat['pages'];
            if($stat['hits_by_pages'] > 0) {
                $hp_class = pow($stat['hits_by_pages'] / 5, 1/3);
                $class *= $hp_class;
            }
        }
	    if($stat['hits'] > 0){
            $stat['bw_by_hits'] = ($stat['bw'] * 1024) / $stat['hits'];
            if($stat['bw_by_hits'] > 0) {
                $bh_class = pow($stat['bw_by_hits'] / 50, 1/5);
                $class *= $bh_class;
            }
        }

        $class = round($class, 0);
        if($class < 1){
	        $class = 1;
        }

	    $stat['class'] = $class;

        return $stat;
    }

    public function analyse_db_stats($stat){
	    $class = $stat['count_star'] / 10000;

	    if($stat['count_star'] > 0){
            $stat['sr_cs'] = $stat['sum_rows'] / $stat['count_star'];
        }

        $class = round($class, 0);
        if($class < 1){
            $class = 1;
        }

        $stat['class'] = $class;

        return $stat;
    }
}
