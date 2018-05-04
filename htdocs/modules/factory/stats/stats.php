<?php

if($emps->auth->credentials("admin")){

    $slst = [];
    $r = $emps->db->query("select * from ".TP."ef_stats");
    while($ra = $emps->db->fetch_named($r)){
        if(!$ra['ef_website_id']){
            continue;
        }
        $stats = $ef->list_stats($ra['id']);
        foreach($stats as $stat){
            if(!$slst[$stat['period']]){
                $slst[$stat['period']] = ['period' => $stat['period'], 'websites' => []];
            }

            $website = $ef->load_website($ra['ef_website_id']);
            unset($website['_full']);
            unset($website['user']);

            $slst[$stat['period']]['websites'][$ra['ef_website_id']]['website'] = $website;
            $slst[$stat['period']]['websites'][$ra['ef_website_id']]['stats'] = $stat;
        }
    }

    dump($slst);

}else{
    $emps->deny_access("AdminNeeded");
}