<?php

class EMPS_FactoryWorker
{
	public function execute_command($ra){
		global $emps;
		$emps->db->query("update ".TP."ef_commands set sdt = ".time()." where id = ".$ra['id']);
		$rv = shell_exec($ra['command']);
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