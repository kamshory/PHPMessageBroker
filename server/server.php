<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQServer.php";

$port = 8887;
$server = new MQServer($port, dirname(__FILE__)."/lib/.htpasswd", true);
$server->showLog = false;
$server->run();
?>