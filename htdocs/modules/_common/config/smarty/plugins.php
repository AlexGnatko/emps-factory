<?php
global $smarty;

function smarty_modifier_money($v){
    $value = md5(uniqid(microtime().$v));
    return $value;
}

$smarty->registerPlugin("modifier", "salt", "smarty_modifier_salt");
