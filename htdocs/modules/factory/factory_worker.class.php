<?php

class EMPS_FactoryWorker
{
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
			mkdir($target_dir, 0666, true);
			$this->file_chown($target_dir, $owner);
			$this->file_chmod($target_dir, 0666);
		}
		$this->move_file($file_path, $target_path, 0644, $owner);
		
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
				$this->create_dir($ssh_dir, 0644, $owner);
			}
			$file_name = $ssh_dir.'/authorized_keys';
			file_put_contents($file_name, $text);
			exec("chown ".$owner." ".$file_name);
			$this->file_chmod($file_name, 0644);
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
		if(!is_dir($git_user_path)){
			$this->create_dir($git_user_path, 0644, $owner);
		}
		
		$git_repo_path = $ef->defaults['git_path'].'/'.$owner.'/'.$hostname.'.git';

		if(!is_dir($git_repo_path)){
			$this->create_dir($git_repo_path, 0644, $owner);
		}
		
		$fail = false;
		if(file_exists($git_repo_path.'/config')){
			$this->say("Git Repository exists. Nothing to do!");
			$fail = true;
		}else{
			$this->echo_shell("cd ".$git_repo_path." && git --bare init ".
			"&& git add .gitignore && git add htdocs ".
			"&& git commit -m \"EMPS Factory Init\"");
		}
		
		if(!$false){
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
		
		$htdocs = $cfg['path'];
		
		$www_dir = $website['www_dir'];
		
		$this->say("WWW Dir: ".$www_dir);

		if(!is_dir($www_dir)){
			$this->create_dir($www_dir, 0644, $owner);
		}
				
		if(!is_dir($htdocs)){
			$this->create_dir($htdocs, 0644, $owner);
		}
		
		$target_dir = $htdocs.'/local';
		if(!is_dir($target_dir)){
			$this->create_dir($target_dir, 0644, $owner);
		}
		$target_dir = $htdocs.'/local/upload';
		if(!is_dir($target_dir)){
			$this->create_dir($target_dir, 0666, $owner);
		}
		$target_dir = $htdocs.'/local/temp_c';
		if(!is_dir($target_dir)){
			$this->create_dir($target_dir, 0666, $owner);
		}
		
		$htaccess = $smarty->fetch("db:_factory/temps,htaccess");
		$file_name = $htdocs.'/.htaccess';
		
		if(!file_exists($file_name) || $overwrite){
			$this->put_file($file_name, 0644, $owner, $htaccess);
		}
		
		$gitignore = $smarty->fetch("db:_factory/temps,gitignore");
		$file_name = $www_dir.'/.gitignore';
		
		if(!file_exists($file_name) || $overwrite){
			$this->put_file($file_name, 0644, $owner, $gitignore);
		}
		
		$smarty->assign("dir", $www_dir);
		$post_checkout = $smarty->fetch("db:_factory/temps,post_checkout");
		$file_name = $www_dir.'/post-checkout.sh';
		
		if(!file_exists($file_name) || $overwrite){
			$this->put_file($file_name, 0744, $owner, $post_checkout);
		}
		
		$file_name = $htdocs.'/index.php';
		
		if(!file_exists($file_name) || $overwrite){		
			$fn = EMPS_PATH_PREFIX.'/sample_index.php';	
			$source_name = stream_resolve_include_path($fn);
	
			$this->copy_file($source_name, $file_name, 0644, $owner);
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
	
	public function cycle(){
		global $ef, $emps;
		
		while(true){
			$r = $emps->db->query("select * from ".TP."ef_commands where status = 0 order by id asc limit 1");
			
			$ra = $emps->db->fetch_named($r);
			
			if(!$ra){
				break;
			}
			
			$this->execute_command($ra);
			
			unset($ra);
			
			$emps->db->free($r);
			
			gc_collect_cycles();
			
			if($GLOBALS['die_now']){
				break;
			}
		}
	}
}

?>