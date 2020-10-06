<?php

class MQServer{
	public $showLog = false;
	public $port = 8889;
	private $clients = array();
	private $read = array();
	private $receivers = null;

	public function __construct($port = 8889)
	{
		$this->port = $port;
		$this->receivers = new \SplObjectStorage();
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
			if (in_array($sock, $this->read)) 
			{
				$this->clients[] = $newsock = socket_accept($sock);
				socket_getpeername($newsock, $ip);
				$key = array_search($sock, $this->read);
				unset($this->read[$key]);
			}		   
			foreach ($this->read as $read_sock) 
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

	public function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}

$server = new MQServer(8887);
$server->run();
?>