<?php

$emps->no_smarty = true;

$website_id = intval($key);

$slst = [];
$r = $emps->db->query("select * from ".TP."ef_stats");
while($ra = $emps->db->fetch_named($r)){

    $period = intval($start);

    $ef->stats_peroid = $period;
    $stats = $ef->list_stats($ra['id']);
    foreach($stats as $stat){
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

$response = [];
$response['code'] = "OK";
$response['lst'] = $slst;
$emps->json_response($response); exit;
