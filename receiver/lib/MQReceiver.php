<?php

class MQReceiver{
	public $showLog = true;
	private $address = '127.0.0.1';
	private $port = 8887;
	private $channel = 'generic';
	private $clientID = '';
	protected $chunk = 1024;
	protected $bitDepth = 4;

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
		$this->socket = null;
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
		if(!$this->sendSocket($this->socket, $message)) 
		{
			$this->errorcode = socket_last_error();
			$this->errormsg = socket_strerror($this->errorcode);

			$this->log("Could not send data: [$this->errorcode] $this->errormsg \n");
		}
		usleep(100000);
	}

	private function writeSocket($readSock, $data)
	{
		$length = strlen($data);
		$header = $this->createHeader($length);
		$chunks = $this->chunkSplit($data, $this->chunk);
		print_r($chunks);
		echo "HEADER1 = $header\r\n";
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
		echo "HEADER2 = $header\r\n";
		$chunks = $this->chunkSplit($data, $this->chunk);
		print_r($chunks);
		socket_send($readSock, $header, strlen($header), 0);
		foreach($chunks as $message)
		{
			echo "Send $message\r\n";
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
			echo "i = $i\r\n";
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
					if(!$this->sendSocket($this->socket, $message)) {
						$this->errorcode = socket_last_error();
						$this->errormsg = socket_strerror($this->errorcode);
						$this->log("Could not send data: [$this->errorcode] $this->errormsg \n");
						continue;
					}
					do
					{
						$data = @socket_read($this->socket, $this->bitDepth,  PHP_BINARY_READ);
						if($data === false)
						{
							continue 2;
						}
						if($data !== null)
						{
							$length = $this->parseHeader($data);
							if($length > 0)
							{
								$incommingMessage = "";
								$i = 0;	
								$buff = "";
								do
								{
									$buff = socket_read($newsock, $this->chunk, PHP_BINARY_READ);
									$incommingMessage .= $buff;
									$i += $this->chunk;
								}
								while($i < $length && $buff !== null);
								echo "Incomming $incommingMessage\r\n";
								$this->log("Receipt new connection...\r\n");
								$this->processMessage($incommingMessage);
							}
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
