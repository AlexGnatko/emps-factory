<?php

class EMPS_FactoryWorker
{
	public $last_tick = 0;
	
	public function say($s){
		echo $s."\r\n";
	}
	
	public function echo_shell($cmd){
		$this->say("# ".$cmd);
		$rv = shell_exec($cmd);
		return $rv;
	}
	
	public function file_chown($file, $owner){
		global $ef;
		exec("chown ".$owner.":".$ef->defaults['www_group']." ".$file);
	}
	
	public function file_chmod($file, $rights){
		global $ef;
		exec("chmod ".sprintf("%o", $rights)." ".$file);
	}	
	
	public function install_local_php($data, $ra){
		global $emps, $ef;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't init project: you have to select a website!");
			return false;
		}
				
		$website = $ef->load_website($website_id);
		
		$this->say("Installing local.php...");
		
		$file_path = $data['file_name'];
		$htdocs = $data['htdocs'];
		$owner = $data['owner'];

		$target_dir = $htdocs.'/local';		
		$target_path = $target_dir.'/local.php';

		if(!is_dir($target_dir)){
			$this->say("Creating ".$target_dir."...");
			mkdir($target_dir, 0777, true);
			$this->file_chown($target_dir, $owner);
			$this->file_chmod($target_dir, 0777);
		}
		$this->move_file($file_path, $target_path, 0755, $owner);
		
		$this->say("Done!");
		
		$ef->set_status($website['context_id'], array("local_php"=>"done"));		
		return true;
	}
	
	public function move_file($file_path, $target_path, $rights, $owner){
		$this->say("Moving ".$file_path." to ".$target_path."...");
		rename($file_path, $target_path);
		$this->say("Setting owner ".$owner.", chmod ".sprintf("0%o", $rights)."...");
		$this->file_chown($target_path, $owner);
		$this->file_chmod($target_path, $rights);
	}
	
	public function create_dir($target_dir, $rights, $owner){
		$this->say("Creating directory ".$target_dir.", chmod ".sprintf("0%o", $rights)."...");
		mkdir($target_dir, $rights, true);
		$this->say("Setting owner ".$owner);
		$this->file_chown($target_dir, $owner);
		$this->file_chmod($target_dir, $rights);
	}
	
	public function put_file($file_name, $rights, $owner, $data){
		$this->say("Creating file ".$file_name."...");
		file_put_contents($file_name, $data);
		$this->say("Setting owner ".$owner.", chmod ".sprintf("0%o", $rights)."...");
		$this->file_chown($file_name, $owner);
		$this->file_chmod($file_name, $rights);
	}
	
	public function copy_file($source_name, $file_name, $rights, $owner){
		$this->say("Copying ".$source_name." to ".$file_name."...");
		copy($source_name, $file_name);
		$this->say("Setting owner ".$owner.", chmod ".sprintf("0%o", $rights)."...");
		$this->file_chown($file_name, $owner);
		$this->file_chmod($file_name, $rights);
	}
	
	public function install_ssh_keys($data, $ra){
		global $emps, $ef, $smarty;
		
		$user_id = intval($data['user_id']);
		if(!$user_id){
			$this->say("Can't ensure user: you have to specify a user!");
			return false;
		}
		
		$user = $ef->load_user(intval($user_id));
			
		if(!$user){
			$this->say("ERROR: No such user!");
			return false;
		}
		
		$username = $user['username'];
		
		$owner = $username;
		
		$this->say("User: ".$username);
		
		$keys = $user['authorized_keys_idx'];
		
		$text = "";
		foreach($keys as $v){
			$v = trim($v);
			if($v){
				$text .= $v."\r\n";
			}
		}
//		$text .= "\r\n";
		
		$def = $ef->user_defaults($user);
		
		$home = $def['home'];
		
		$fail = false;
		
		if(!$home){
			$this->say("No home dir!");
			$fail = true;
		}else{
			$ssh_dir = $home.'/.ssh';
			if(!is_dir($ssh_dir)){
				$this->create_dir($ssh_dir, 0700, $owner);
			}
			$file_name = $ssh_dir.'/authorized_keys';
			file_put_contents($file_name, $text);
			exec("chown ".$owner." ".$file_name);
			$this->file_chmod($file_name, 0700);
		}
	
		if(!$fail){
			$this->say("Done!");
		}	
	}

	public function ensure_mysql_user($data, $ra){
		global $emps, $ef, $smarty;
		
		$user_id = intval($data['user_id']);
		if(!$user_id){
			$this->say("Can't ensure user: you have to specify a user!");
			return false;
		}
		
		$user = $ef->load_user(intval($user_id));
			
		if(!$user){
			$this->say("ERROR: No such user!");
			return false;
		}
		
		$username = $user['username'];
		$this->say("User: ".$username);
		$password = $user['cfg']['mysql_password'];
		
		$fail = false;
		
		$r = $emps->db->query("select exists(select 1 from mysql.user where user = '".$username."')");
		$ra = $emps->db->fetch_row($r);
		if($ra){
			if($ra[0] == 1){
				// user exists
				$this->say("User already exists!");
			}else{
				// user does not exist
				$emps->db->query("create user '".$username."'@'%' identified by '".$password."';");
				$q = "grant all privileges on `".$username."\_%`.* to '".$username."'@'%'";
//				$this->say($q);
				$emps->db->query($q);
//				$this->say($emps->db->sql_error());
				$this->say("User created! Privileges granted!");
			}
		}else{
			$this->say("ERROR: Wrong answer from MySQL!");
			$fail = true;
		}
		
		if(!$fail){
			$this->say("Done!");
		}
	}
	
	public function ensure_linux_user($data, $ra){
		global $emps, $ef, $smarty;
		
		$user_id = intval($data['user_id']);
		if(!$user_id){
			$this->say("Can't ensure user: you have to specify a user!");
			return false;
		}
		
		$user = $ef->load_user(intval($user_id));
			
		if(!$user){
			$this->say("ERROR: No such user!");
			return false;
		}
		
		$username = $user['username'];
		$this->say("User: ".$username);
		$password = $user['cfg']['linux_password'];
		
		$def = $ef->user_defaults($user);
		
		$fail = false;
		$rc = shell_exec("grep -c '^".$username.":' /etc/passwd");
		if(intval($rc) == 1){
			// user exists
			$this->say("Setting new password...");
			exec("echo ".$username.":".$password." | chpasswd");
		}else{
			// user does not exist
			$this->echo_shell("useradd -b ".$ef->defaults['home']." -f -1 -G ".$ef->defaults['www_group']." -m -U ".$username);
			sleep(1);
			
			$rc = shell_exec("grep -c '^".$username.":' /etc/passwd");
			if(intval($rc) == 1){
				$this->say("Setting new password...");
				exec("echo ".$username.":".$password." | chpasswd");
			}else{
				$this->say("Failure!");
				$fail = true;
			}
		}
		
		if(!$fail){
			$this->say("Done!");
		}
	}
	
	public function regex_escape($txt){
		$txt = str_replace(".", "\.", $txt);
		$txt = str_replace("-", "\-", $txt);
		return $txt;
	}
	
	public function install_database($data, $ra){
		global $emps, $ef, $smarty;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't install database: you have to select a website!");
			return false;
		}
		
		$website = $ef->load_website($website_id);
		
		$cfg = $ef->site_defaults($website);
		
		$database_name = $cfg['db']['database'];
		
		$failed = false;
		
		$this->say("Trying to create if not exists: ".$database_name);
		$r = $emps->db->query("create database if not exists `".$database_name."` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
		
		$r = $emps->db->query("show databases like '".$database_name."'");
		$ra = $emps->db->fetch_named($r);
		if(!$ra){
			$this->say("ERROR: Could not create the database!");
			$failed = true;
		}
		
		if(!$failed){
			$ef->set_status($website['context_id'], array("setup_mysql"=>"done"));
			$this->say("Done!");
		}else{
			$ef->set_status($website['context_id'], array("setup_mysql"=>"failed"));
		}
	}
	
	public function init_website($data, $ra){
		global $emps, $ef, $smarty;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't init website: you have to select a website!");
			return false;
		}
		
		$website = $ef->load_website($website_id);
		
		$cfg = $ef->site_defaults($website);
		
		$user = $ef->load_user(intval($website['user_id']));

		$failed = false;
					
		if(!$user){
			$this->say("ERROR: No such user!");
			$failed = true;
		}else{
		
			$username = $user['username'];
			$this->say("User: ".$username);
			$password = $user['cfg']['linux_password'];
			
			$hostname = $cfg['hostname'];
			$x = explode(".", $hostname);
			array_pop($x);
			$hostname_short = implode(".", $x).'.'.$ef->defaults['hostname_short'];
			
			$this->say("Using hostname: ".$hostname_short);
			
			$data = file_get_contents("http://".$hostname_short."/sqlsync/");
			$data = file_get_contents("http://".$hostname_short."/ensure_root/".$password);
			$data = file_get_contents("http://".$hostname_short."/init_settings/");
			
			if(!$failed){
				$ef->set_status($website['context_id'], array("init_website"=>"done"));
				$this->say("Done!");
			}else{
				$ef->set_status($website['context_id'], array("init_website"=>"failed"));
			}
		}
	}
	
	public function setup_awstats($data, $ra){
		global $emps, $ef, $smarty;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't setup AWStats: you have to select a website!");
			return false;
		}
		
		$wwwdata = $ef->defaults['www_group'];
		
		$website = $ef->load_website($website_id);
		
		$cfg = $ef->site_defaults($website);
		
		$server_type = $ef->defaults['server_type'];
		
		$user = $ef->load_user(intval($website['user_id']));

		$failed = false;
					
		if(!$user){
			$this->say("ERROR: No such user!");
			$failed = true;
		}else{
		
			$username = $user['username'];
			$this->say("User: ".$username);
			
			$hostname = $cfg['hostname'];
			
			$smarty->assign("hostname", $hostname);
			$smarty->assign("server_type", $server_type);
			
			$text = $smarty->fetch("db:_factory/temps,awstats");
			
			$config_file = "/etc/awstats/awstats.".$hostname.".conf";
			
			$this->put_file($config_file, 0644, $wwwdata, $text);
			
			$this->echo_shell("/usr/lib/cgi-bin/awstats.pl -config=".$hostname." -update");
			
		}
        if(!$failed){
            $ef->set_status($website['context_id'], array("setup_awstats"=>"done"));
            $this->say("Done!");
        }else{
            $ef->set_status($website['context_id'], array("setup_awstats"=>"failed"));
        }
	}

    public function move_uploads($data, $ra){
        global $emps, $ef, $smarty;

        $website_id = $ra['ef_website_id'];
        if(!$website_id){
            $this->say("Can't move uploads: you have to select a website!");
            return false;
        }

        $wwwdata = $ef->defaults['www_group'];

        $website = $ef->load_website($website_id);

        $cfg = $ef->site_defaults($website);

        $htdocs = $cfg['path'];

        $user = $ef->load_user(intval($website['user_id']));
        $owner = $user['username'];

        $failed = false;

        if(!$user){
            $this->say("ERROR: No such user!");
            $failed = true;
        }else{

            $username = $user['username'];
            $this->say("User: ".$username);

            $hostname = $cfg['hostname'];
            $uploads_dir = "/srv/upload/".$owner."/".$hostname;

            $this->create_dir($uploads_dir, 0777, $owner);

            $source_path = $htdocs."/local/upload";
            $target_path = $uploads_dir;
            $this->say("Renaming: ".$source_path." to ".$target_path);
            rename($source_path, $target_path);
            $this->echo_shell("chmod -R 0777 /srv/upload");

        }
        if(!$failed){
            $ef->set_status($website['context_id'], array("move_uploads"=>"done"));
            $this->say("Done!");
        }else{
            $ef->set_status($website['context_id'], array("move_uploads"=>"failed"));

        }

    }

	public function hostname_part($hostname){
		$x = explode(".", $hostname);
		array_pop($x);
		$hostname_part = implode(".", $x);
		return $hostname_part;
	}
	
	public function configure_httpd($data, $ra){
		global $emps, $ef, $smarty;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't configure httpd: you have to select a website!");
			return false;
		}
		
		$website = $ef->load_website($website_id);
		$cfg = $website['cfg'];
		$owner = $website['user']['username'];
		$wwwdata = $ef->defaults['www_group'];
		
		$server_type = $ef->defaults['server_type'];
		
		$htdocs = $cfg['path'];
		
		$www_dir = $website['www_dir'];
		
		$failed = false;
		
		$hostname = $cfg['hostname'];
		
		$hostname_part = $this->hostname_part($hostname);

		$smarty->assign("hostname", $hostname);
		$host_regex = $cfg['hostname_regex'];
		if(!$host_regex){
			$host_regex = $this->regex_escape($hostname);
		}
		$smarty->assign("hostname_escaped", $host_regex);
		$smarty->assign("upper_host", $this->regex_escape($ef->defaults['hostname_short']));
		$smarty->assign("hostname_part", $this->regex_escape($hostname_part));
		$smarty->assign("htdocs", $www_dir.'/htdocs');
		
		$smarty->assign("ssl", $website['sd']['ssl_mode']);
		$this->say("SSL mode: ".$website['sd']['ssl_mode']);
		
		$prefix = $cfg['prefix'];
		
		if($server_type == "lighttpd"){
			$config_file = $ef->defaults['lighttpd_conf_path'].'/vhosts.d/'.$prefix.'-'.$hostname.'.conf';
			
			$text = $smarty->fetch("db:_factory/temps,lighttpd");
			$this->put_file($config_file, 0644, $wwwdata, $text);
	
			if(!$failed){
				$ef->set_status($website['context_id'], array("setup_httpd"=>"done"));
				$this->say("Done!");
				$ef->add_command("service lighttpd reload");
			}else{
				$ef->set_status($website['context_id'], array("setup_httpd"=>"failed"));
			}
		}elseif($server_type == "nginx"){
			$config_file = $ef->defaults['nginx_conf_path'].'/sites-enabled/'.$prefix.'-'.$hostname.'.conf';
			
			$text = $smarty->fetch("db:_factory/temps,nginx");
			$this->put_file($config_file, 0644, $wwwdata, $text);
	
			if(!$failed){
				$ef->set_status($website['context_id'], array("setup_httpd"=>"done"));
				$this->say("Done!");
				$ef->add_command("service nginx reload");
			}else{
				$ef->set_status($website['context_id'], array("setup_httpd"=>"failed"));
			}
			
			if($website['sd']['ssl_mode']){
				// copy default pem files
				$conf_path = $ef->defaults['nginx_conf_path'];
				$certs_path = $conf_path.'/ssl';
				
				$key_file_name = $certs_path."/nginx.key";
				$file_name = $certs_path."/nginx.crt";
				
				$pem_path = $certs_path.'/'.$hostname.'.pem';
				$key_path = $certs_path.'/'.$hostname.'.key';
				$comb_path = $certs_path.'/'.$hostname.'.comb';
				
				if(!file_exists($pem_path) && !file_exists($key_path)){
					$comb_name = $ef->temporary_file("comb-".$website['id'], file_get_contents($key_file_name)."\n".file_get_contents($file_name));
	
					$this->copy_file($file_name, $pem_path, 0600, $wwwdata);
					$this->copy_file($key_file_name, $key_path, 0600, $wwwdata);
					$this->move_file($comb_name, $comb_path, 0600, $wwwdata);
				}
			}
		}else{
			$this->say("Unknown server type!");
			$ef->set_status($website['context_id'], array("setup_httpd"=>"failed"));
		}
	}
	
	public function install_pemfile($data, $ra){
		global $emps, $ef, $smarty;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't install pemfile: you have to select a website!");
			return false;
		}
		
		$website = $ef->load_website($website_id);
		$cfg = $website['cfg'];
		$owner = $website['user']['username'];
		$wwwdata = $ef->defaults['www_group'];
		
		$failed = false;
		
		$file_name = $data['file_name'];
		$key_file_name = $data['key_file_name'];

		$hostname = $cfg['hostname'];				
		
		$server_type = $ef->defaults['server_type'];
		if($server_type == "lighttpd"){
			$conf_path = $ef->defaults['lighttpd_conf_path'];
			$certs_path = $conf_path.'/certs';
			if(!is_dir($certs_path)){
				$this->create_dir($certs_path, 0600, $wwwdata);
			}
			$pem_path = $certs_path.'/'.$hostname.'.pem';
			$results = shell_exec("openssl verify -CAfile ".$file_name." ".$file_name);
			$x = explode(": ", $results, 2);
			if(trim($x[1]) == "OK"){
				$this->say("openssl verify - OK");
				$this->move_file($file_name, $pem_path, 0600, $wwwdata);
			}else{
				$failed = true;
				$this->say("ERROR: openssl verify failed for this pemfile!");
			}
		}
		if($server_type == "nginx"){
			$conf_path = $ef->defaults['nginx_conf_path'];
			$certs_path = $conf_path.'/ssl';
			if(!is_dir($certs_path)){
				$this->create_dir($certs_path, 0600, $wwwdata);
			}
			$pem_path = $certs_path.'/'.$hostname.'.pem';
			$key_path = $certs_path.'/'.$hostname.'.key';
			$comb_path = $certs_path.'/'.$hostname.'.comb';
			
			$comb_name = $ef->temporary_file("comb-".$website['id'], file_get_contents($key_file_name)."\n".file_get_contents($file_name));
			
			$results = shell_exec("openssl verify -CAfile ".$comb_name." ".$comb_name);
			$x = explode(": ", $results, 2);
			if(trim($x[1]) == "OK"){
				$this->say("openssl verify - OK");
				$this->move_file($file_name, $pem_path, 0600, $wwwdata);
				$this->move_file($key_file_name, $key_path, 0600, $wwwdata);
				$this->move_file($comb_name, $comb_path, 0600, $wwwdata);
			}else{
				$failed = true;
				$this->say("ERROR: openssl verify failed for this pemfile!");
			}
		}

		$ef->set_status($website['context_id'], array("pemfile_time"=>$emps->form_time(time())));	
		if(!$failed){
			$ef->set_status($website['context_id'], array("pemfile"=>"done"));
			$this->say("Done!");
		}else{
			$ef->set_status($website['context_id'], array("pemfile"=>"failed"));
			$ef->set_status($website['context_id'], array("ssl_mode"=>false));
		}
	}

	public function setup_project_git($data, $ra){
		global $emps, $ef, $smarty;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't init project: you have to select a website!");
			return false;
		}
		
		$website = $ef->load_website($website_id);
		$cfg = $website['cfg'];
		$owner = $website['user']['username'];
		
		$htdocs = $cfg['path'];
		
		$www_dir = $website['www_dir'];
		
		$this->say("WWW Dir: ".$www_dir);
		
		$hostname = $cfg['hostname'];
		
		$git_user_path = $ef->defaults['git_path'].'/'.$owner;
		$git_repo_path = $ef->defaults['git_path'].'/'.$owner.'/'.$hostname.'.git';

		if(!is_dir($git_repo_path)){
			$this->create_dir($git_repo_path, 0775, $owner);
			$this->echo_shell("chown ".$owner.":git ".$git_repo_path);
			$this->echo_shell("chmod 0755 ".$git_repo_path);
			$this->echo_shell("chown ".$owner.":git ".$git_user_path);
			$this->echo_shell("chmod 0755 ".$git_user_path);
		}
		
		$fail = false;
		$config_file = $git_repo_path.'/config';
		if(file_exists($config_file)){
			$this->say("Git Repository exists. Nothing to do!");
			$fail = true;
		}else{
			$this->echo_shell("cd ".$git_repo_path." && git --bare init");
			if(file_exists($config_file)){
				$smarty->assign("worktree", $www_dir);
				$config = $smarty->fetch("db:_factory/temps,git_config");
				$this->put_file($config_file, 0744, $owner, $config);
				$smarty->assign("username", $owner);
				$receive = $smarty->fetch("db:_factory/temps,git_receive");
				$this->put_file($git_repo_path.'/hooks/post-receive', 0755, $owner, $receive);
				
				$this->echo_shell("cd ".$git_repo_path." && ".
					"git config user.email \"gnatko@mail.ru\" && ".
					"git config user.name \"Alex\" && ".
					"git add .gitignore && git add htdocs ".
					"&& git commit -m \"EMPS Factory Init\"");
				
				$this->echo_shell("chown -R ".$owner.":git ".$git_repo_path);
				$this->echo_shell("chmod 0755 ".$www_dir);
				
			}else{
				$this->say("ERROR: Could not create the repository");
				$fail = true;
			}
		}
		
		if(!$fail){
			$ef->set_status($website['context_id'], array("setup_git"=>"done"));
			$this->say("Done!");
		}else{
			$ef->set_status($website['context_id'], array("setup_git"=>"failed"));
		}
	}
	
	public function init_project($data, $ra){
		global $emps, $ef, $smarty;
		
		$website_id = $ra['ef_website_id'];
		if(!$website_id){
			$this->say("Can't init project: you have to select a website!");
			return false;
		}
		
		$overwrite = ($data['overwrite'])?true:false;
		if(!$overwrite){
			$this->say("(Without overwritting)");
		}else{
			$this->say("(Overwritting)");
		}
		
		$website = $ef->load_website($website_id);
		$cfg = $website['cfg'];
		$owner = $website['user']['username'];
		
		$parent_website = $ef->load_website($website['parent_website_id']);
		
		$htdocs = $cfg['path'];
		
		$www_dir = $website['www_dir'];
		
		$this->say("WWW Dir: ".$www_dir);

		if(!is_dir($www_dir)){
			$this->create_dir($www_dir, 0755, $owner);
		}
				
		if(!is_dir($htdocs)){
			$this->create_dir($htdocs, 0755, $owner);
		}
		
		$target_dir = $htdocs.'/local';
		if(!is_dir($target_dir)){
			$this->create_dir($target_dir, 0755, $owner);
		}
		$target_dir = $htdocs.'/local/upload';
		if(!is_dir($target_dir)){
			$this->create_dir($target_dir, 0777, $owner);
		}
		$target_dir = $htdocs.'/local/temp_c';
		if(!is_dir($target_dir)){
			$this->create_dir($target_dir, 0777, $owner);
		}
		
		$htaccess = $smarty->fetch("db:_factory/temps,htaccess");
		$file_name = $htdocs.'/.htaccess';
		
		if(!file_exists($file_name) || $overwrite){
			$this->put_file($file_name, 0755, $owner, $htaccess);
		}
		
		if($parent_website){
			$smarty->assign("slave", 1);
		}
		
		$gitignore = $smarty->fetch("db:_factory/temps,gitignore");
		$file_name = $www_dir.'/.gitignore';
		
		if(!file_exists($file_name) || $overwrite){
			$this->put_file($file_name, 0755, $owner, $gitignore);
		}
		
		$smarty->assign("dir", $www_dir);
		$post_checkout = $smarty->fetch("db:_factory/temps,post_checkout");
		$file_name = $www_dir.'/post-checkout.sh';
		
		if(!file_exists($file_name) || $overwrite){
			$this->put_file($file_name, 0755, $owner, $post_checkout);
		}
		
		$file_name = $htdocs.'/index.php';
		
		if($parent_website){
			$smarty->assign("hostname", $website['hostname']);
			$smarty->assign("htdocs", $parent_website['www_dir'].'/htdocs');
			$index_php = $smarty->fetch("db:_factory/temps,slave_index");
			
			if(!file_exists($file_name) || $overwrite){
				$this->put_file($file_name, 0755, $owner, $index_php);
			}
		}else{
			if(!file_exists($file_name) || $overwrite){
				if(EMPS_COMMON_PATH_PREFIX){
					$fn = EMPS_COMMON_PATH_PREFIX.'/sample_index.php';	
				}else{
					$fn = EMPS_PATH_PREFIX.'/sample_index.php';	
				}
				$source_name = stream_resolve_include_path($fn);
		
				$this->copy_file($source_name, $file_name, 0755, $owner);
			}
		}

		$ef->set_status($website['context_id'], array("init_project"=>"done"));
		$this->say("All done!");
	}
	
	public function execute_custom_command($cmd, $ra){
		global $emps;
		
		$payload = $ra['payload'];
		$data = json_decode($payload, true);
		
		ob_start();
		
		$this->say("Custom command: ".$cmd);
		
		switch($cmd){
		case 'install-local-php':
			$this->install_local_php($data, $ra);
			break;
		case 'init-project':
			$this->init_project($data, $ra);
			break;
		case 'ensure-linux-user':
			$this->ensure_linux_user($data, $ra);
			break;
		case 'ensure-mysql-user':
			$this->ensure_mysql_user($data, $ra);
			break;
		case 'install-ssh-keys':
			$this->install_ssh_keys($data, $ra);
			break;
		case 'setup-project-git':
			$this->setup_project_git($data, $ra);
			break;
		case 'install-pemfile':
			$this->install_pemfile($data, $ra);
			break;
		case 'configure-httpd':
			$this->configure_httpd($data, $ra);
			break;
		case 'install-database':
			$this->install_database($data, $ra);
			break;
		case 'init-website':
			$this->init_website($data, $ra);
			break;
		case 'setup-awstats':
			$this->setup_awstats($data, $ra);
			break;
        case 'move-uploads':
            $this->move_uploads($data, $ra);
            break;
		case 'restart':
			$GLOBALS['die_now'] = true;
			$this->say("OK, going to restart...");
			break;
		}
		
		$rv = ob_get_clean();
		return $rv;
	}
	
	public function execute_command($ra){
		global $emps;
		
		$cmd = $ra['command'];

//		$this->say($cmd);		

		$emps->db->query("update ".TP."ef_commands set sdt = ".time().", status = 5 where id = ".$ra['id']);
		if(mb_substr($cmd, 0, 3) == 'cc '){
			$cmd = mb_substr($cmd, 3);
			$rv = $this->execute_custom_command($cmd, $ra);
		}else{
			$rv = shell_exec($ra['command']);
		}
		$emps->db->query("update ".TP."ef_commands set edt = ".time().", status = 10, response = '".$emps->db->sql_escape($rv)."' where id = ".$ra['id']);
		unset($rv);
	}
	
	public function heartbeat_cycle(){
		global $ef, $emps;
		
		$dt = time();
		$r = $emps->db->query("select * from ".TP."ef_heartbeat where status = 10 and nedt <= $dt");
		while($ra = $emps->db->fetch_named($r)){
			$website = $ef->load_website($ra['ef_website_id']);
			if(!$website){
				continue;
			}
			if($website['status'] == 50){
			    // the website is archived
			    continue;
            }
			$cfg = $website['cfg'];
			$hostname = $cfg['hostname'];
			$hostname_part = $this->hostname_part($hostname);
			$interval = intval($cfg['heartbeat_interval']);
			if(!$interval){
				$interval = 60;
			}
			$dt = time() + $interval;
			$emps->db->query("update ".TP."ef_heartbeat set nedt = $dt where id = ".$ra['id']);
//			exec("curl http://".$hostname_part.".".$ef->defaults['hostname_short']."/heartbeat/ &");
			exec("echo \"curl -L http://".$hostname."/heartbeat/\" | at -M now");
		}
		
		if($this->last_tick < (time() - 60)){
			$this->last_tick = time();
			$homedir = $ef->defaults['home'];
			$list = file_get_contents($homedir."/heartbeat.conf");
			$x = explode("\n", $list);
			foreach($x as $v){
				$v = trim($v);
				if(substr($v, 0, 1) == "#"){
					continue;
				}
				if($v){
					exec("echo \"curl -L ".$v."\" | at -M now");
				}
			}
		}
	}

	public function read_awstats_section($fh, $code){
	    rewind($fh);
	    while(!feof($fh)){
	        $line = fgets($fh);
	        $x = explode(" ", $line);
	        if($x[0] == 'BEGIN_MAP'){
	            $count = intval($x[1]);
	            $map = [];
	            for($i = 0; $i < $count; $i++){
	                $line = fgets($fh);
	                $x = explode(" ", $line);
	                $map[$x[0]] = intval($x[1]);
                }
                $pos = $map['POS_'.$code];
	            fseek($fh, $pos, SEEK_SET);
            }
            if($x[0] == 'BEGIN_'.$code){
	            $count = intval($x[1]);
	            $values = [];
                for($i = 0; $i < $count; $i++){
                    $line = fgets($fh);
                    $line = trim($line);
                    $x = explode(" ", $line);
                    $code = array_shift($x);
                    $values[$code] = [];
                    foreach($x as $xv){
                        $values[$code][] = $xv;
                    }
                }
                return $values;
            }
        }
    }

    public function save_stats_var($Ym, $stat_id, $code, $value){
	    global $emps;
	    $nr = [];
	    $nr['period'] = $Ym;
	    $nr['stats_id'] = $stat_id;
	    $nr['code'] = $code;
	    $row = $emps->db->sql_ensure_row("ef_stats_values", $nr);
	    $update = ['SET' => ['value' => $value]];
	    $emps->db->sql_update_row("ef_stats_values", $update, "id = {$row['id']}");
    }

    public function save_stats_vars($Ym, $stat_id, $vars){
	    foreach($vars as $n => $v){
	        $this->save_stats_var($Ym, $stat_id, $n, $v);
        }
    }

	public function update_website_stats($stat, $hostname, $dt){
	    global $emps;

	    $mY = date("mY", $dt);
        $stats_file = "/var/lib/awstats/awstats{$mY}.{$hostname}.txt";
//        echo $stats_file."<br/>";
        $fh = fopen($stats_file, "rb");
        if(!$fh){
            return false;
        }

        $general = $this->read_awstats_section($fh, "GENERAL");
        if($general){
            $domain = $this->read_awstats_section($fh, "DOMAIN");
            if($domain){
                $vars = [];
                $vars['unique'] = $general['TotalUnique'][0];
                $vars['visits'] = $general['TotalVisits'][0];
                $vars['pages'] = $domain['ip'][0];
                $vars['hits'] = $domain['ip'][1];
                $vars['bw'] = $domain['ip'][2] / (1024*1024);

                $this->save_stats_vars(date("Ym", $dt), $stat['id'], $vars);
            }
        }

        fclose($fh);
    }

    public function stats_cycle(){
        global $ef, $emps;

        require_once $emps->common_module('datetime/dates.class.php');
        $dates = new EMPS_Dates;

        $dt = time();
        $r = $emps->db->query("select * from ".TP."ef_stats where status = 10 and nedt <= $dt");
        while($ra = $emps->db->fetch_named($r)){
            $website = $ef->load_website($ra['ef_website_id']);
            if(!$website){
                continue;
            }
            if($website['status'] == 50){
                // the website is archived
                continue;
            }
            $cfg = $website['cfg'];
            $hostname = $cfg['hostname'];

            $interval = 23 * 60 * 60 + floor(rand(0, 60 * 60));
//            $interval = 60;
            $dt = time() + $interval;

            $emps->db->query("update ".TP."ef_stats set nedt = $dt where id = ".$ra['id']);

            /**
             * Update the stats variables
             */

            $dt = time();
            for($i = 0; $i < 12; $i++){
                $this->update_website_stats($ra, $hostname, $dt);
                $dt = $dates->prev_period("month", $dt);
            }

        }
    }

    public function save_db_stat($database, $code, $value){
	    global $emps;

	    $period = date("Ymd", time());
        $nr = [];
        $nr['database'] = $database;
        $nr['code'] = $code;
        $nr['period'] = $period;
        $stats_row = $emps->db->sql_ensure_row("ef_database_stats_values", $nr);
//        dump($nr);
//        dump($stats_row);

        if($stats_row){
            $update = ['SET' => ['value' => $value]];
            $emps->db->sql_update_row("ef_database_stats_values", $update, "id = {$stats_row['id']}");
        }
    }

    public function db_stats_cycle($override){
	    global $emps;

	    $last_db_stats = intval($emps->get_setting("_last_db_stats"));

//	    echo $last_db_stats;

	    $dt = time() - 24*60*60;

	    if($last_db_stats < $dt || $override){

	        $emps->save_setting("_last_db_stats", time());

            $r = $emps->db->query("select schema_name, sum(count_star) as `count_star_sum`, 
                sum(sum_rows_examined) as `sum_rows`
                from performance_schema.`events_statements_summary_by_digest` group by `schema_name` 
                order by `count_star_sum` desc");

            $emps->db->sql_error();

            while($ra = $emps->db->fetch_named($r)){
                if(!$ra['schema_name']){
                    continue;
                }
                $this->save_db_stat($ra['schema_name'], "count_star", $ra['count_star_sum']);
                $this->save_db_stat($ra['schema_name'], "sum_rows", $ra['sum_rows']);
            }

            $emps->db->query("truncate table performance_schema.`events_statements_summary_by_digest`");
        }
    }
	
	public function cycle(){
		global $emps;
		
		$this->heartbeat_cycle();
		$this->stats_cycle();
        $this->db_stats_cycle(false);
		
		while(true){
			$r = $emps->db->query("select * from ".TP."ef_commands where status = 0 order by id asc limit 1");
			
			if(!$r){
				$GLOBALS['die_now'] = true;
				break;
			}
			
			$ra = $emps->db->fetch_named($r);
			
			if(!$ra){
				break;
			}
			
			$this->execute_command($ra);
			
			unset($ra);
			
			$emps->db->free($r);
			
			gc_collect_cycles();
			clearstatcache();
			
			if($GLOBALS['die_now']){
				break;
			}
		}
	}
}

