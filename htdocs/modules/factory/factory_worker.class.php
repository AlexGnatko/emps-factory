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
	
	public function install_local_php($data, $ra){
		global $emps;
		
		$this->say("Installing local.php...");
		
		$file_path = $data['file_name'];
		$htdocs = $data['htdocs'];
		$owner = $data['owner'];

		$target_dir = $htdocs.'/local';		
		$target_path = $target_dir.'/local.php';

		if(!is_dir($target_dir)){
			$this->say("Creating ".$target_dir."...");
			mkdir($target_dir, 0666, true);
			chown($target_dir, $owner);
		}
		$this->move_file($file_path, $target_path, 0644, $owner);
		return true;
	}
	
	public function move_file($file_path, $target_path, $rights, $owner){
		$this->say("Moving ".$file_path." to ".$target_path."...");
		rename($file_path, $target_path);
		$this->say("Setting owner ".$owner.", chmod ".sprintf("0%o", $rights)."...");
		chown($target_path, $owner);						
		chmod($target_path, $rights);
	}
	
	public function create_dir($target_dir, $rights, $owner){
		$this->say("Creating directory ".$target_dir.", chmod ".sprintf("0%o", $rights)."...");
		mkdir($target_dir, $rights, true);
		$this->say("Setting owner ".$owner);
		chown($target_dir, $owner);
	}
	
	public function put_file($file_name, $rights, $owner, $data){
		$this->say("Creating file ".$file_name."...");
		file_put_contents($file_name, $data);
		$this->say("Setting owner ".$owner.", chmod ".sprintf("0%o", $rights)."...");
		chown($file_name, $owner);
		chmod($file_name, $rights);
	}
	
	public function copy_file($source_name, $file_name, $rights, $owner){
		$this->say("Copying ".$source_name." to ".$file_name."...");
		copy($source_name, $file_name);
		$this->say("Setting owner ".$owner.", chmod ".sprintf("0%o", $rights)."...");
		chown($file_name, $owner);
		chmod($file_name, $rights);
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
		
		$file_name = $htdocs.'/index.php';
		
		if(!file_exists($file_name) || $overwrite){		
			$fn = EMPS_PATH_PREFIX.'/sample_index.php';	
			$source_name = stream_resolve_include_path($fn);
	
			$this->copy_file($source_name, $file_name, 0644, $owner);
		}

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
		}
	}
}

?>