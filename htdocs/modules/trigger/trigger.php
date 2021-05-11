<?php

$emps->no_smarty = true;
$emps->plaintext_response();

$e_key = $emps->db->sql_escape($key);
$e_start = $emps->db->sql_escape($start);

$row = $emps->db->get_row("ef_remote_commands", "url = '{$e_key}' and apikey = '{$e_start}'");
if ($row) {
    $ef->add_command($row['command']);
    echo "OK";
} else {
    $emps->not_found();
}
