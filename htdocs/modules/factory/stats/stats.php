<?php

if($emps->auth->credentials("admin")){

    $slst = [];
    $r = $emps->db->query("select * from ".TP."ef_stats");
    while($ra = $emps->db->fetch_named($r)){
        if(!$ra['ef_website_id']){
            continue;
        }
        $stats = $ef->list_stats($ra['id']);
        if(!$slst[$stats['period']]){
            $slst[$stats['period']] = ['period' => $stats['period'], 'websites' => []];
        }

        $slst[$stats['period']]['websites'][$ra['ef_website_id']] = $stats;
    }

    dump($slst);

}else{
    $emps->deny_access("AdminNeeded");
}