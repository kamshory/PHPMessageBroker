<?php

class MQServer{
	public $showLog = false;
	public $server = '127.0.0.1';
	public $port = 8889;
	public function __construct($server = "127.0.0.1", $port = 8889)
	{
		$this->server = $server;
		$this->port = $port;
	}
	public function processMessage($data)
	{
	}
	
	public function run()
	{

		$port = $this->port;
	   
		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	   
		socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
	   
		socket_bind($sock, 0, $port);
	   
		socket_listen($sock);

		$clients = array($sock);
		$receivers = array();
	   
		while (true) 
		{
			$read = $clients;
			if (socket_select($read, $write = NULL, $except = NULL, 0) < 1)
				continue;

			if (in_array($sock, $read)) {
				$clients[] = $newsock = socket_accept($sock);
			   
				$msg = json_encode(array("command"=>"connect", "response_code"=>"001"));
				socket_write($newsock, $msg, strlen($msg));
			   
				socket_getpeername($newsock, $ip);
				echo "New client connected: {$ip}\n";
			   
				// remove the listening socket from the clients-with-data array
				$key = array_search($sock, $read);
				unset($read[$key]);
			}
		   
			// loop through all the clients that have data to read from
			foreach ($read as $read_sock) 
			{
				// read until newline or 8192 bytes
				// socket_read while show errors when the client is disconnected, so silence the error messages
				$data = @socket_read($read_sock, 8192,  PHP_BINARY_READ);
				$client_data = json_decode($data);
				// check if the client is disconnected
				if ($data === false) 
				{
					// remove client for $clients array
					$key = array_search($read_sock, $clients);
					
					// Remove client from receiver
					foreach($receivers as $i=>$j)
					{
						if($j === $read_sock)
						{
							unset($receivers[$i]);
						}
					}
					unset($clients[$key]);
					echo "client disconnected.\n";
					// continue to the next client to read from, if any
					continue;
				}
				
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
						$receivers[$client_data->id] = $read_sock;
						$msg = json_encode(array("command"=>"connect", "response_code"=>"001"));
						echo "Send message $msg";
						socket_write($read_sock, $msg, strlen($msg));
					}
					else if($client_data->client_type === "sender" && $client_data->command == "message")
					{
						$message = json_encode(array("command"=>"message", "data"=>array($client_data->data)));
						if(count($receivers) == 0)
						{
						}
						else
						{
							foreach($receivers as $id=>$receiver)
							{
								echo "Send data to receiver $id $message \r\n";
								socket_write($receiver, $message, strlen($message));
							}
						}
					}
				}
			}
		}

		socket_close($sock);
	}
	public function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}
$server = new MQServer('127.0.0.1', 8889);
$server->run();
?>