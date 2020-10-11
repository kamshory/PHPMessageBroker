<?php
class MQSender
{	
	private $address = '127.0.0.1';
	private $port = 8889;
	public $showLog = false;
	private $socket = null;
	private $username = null;
	private $password = null;
	public $connected = false;

	/**
	 * Constructor
	 * @param String $address Server address
	 * @param Int $port Port number
	 * @param String $username Ursename
	 * @param String $password Password
	 */
	public function __construct($address = "127.0.0.1", $port = 8889, $username = '', $password = '')
	{
		set_time_limit(0);
		$this->address = $address;
		$this->port = $port;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Login
 	 * @param String $username Ursename
	 * @param String $password Password
	 * @return Boolean true if success, false if failed
	 */

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
			$this->connected = false;
			return false;
		}
		$this->connected = true;
		usleep(10000);
		return true;
	}

	/**
	 * Connect to the server
	 */
	public function connect()
	{
		if(!($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
			$this->log("Couldn't create socket: [$errorcode] $errormsg \n");
		}

		$this->log("Socket created \n");

		//Connect socket to remote address
		if(!socket_connect($this->socket, $this->address, $this->port)) {
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
			$this->log("Could not connect: [$errorcode] $errormsg \n");
		}
		if($this->socket != null)
		{
			return $this->login($this->username, $this->password);
		}
		else
		{
			$this->connected = false;
			return false;
		}
	}

	/**
	 * Send data to server
	 * @param Object $data Data to be send
	 * @param String $channel Channel name
	 * @return Boolean true if success, false if failed
	 */
	public function send($data, $channel)
	{
		if(!$this->connected)
		{
			$this->connect();
		}
		if($this->connected)
		{
			$message = json_encode(array(
				'command' => 'message',
				'type' => 'sender', 
				'channel'=>$channel,
				'data'=>$data
				)
			);
			//Send the message to the address
			if(!socket_send($this->socket, $message, strlen($message), 0)) 
			{
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				$this->log("Could not send data: [$errorcode] $errormsg \n");
				return false;
			}
			$this->log("Message send successfully \n");
			usleep(10000);
			return true;
		}
		return false;
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