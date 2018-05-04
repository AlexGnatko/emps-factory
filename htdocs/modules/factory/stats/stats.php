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

            $stat = $ef->analyse_stats($stat);

            $slst[$stat['period']]['websites'][$ra['ef_website_id']]['website'] = $website;
            $slst[$stat['period']]['websites'][$ra['ef_website_id']]['stats'] = $stat;

            foreach($stat as $n => $v){
                if($n != 'period'){
                    $slst[$stat['period']]['stat'][$n] += $v;
                }

            }
        }
    }

    foreach($slst as $n => $v){
        $websites = $v['websites'];
        usort($websites, function($a, $b){
            if($a['stats']['hits'] > $b['stats']['hits']){
                return -1;
            }
            if($a['stats']['hits'] < $b['stats']['hits']){
                return 1;
            }
            return 0;
        });
        $slst[$n]['stat'] = $ef->analyse_stats($slst[$n]['stat']);
        $slst[$n]['websites'] = $websites;
    }


    $smarty->assign("slst", $slst);

}else{
    $emps->deny_access("AdminNeeded");
}