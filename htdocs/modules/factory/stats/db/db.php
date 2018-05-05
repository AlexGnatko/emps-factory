<?php

if($emps->auth->credentials("admin")){

    $slst = [];
    $r = $emps->db->query("select * from ".TP."ef_database_stats_values");
    while($ra = $emps->db->fetch_named($r)){
        if(!$ra['database']){
            continue;
        }

        $period = $ra['period'];
        $slst[$period]['period'] = $period;
        $slst[$period]['databases'][$ra['database']]['name'] = $ra['database'];
        $slst[$period]['databases'][$ra['database']]['stats'][$ra['code']] = $ra['value'];
        $slst[$period]['stat'][$ra['code']] += $ra['value'];
    }

    foreach($slst as $n => $v){
        $databases = $v['databases'];
        usort($databases, function($a, $b){
            if($a['stats']['count_star'] > $b['stats']['count_star']){
                return -1;
            }
            if($a['stats']['count_star'] < $b['stats']['count_star']){
                return 1;
            }
            return 0;
        });
        foreach($databases as $nn => $vv){
            $databases[$nn]['stats'] = $ef->analyse_db_stats($vv['stats']);
        }
        $slst[$n]['stat'] = $ef->analyse_db_stats($slst[$n]['stat']);
        $slst[$n]['databases'] = $databases;
    }

    dump($slst);

    $smarty->assign("slst", $slst);

}else{
    $emps->deny_access("AdminNeeded");
}