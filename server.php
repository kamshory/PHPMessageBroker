<?php
require_once dirname(__FILE__)."/lib/MQServer.php";
$server = new MQServer(8887);
$server->run();
?>