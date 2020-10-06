<?php
require_once dirname(__FILE__)."/HTPasswd.php";
class MQServer{
	public $showLog = false;
	private $port = 8889;
	private $clients = array();
	private $read = array();
	private $receivers = null;

	public function __construct($port = 8889, $user_list = null, $user_from_file = false)
	{
		$this->port = $port;
		$this->receivers = new \SplObjectStorage();
		if($user_list != null)
		{
			$this->user_list = $user_list;
			$this->user_from_file = $user_from_file;
			$this->load_user($this->user_list, $this->user_from_file);
		}
	}
	public function reload_user()
	{
		$this->load_user($this->user_list);
	}
	private function load_user($user_list, $user_from_file)
	{
		if(!$user_from_file)
		{
			$this->users = $user_list;
		}
		else
		{
			$file = $user_list;
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

	private function removeClients($read_sock)
	{
		// remove client for $this->clients array
		$key = array_search($read_sock, $this->clients);					
		// Remove client from receiver
		foreach($this->receivers as $i=>$j)
		{
			if($j->socket === $read_sock)
			{
				unset($this->receivers[$i]);
				break;
			}
		}
		unset($this->clients[$key]);
	}
	private function validate_user($username, $password)
	{
		return HTPasswd::auth($username, $password, $this->users);
	}
	private function authorization($data)
	{
		$client_data = json_decode($data);
		if(isset($client_data->command))
		{
			if($client_data->command == 'login')
			{
				$authorization = $client_data->authorization;
				$str = base64_decode($authorization);
				if(stripos($str, ":") !== false)
				{
					$arr = explode(":", $str, 2);
					$username = $arr[0];
					$password = $arr[1];
					return $this->validate_user($username, $password);
				}
			}
			if($client_data->command == 'reload-user')
			{
				$this->reload_user();
			}
		}
		return false;
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
					if(!$this->authorization($data))
					{
						$this->removeClients($newsock);
						continue;
					}
				}
			}		   
			foreach($this->read as $read_sock) 
			{
				$data = @socket_read($read_sock, 8192,  PHP_BINARY_READ);
				if ($data === false) 
				{
					$this->removeClients($read_sock);
					continue;
				}
				$this->processData($read_sock, $data);				
			}
		}
		while(true);
		socket_close($sock);
	}

	private function processData($read_sock, $data)
	{
		$data = trim($data);
		if(!empty($data))
		{
			$client_data = json_decode($data);
			if($client_data->type === "receiver")
			{
				$channel = isset($client_data->channel)?$client_data->channel:'generic';
				$id = isset($client_data->id)?$client_data->id:(uniqid().time(0));
				$client = new \stdClass();
				$client->socket = $read_sock;
				$client->channel = $channel;
				$client->index = $id;
				$this->receivers[$client_data->id] = $client; 
			}
			else if($client_data->type === "sender" && $client_data->command == "message")
			{
				$this->sendToReceivers($client_data);
			}
		}
	}

	private function sendToReceivers($client_data)
	{
		if(count($this->receivers) == 0)
		{
			$this->saveToDatabase($client_data);
		}
		else
		{
			$message = json_encode(array("command"=>"message", "data"=>array($client_data->data)));
			$channel = isset($client_data->channel)?$client_data->channel:'generic';
			foreach ($this->receivers as $receiver) 
			{
				if($receiver->channel == $channel)
				{
					socket_write($receiver->socket, $message, strlen($message));
				}
			}			
		}
	}

	private function saveToDatabase($client_data)
	{
		// Save to database
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