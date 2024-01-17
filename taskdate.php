<?php
header('Content-Type: application/json; charset=utf-8'); 
$name = $_SERVER['SERVER_NAME'];
$name = preg_replace("/[^-a-zA-Z_.0-9]+/", "", $name);
header("Access-Control-Allow-Origin: https://$name");
include "tools.php";
logInAs();


$slug = $_GET['task'];
if (!array_key_exists($slug, assignments())) {
    die('{"status":"error","message":"no such assignment"}');
}
$details = asgn_details($user, $slug);
echo '{"status":"ok","time":'.$details['update_time'].'}';
?>
