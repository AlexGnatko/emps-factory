<?php

$emps->no_smarty = true;

$database_name = $key;

$period = intval($start);

$sp = $period * 100 + 1;
$ep = $sp + 30;

$slst = [];
$r = $emps->db->query("select * from ".TP."ef_database_stats_values where period >= {$sp} and period <= {$ep}");
while($ra = $emps->db->fetch_named($r)){
    if(!$ra['database']){
        continue;
    }

    $period = $ra['period'];
    $slst[$period]['period'] = $period;
    if ($ra['database'] == $database_name) {
        $slst[$period]['databases'][$ra['database']]['name'] = $ra['database'];
        $slst[$period]['databases'][$ra['database']]['stats'][$ra['code']] = intval($ra['value']);
    }
    $slst[$period]['stat'][$ra['code']] += intval($ra['value']);
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

$o_stat = [];
$d_stat = [];
foreach ($slst as $row) {
    foreach($row['stat'] as $n => $v) {
        $o_stat[$n] += $v;
    }
    foreach($row['databases'][0]['stats'] as $n => $v) {
        $d_stat[$n] += $v;
    }
}

foreach($o_stat as $n => $v) {
    $o_stat[$n] /= count($slst);
}

$o_stat = $ef->analyse_db_stats($o_stat);

foreach($d_stat as $n => $v) {
    $d_stat[$n] /= count($slst);
}

$d_stat = $ef->analyse_db_stats($o_stat);

$response = [];
$response['code'] = "OK";
$response['lst'] = $slst;
$response['o_stat'] = $o_stat;
$response['d_stat'] = $d_stat;
$emps->json_response($response); exit;
