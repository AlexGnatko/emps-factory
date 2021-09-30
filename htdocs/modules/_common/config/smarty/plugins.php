<?php
global $smarty;

function smarty_modifier_salt($v){
    $value = md5(uniqid(microtime().$v));
    return $value;
}

$smarty->registerPlugin("modifier", "salt", "smarty_modifier_salt");
