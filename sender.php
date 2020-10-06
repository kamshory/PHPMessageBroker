<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQSender.php";
$address = "127.0.0.1";
$port = 8887;
$username = 'manager';
$password = 'Albasiko2020^';
$sender = new MQSender($address, $port, $username, $password);

$data = array(
	'id'=>uniqid(),
	'time' => time(0),
	'receiver'=>'+6281200000000',
	'message'=>"Kode OTP Anda adalah ".mt_rand(100000, 999999)."\r\n>>>Jangan memberitahukan kode ini kepada siapapun<<<"
);

$channel = 'sms';
$sender->showLog = false;
$sender->send($data, $channel);
?>