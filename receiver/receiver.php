<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQReceiver.php";
class Receiver extends MQReceiver{
	public function processMessage($message)
	{
		global $i;
		global $j;
		$object = json_decode($message);
		$rows = $object->data;
		$i++;
		foreach($rows as $idx=>$data)
		{
			$j++;
			echo "Time     : ".date('j F Y H:i:s', $data->time)."\r\nReceiver : ".$data->receiver."\r\nMessage  : ".$data->message."\r\n\r\n";
			//echo "i = $i | j = $j\r\n";
		}
	}
}
$i = 0;
$j = 0;
$address = "127.0.0.1";
$port = 8887;
$username = 'manager';
$password = 'Albasiko2020^';
$channel = 'sms';
$receiver = new Receiver($address, $port, $username, $password, $channel);
$receiver->showLog = false;
$receiver->run();
?>