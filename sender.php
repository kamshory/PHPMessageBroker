<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQSender.php";
$sender = new MQSender('127.0.0.1', 8887, 'mqadmin', 'mqpassword');

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