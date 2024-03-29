<?php

$emps->plaintext_response();
$emps->no_time_limit();

$postkey = $_POST['postkey'];

$real_postkey = $emps->get_setting("postkey");

if (!$real_postkey) {
    echo "Undefined post key!\r\n";
    exit;
}

if ($postkey == $real_postkey) {
    $data = $_POST['data'];
    $filepath = $_POST['filepath'];

    $uniq = md5(uniqid(json_encode($_POST).time())).".dat";
    $tmpfile = EMPS_SCRIPT_PATH."/local/temp_c/{$uniq}";
    file_put_contents($tmpfile, $data);
    $rv = shell_exec("chmod 0777 {$tmpfile} && mv {$tmpfile} {$filepath} 2>&1");
    echo $rv."\r\n";
    if (file_exists($tmpfile)) {
        echo " - FAILED, DELETING {$tmpfile} => {$filepath} ";
        unlink($tmpfile);
    }
    echo " - DONE\r\n";
} else {
    echo "NO KEY";
}