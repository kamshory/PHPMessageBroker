<?php

class MQReceiver{
	public $showLog = false;
	public $server = '127.0.0.1';
	public $port = 8889;
	public $channel = 'generic';
	public function __construct($server = "127.0.0.1", $port = 8889, $username = '', $password = '', $channel = 'channel')
	{
		$this->server = $server;
		$this->port = $port;
		$this->channel = $channel;
		$this->username = $username;
		$this->password = $password;
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
			$this->errorcode = socket_last_error();
			$errormsg = socket_strerror($this->errorcode);

			$this->log("Could not send data: [$this->errorcode] $errormsg \n");
		}
		usleep(100000);
	}
	
	public function processMessage($data)
	{
		// define here
	}
	private function connect()
	{
		if(!($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$this->errorcode = socket_last_error();
			$errormsg = socket_strerror($this->errorcode);
			$this->log("Couldn't create socket: [$this->errorcode] $errormsg \n");
		}

		$this->log("Socket created \n");

		//Connect socket to remote server
		if(!socket_connect($this->socket, $this->server, $this->port)) {
			$this->errorcode = socket_last_error();
			$errormsg = socket_strerror($this->errorcode);
			$this->log("Could not connect: [$this->errorcode] $errormsg \n");
		}
	}

	public function run()
	{
		set_time_limit(0);
		do
		{
			$this->socket = null;
			try
			{
				$this->errorcode = "";
				$this->connect();
				$this->login($this->username, $this->password);
				if(!$this->errorcode)
				{
					$this->log("Connection established \n");
					$message = json_encode(array(
						'command' => 'register',
						'type' => 'receiver', 
						'id' => uniqid().time(0),
						'channel'=>$this->channel,
						'data' => array(
							'id'=>uniqid().time(0),
							'time' => gmdate('Y-m-d H:i:s')
						)
					));
					if(!socket_send($this->socket, $message, strlen($message), 0)) {
						$this->errorcode = socket_last_error();
						$errormsg = socket_strerror($this->errorcode);
						$this->log("Could not send data: [$this->errorcode] $errormsg \n");
						continue;
					}
					do
					{
						$data = @socket_read($this->socket, 8192,  PHP_BINARY_READ);
						if($data === false)
						{
							continue 2;
						}
						if($data !== null)
						{
							if($this->errorcode)
							{
								$this->log("Could not read data: [$this->errorcode] $errormsg \n");
							}
							$this->processMessage($data);
						}

					}
					while(true);
				}
			}
			catch(Exception $e)
			{
				$this->log( "Reconnect...\r\n");
			}
		}
		while(true);
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
