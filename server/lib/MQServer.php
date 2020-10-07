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

	public function __construct($port = 8887, $numberOfReceiver = 1, $userList = null, $userFromFile = false)
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
	
	public function reloadUser()
	{
		$this->loadUser($this->userList);
	}

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

	private function validateUser($username, $password)
	{
		return \HTPasswd::auth($username, $password, $this->users);
	}

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

	private function replyPing($newsock, $data)
	{
		$clientData = json_decode($data);
		$message = json_encode($clientData);
		socket_write($newsock, $message, strlen($message));
	}

	private function processData($readSock, $data)
	{
		$data = trim($data);
		if(!empty($data))
		{
			$clientData = json_decode($data);
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
						$message = $this->loadFromDatabase($clientData->channel);
						if($message !== null)
						{
							socket_write($readSock, $message, strlen($message));
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

	private function sendToReceivers($clientData)
	{
		if(count($this->receivers) == 0 && $this->keepData)
		{
			$this->saveToDatabase($clientData);
		}
		else
		{
			$message = json_encode(array("command"=>"message", "data"=>array($clientData->data)));
			$channel = isset($clientData->channel)?$clientData->channel:'generic';
			$count = 0;
			foreach ($this->receivers as $receiver) 
			{
				if($receiver->channel == $channel)
				{
					$count++;
					socket_write($receiver->socket, $message, strlen($message));
					if($count >= $this->numberOfReceiver && $this->numberOfReceiver > 0)
					{
					break;
					}
				}
			}			
		}
	}

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
	 * @return String eesage to be sent to the client or null if data not exists
	 */
	public function loadFromDatabase($channel)
	{
		// Load from database
		$this->nexRecord = 0;
		return null;
	}

	public function saveToDatabase($clientData)
	{
		// Save to database
	}

	protected function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}


?>