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
		socket_write($newsock, $responseMessage, strlen($responseMessage));
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
							socket_write($readSock, $responseMessage, strlen($responseMessage));
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
					socket_write($receiver->socket, $responseMessage, strlen($responseMessage));
					if($this->numberOfReceiver > 0 && $count >= $this->numberOfReceiver)
					{
					break;
					}
				}
			}			
		}
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
			if (socket_select($this->read, $write = NULL, $except = NULL, 0) < 1)
			{
				continue;
			}
			if(in_array($sock, $this->read)) 
			{
				$this->clients[] = $newsock = socket_accept($sock);
				socket_getpeername($newsock, $ip);
				$key = array_search($sock, $this->read);
				unset($this->read[$key]);
				$data = @socket_read($newsock, 8192,  PHP_BINARY_READ);
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
						$clientData = json_decode($data);
						$this->log("Receipt new connection...\r\n");
						if(!$this->authorization($newsock, $clientData))
						{
							$this->removeClients($newsock);
							continue;
						}
					}
				}
			}		   
			foreach($this->read as $readSock) 
			{
				$data = @socket_read($readSock, 8192,  PHP_BINARY_READ);
				if ($data === false) 
				{
					$this->removeClients($readSock);
					continue;
				}
				$this->processData($readSock, $data);				
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