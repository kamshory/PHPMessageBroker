<?php

class MQReceiver{
	public $showLog = true;
	private $server = '127.0.0.1';
	private $port = 8887;
	private $channel = 'generic';
	private $clientID = '';
	public function __construct($server = "127.0.0.1", $port = 8887, $username = '', $password = '', $channel = 'generic')
	{
		$this->server = $server;
		$this->port = $port;
		$this->channel = $channel;
		$this->username = $username;
		$this->password = $password;
		$this->clientID = uniqid().time();
	}
	private function login($username, $password)
	{
		if($this->socket == null)
		{
			$this->connect();
		}
		$message = json_encode(array(
			'id' => $this->clientID,
			'command' => 'login',
			'type' => 'receiver', 
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
		// define your code here or on your extend class
	}

	private function connect()
	{
		if(!($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$this->errorcode = socket_last_error();
			$errormsg = socket_strerror($this->errorcode);
			$this->log("Couldn't create socket: [$this->errorcode] $errormsg \n");
		}

		$this->log("Socket created \n");

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
						'id' => $this->clientID,
						'channel'=>$this->channel					
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

	private function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}


?>
