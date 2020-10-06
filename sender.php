<?php
require_once dirname(__FILE__)."/lib/MQSender.php";
$sender = new MQSender('127.0.0.1', 8887);
$message = json_encode(array(
	'id' => uniqid().time(),
	'command' => 'message',
	'type' => 'sender', 
	'channel'=>'sms',
	'data' => array(
		'id'=>uniqid(),
		'time' => time(0),
		'receiver'=>'+6281200000000',
		'message'=>"Kode OTP Anda adalah ".mt_rand(100000, 999999)."\r\n>>>Jangan memberitahukan kode ini kepada siapapun<<<"
	)
));
$sender->showLog = false;
$sender->send($message);
?>