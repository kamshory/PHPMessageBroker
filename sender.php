<?php
class MQSender
{	
	public $server = '127.0.0.1';
	public $port = 8889;
	public $showLog = false;
	public function __construct($server = "127.0.0.1", $port = 8889)
	{
		$this->server = $server;
		$this->port = $port;
	}

	public function send($message)
	{
		set_time_limit(0);

		if(!($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
			$this->log("Couldn't create socket: [$errorcode] $errormsg \n");
		}

		$this->log("Socket created \n");

		//Connect socket to remote server
		if(!socket_connect($sock, $this->server, $this->port)) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
			$this->log("Could not connect: [$errorcode] $errormsg \n");
		}

		$this->log("Connection established \n");

		//Send the message to the server
		if(!socket_send($sock, $message, strlen($message), 0)) 
		{
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);

			$this->log("Could not send data: [$errorcode] $errormsg \n");
		}
		$this->log("Message send successfully \n");
		usleep(20000);
	}
	public function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}
$server = new MQSender('127.0.0.1', 8887);
$message = json_encode(array(
	'id' => uniqid().time(),
	'command' => 'message',
	'client_type' => 'sender', 
	'label'=>'sms',
	'data' => array(
		'id'=>uniqid(),
		'time' => time(0),
		'receiver'=>'+6281266612126',
		'message'=>"Kode OTP Anda adalah ".mt_rand(100000, 999999)."\r\n>>>Jangan memberitahukan kode ini kepada siapapun<<<"
	)
));
$server->showLog = false;
$server->send($message);

?>