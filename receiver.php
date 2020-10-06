<?php
require_once dirname(__FILE__)."/lib/MQReceiver.php";
class Receiver extends MQReceiver{
	public function processMessage($message)
	{
		$object = json_decode($message);
		$rows = $object->data;
		foreach($rows as $idx=>$data)
		{
			echo "Time     : ".date('j F Y H:i:s', $data->time)."\r\nReceiver : ".$data->receiver."\r\nMessage  : ".$data->message."\r\n\r\n";
		}
	}
}

$receiver = new Receiver("127.0.0.1", 8887, 'sms');
$receiver->run();
?>