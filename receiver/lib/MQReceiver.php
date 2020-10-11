<?php

class MQReceiver{
	public $showLog = true;
	private $address = '127.0.0.1';
	private $port = 8887;
	private $channel = 'generic';
	private $clientID = '';

	/**
	 * Constructor
	 * @param String $address Server address
	 * @param Int $port Port number
	 * @param String $username Ursename
	 * @param String $password Password
	 * @param String $channel Channel name
	 */
	public function __construct($address = "127.0.0.1", $port = 8887, $username = '', $password = '', $channel = 'generic')
	{
		set_time_limit(0);
		$this->address = $address;
		$this->port = $port;
		$this->channel = $channel;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Connect to server
	 */
	private function connect()
	{
		$this->clientID = uniqid().time();
		$this->errorcode = '';
		if(!($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$this->errorcode = socket_last_error();
			$this->errormsg = socket_strerror($this->errorcode);
			$this->log("Couldn't create socket: [$this->errorcode] $this->errormsg \n");
		}

		$this->log("Socket created \n");

		if(!socket_connect($this->socket, $this->address, $this->port)) {
			$this->errorcode = socket_last_error();
			$this->errormsg = socket_strerror($this->errorcode);
			$this->log("Could not connect: [$this->errorcode] $this->errormsg \n");
		}
	}

	/**
	 * Login to server
	 * @param String $username Ursename
	 * @param String $password Password
	 */
	private function login($username = null, $password = null)
	{
		if($username !== null)
		{
			$this->username = $username;
		}
		if($password !== null)
		{
			$this->password = $password;
		}
		if($this->socket == null)
		{
			$this->connect();
		}
		$message = json_encode(array(
			'id' => $this->clientID,
			'command' => 'login',
			'type' => 'receiver', 
			'authorization'=>base64_encode($this->username.':'.$this->password)
			)
		);
		if(!socket_send($this->socket, $message, strlen($message), 0)) 
		{
			$this->errorcode = socket_last_error();
			$this->errormsg = socket_strerror($this->errorcode);

			$this->log("Could not send data: [$this->errorcode] $this->errormsg \n");
		}
		usleep(100000);
	}
	
	/**
	 * Process message
	 * @param String $data Message received
	 */
	public function processMessage($data)
	{
		// define your code here or on your extend class
	}

	/**
	 * Run the receiver process
	 * @param String $channel Channel name
	 */
	public function run($channel = null)
	{
		if($channel !== null)
		{
			$this->channel = $channel;
		}
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
						$this->errormsg = socket_strerror($this->errorcode);
						$this->log("Could not send data: [$this->errorcode] $this->errormsg \n");
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

	/**
	 * Log
	 * @param String $text Text to be logged
	 */
	private function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}


?>
