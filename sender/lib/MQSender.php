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
	private $clientID = "";
	protected $chunk = 1024;
	protected $bitDepth = 4;

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
	 * Connect to the server
	 */
	public function connect()
	{
		$this->clientID = uniqid().time();
		if(!($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$this->errorcode = socket_last_error();
			$this->errormsg = socket_strerror($this->errorcode);
			$this->log("Couldn't create socket: [$this->errorcode] $this->errormsg \n");
		}

		$this->log("Socket created \n");

		//Connect socket to remote address
		if(!socket_connect($this->socket, $this->address, $this->port)) {
			$this->errorcode = socket_last_error();
			$this->errormsg = socket_strerror($this->errorcode);
			$this->log("Could not connect: [$this->errorcode] $this->errormsg \n");
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
			'id' => $this->clientID,
			'command' => 'login',
			'type' => 'sender', 
			'authorization'=>base64_encode($username.':'.$password)
			)
		);
		
		if(!$this->sendSocket($this->socket, $message)) 
		{
			$this->log("Could not send data: [$this->errorcode] $this->errormsg \n");
			$this->connected = false;
			return false;
		}
		$this->connected = true;
		usleep(10000);
		return true;
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
			if(!$this->sendSocket($this->socket, $message)) 
			{
				$this->log("Could not send data: [$this->errorcode] $this->errormsg \n");
				return false;
			}
			$this->log("Message send successfully \n");
			usleep(10000);
			return true;
		}
		return false;
	}

	private function writeSocket($readSock, $data)
	{
		$length = strlen($data);
		$header = $this->createHeader($length);
		$chunks = $this->chunkSplit($data, $this->chunk);
		socket_write($readSock, $header, strlen($header));
		foreach($chunks as $message)
		{
			socket_write($readSock, $message, strlen($message));
		}
		$this->errorcode = socket_last_error();
		$this->errormsg = socket_strerror($this->errorcode);
		return ($this->errorcode == 0);
	}

	private function sendSocket($readSock, $data)
	{
		$length = strlen($data);
		$header = $this->createHeader($length);
		$chunks = $this->chunkSplit($data, $this->chunk);
		socket_send($readSock, $header, strlen($header), 0);
		foreach($chunks as $message)
		{
			socket_send($readSock, $message, strlen($message), 0);
		}
		$this->errorcode = socket_last_error();
		$this->errormsg = socket_strerror($this->errorcode);
		return ($this->errorcode == 0);

	}

	function chunkSplit($data, $chunk)
	{
		$result = array();
		$length =strlen($data);
		$x = ceil(strlen($data) / $chunk);
		for($i = 0, $j = 1; $i<$length; $i+=$chunk, $j++)
		{
			if($j < $x)
			{
				$result[] = substr($data, $i, $chunk);
			}
			else
			{
				$result[] = substr($data, $i);
			}
		}
		return $result;
	}

	private function createHeader($length)
	{
		$hex = sprintf("%x", $length);
		if((strlen($hex) % 2) == 1)
		{
			$hex = "0".$hex;
		}
		$ln = strlen($hex);
		$header = "";
		for($i = 0; $i<$ln; $i+=2)
		{
			$header .= substr($hex, $i, 2);
		}
		while(strlen($header) < ($this->bitDepth * 2))
		{
			$header = "0".$header;
		}
		return $header;
	}

	private function parseHeader($header)
	{
		if(strlen($header) % 2 == 1)
		{
			// add 0
			$len2 = strlen($header);
			$len3 = $len2 - 1;
			$sub1 = substr($header, 0, $len3);
			$sub2 = substr($header, $len3);
			if(strlen($sub2) == 1)
			{
				$sub2 = "0".$sub2;
			}
			$header = $sub1.$sub2;
		}
		$len = strlen($header);
		$length = 0;
		for($i = 0; $i<$len; $i+=2)
		{
			$length = $length * 256;
			$hex = substr($header, $i, 2);
			$length += hexdec($hex);
		}
		return $length;
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