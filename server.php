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
			   
				$msg = json_encode(array("command"=>"connect", "response_code"=>"001"));
				socket_write($newsock, $msg, strlen($msg));
			   
				socket_getpeername($newsock, $ip);
			   
				// remove the listening socket from the clients-with-data array
				$key = array_search($sock, $this->read);
				unset($this->read[$key]);
			}
		   
			// loop through all the clients that have data to read from
			foreach ($this->read as $read_sock) 
			{
				// read until newline or 8192 bytes
				// socket_read while show errors when the client is disconnected, so silence the error messages
				$data = @socket_read($read_sock, 8192,  PHP_BINARY_READ);
				// check if the client is disconnected
				if ($data === false) 
				{
					$this->removeClients($read_sock);
					// continue to the next client to read from, if any
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
			if($client_data->client_type === "admin")
			{
				echo "Admin connection\n";
			}
			else if($client_data->client_type === "receiver")
			{
				$channel = isset($client_data->channel)?$client_data->channel:'generic';
				$id = isset($client_data->id)?$client_data->id:(uniqid().time(0));

				$client = new \stdClass();
				$client->socket = $read_sock;
				$client->channel = $channel;
				$client->index = $id;
				$this->receivers[$client_data->id] = $client; 
				$msg = json_encode(array("command"=>"connect", "response_code"=>"001"));
				socket_write($read_sock, $msg, strlen($msg));
			}
			else if($client_data->client_type === "sender" && $client_data->command == "message")
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