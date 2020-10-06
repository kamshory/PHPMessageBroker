<?php
class MQSender
{	
	public $server = '127.0.0.1';
	public $port = 8889;
	public $showLog = false;
	private $socket = null;
	private $username = null;
	private $password = null;
	public function __construct($server = "127.0.0.1", $port = 8889, $username = '', $password = '')
	{
		set_time_limit(0);
		$this->server = $server;
		$this->port = $port;
		$this->username = $username;
		$this->password = $password;
		$this->connect();
		$this->login($this->username, $this->password);
	}
	private function login($username, $password)
	{
		if($this->socket == null)
		{
			$this->connect();
		}
		$message = json_encode(array(
			'id' => uniqid().time(),
			'command' => 'login',
			'type' => 'sender', 
			'authorization'=>base64_encode($username.':'.$password)
			)
		);
		if(!socket_send($this->socket, $message, strlen($message), 0)) 
		{
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);

			$this->log("Could not send data: [$errorcode] $errormsg \n");
		}
		usleep(100000);
	}
	private function connect()
	{
		if(!($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
			$this->log("Couldn't create socket: [$errorcode] $errormsg \n");
		}

		$this->log("Socket created \n");

		//Connect socket to remote server
		if(!socket_connect($this->socket, $this->server, $this->port)) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
			$this->log("Could not connect: [$errorcode] $errormsg \n");
		}
	}
	public function send($data, $channel)
	{
		if($this->socket == null)
		{
			$this->connect();
			$this->login($this->username, $this->password);
		}
		else
		{
			$message = json_encode(array(
				'command' => 'message',
				'type' => 'sender', 
				'channel'=>$channel,
				'data'=>$data
				)
			);
			

			$this->log("Connection established \n");

			//Send the message to the server
			if(!socket_send($this->socket, $message, strlen($message), 0)) 
			{
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);

				$this->log("Could not send data: [$errorcode] $errormsg \n");
			}
			$this->log("Message send successfully \n");
			usleep(20000);
		}
	}
	public function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}


?>