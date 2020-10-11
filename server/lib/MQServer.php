<?php
require_once dirname(__FILE__)."/HTPasswd.php";
class MQServer{
	public $showLog = false;
	private $port = 8887;
	private $numberOfReceiver = 1;
	private $clients = array();
	private $read = array();
	private $receivers = null;
	protected $nexRecord = 0;
	protected $keepData = false;
	protected $chunk = 1024;
	protected $bitDepth = 4;

	/**
	 * Custructor
	 * @param Int $port Port number.
	 * @param Int $numberOfReceiver Maximum number of receiver.
	 * @param String $userList User list. If $userFromFile is false, $userList is pairs of username and password.  If $userFromFile is true, $userList is fina path instad of pairs of username and password. 
	 * @param Boolean $userFromFile Indicate that $userList is a file path instad of pairs of username and password.
	 */
	public function __construct($port = 8887, $numberOfReceiver = 0, $userList = null, $userFromFile = false)
	{
		$this->port = $port;
		if($numberOfReceiver < 0)
		{
			$numberOfReceiver = 0;
		}
		$this->numberOfReceiver = $numberOfReceiver;
		$this->receivers = new \SplObjectStorage();
		if($userList != null)
		{
			$this->userList = $userList;
			$this->userFromFile = $userFromFile;
			$this->loadUser($this->userList, $this->userFromFile);
		}
	}
	
	/**
	 * Realod user list if the source of user list is file
	 */
	public function reloadUser()
	{
		$this->loadUser($this->userList);
	}

	/**
	 * Load user list from file
	 * @param String $userList User list
	 * @param Boolean $userFromFile Indicate that user list is a file path
	 */
	private function loadUser($userList, $userFromFile)
	{
		if(!$userFromFile)
		{
			$this->users = $userList;
		}
		else
		{
			$file = $userList;
			if(file_exists($file))
			{
				$this->users = array();
				$row = file($file);
				foreach($row as $idx=>$line)
				{
					$row[$idx] = trim($line, " \r\n\t ");
				}
				$this->users = implode("\r\n", $row);
			}
		}
	}

	/**
	 * Remove client from array
	 * @param Socket $readSock Client socket
	 */
	private function removeClients($readSock)
	{
		// remove client for $this->clients array
		$key = array_search($readSock, $this->clients);					
		// Remove client from receiver
		foreach($this->receivers as $i=>$j)
		{
			if($j->socket === $readSock)
			{
				unset($this->receivers[$i]);
				break;
			}
		}
		unset($this->clients[$key]);
	}

	/**
	 * Validate client
	 * @param String $username Username
	 * @param String $password Password
	 */
	private function validateUser($username, $password)
	{
		return \HTPasswd::auth($username, $password, $this->users);
	}

	/**
	 * Check authorization
	 * @param Socket $newsock Client socket
	 * @param JSONObject $clientData Client data
	 */
	private function authorization($newsock, $clientData)
	{
		print_r($clientData);
		if(isset($clientData->command))
		{
			if($clientData->command == 'login')
			{
				$authorization = $clientData->authorization;
				$str = base64_decode($authorization);
				if(stripos($str, ":") !== false)
				{
					$arr = explode(":", $str, 2);
					$username = $arr[0];
					$password = $arr[1];
					return $this->validateUser($username, $password);
				}
			}
			if($clientData->command == 'reload-user')
			{
				$this->reloadUser();
			}
			if($clientData->command == 'ping')
			{
				$this->replyPing($newsock, $data);
			}
		}
		return false;
	}

	/**
	 * Reply ping
	 * @param Socket $newsock Client socket
	 * @param String $requestMessage Request message
	 */
	private function replyPing($newsock, $requestMessage)
	{
		$clientData = json_decode($requestMessage);
		$responseMessage = json_encode($clientData);
		$this->writeSocket($newsock, $responseMessage);
	}

	/**
	 * Process data
	 * @param Socket $readSock Client socket
	 * @param JSONObject $requestData Request message
	 */
	private function processData($readSock, $requestData)
	{
		$requestData = trim($requestData);
		if(!empty($requestData))
		{
			$clientData = json_decode($requestData);
			if($clientData->type === "receiver" && $clientData->command == "register")
			{
				$channel = isset($clientData->channel)?$clientData->channel:'generic';
				$id = isset($clientData->id)?$clientData->id:(uniqid().time(0));
				$client = new \stdClass();
				$client->socket = $readSock;
				$client->channel = $channel;
				$client->index = $id;
				$this->receivers[$clientData->id] = $client; 
				
				if($this->keepData)
				{
					do
					{
						$responseMessage = $this->loadFromDatabase($clientData->channel);
						if($responseMessage !== null)
						{
							$this->writeSocket($readSock, $responseMessage);
							/**
							 * socket_write($readSock, $responseMessage, strlen($responseMessage));
							 */
						}
					}
					while($this->nextRecord > 0);
				}
			}
			else if($clientData->type === "sender" && $clientData->command == "message")
			{
				$this->sendToReceivers($clientData);
			}
		}
	}

	/**
	 * Send data to receivers
	 * @param JSONObject $clientData Client data
	 */
	private function sendToReceivers($clientData)
	{
		if(count($this->receivers) == 0 && $this->keepData)
		{
			$this->saveToDatabase($clientData);
		}
		else
		{
			$responseMessage = json_encode(array("command"=>"message", "data"=>array($clientData->data)));
			$channel = isset($clientData->channel)?$clientData->channel:'generic';
			$count = 0;
			foreach ($this->receivers as $receiver) 
			{
				if($receiver->channel == $channel)
				{
					$count++;
					$this->writeSocket($receiver->socke, $responseMessage);
					if($this->numberOfReceiver > 0 && $count >= $this->numberOfReceiver)
					{
					break;
					}
				}
			}			
		}
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
	 * Run server
	 */
	public function run()
	{ 
		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);   
		socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($sock, 0, $this->port);
		socket_listen($sock);
		$this->clients = array($sock);
		$this->receivers = array();
		do
		{
			$this->read = $this->clients;
			if(socket_select($this->read, $write = NULL, $except = NULL, 0) < 1)
			{
				continue;
			}
			if(in_array($sock, $this->read)) 
			{
				$this->clients[] = $newsock = socket_accept($sock);
				socket_getpeername($newsock, $ip);
				$key = array_search($sock, $this->read);
				unset($this->read[$key]);
				$data = @socket_read($newsock, 8,  PHP_BINARY_READ);
				if ($data === false) 
				{
					$this->removeClients($newsock);
					continue;
				}
				else
				{
					$data = trim($data);
					if(!empty($data))
					{
						echo "HEADER X = $data\r\n";
						$length = $this->parseHeader($data);
						if($length > 0)
						{
							$incommingMessage = "";
							$i = 0;	
							$buff = "";
							while($i < $length && $buff !== null)
							{
								$buff = socket_read($newsock, $this->chunk, PHP_BINARY_READ);
								$incommingMessage .= $buff;
								$i += strlen($buff);
								if($i == $length)
								{
								break 1;
								}
							}
							
							echo "$i | $incommingMessage\r\n";
							$clientData = json_decode($incommingMessage);
							$this->log("Receipt new connection...\r\n");
							if(!$this->authorization($newsock, $clientData))
							{
								$this->removeClients($newsock);
								echo "Invalid\r\n";
								continue;
							}
							else
							{
								echo "Logged in\r\n";
							}
						}
					}
				}
			}		   
			foreach($this->read as $readSock) 
			{
				$data = @socket_read($readSock, 8,  PHP_BINARY_READ);
				var_dump($data);
				if ($data === false) 
				{
					$this->removeClients($readSock);
					continue;
				}
				$length = $this->parseHeader($data);
				if($length > 0)
				{
					$incommingMessage = "";
					$i = 0;	
					do
					{
						$buff = socket_read($newsock, $this->chunk, PHP_BINARY_READ);
						$incommingMessage .= $buff;
						$i += $this->chunk;
					}
					while($i < $length);
					$this->processData($readSock, $incommingMessage);	
					echo "incommingMessage = $incommingMessage\r\n";
				}			
			}
		}
		while(true);
		socket_close($sock);
	}

	/**
	 * Load channel data from database
	 * @param String $channel Channel name
	 */
	public function loadFromDatabase($channel)
	{
		// Load from database
		$this->nexRecord = 0;
		return null;
	}

	/**
	 * Save data client data to database
	 * @param JSONObject $clientData Client data
	 */
	public function saveToDatabase($clientData)
	{
		// Save to database
	}

	/**
	 * Create log
	 * @param String $text Text to be logged
	 */
	protected function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}


?>