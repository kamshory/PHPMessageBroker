<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQServer.php";

$users = 'manager:$apr1$9w8ia7th$OzQ8sEgIqWHUXWS/NWbIe/
user-admin:$apr1$BUB5/4Td$Ndgx5ogWsHlwD9SupuQeo0
uploader:$apr1$M.59./xT$8tlERsHYFHEJ7L20xI2oJ/';

$server = new MQServer(8887, $users);
$server->showLog = true;
$server->run();
?>