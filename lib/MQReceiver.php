<?php

class MQReceiver{
	public $showLog = false;
	public $server = '127.0.0.1';
	public $port = 8889;
	public $channel = 'generic';
	public function __construct($server = "127.0.0.1", $port = 8889, $channel = 'channel')
	{
		$this->server = $server;
		$this->port = $port;
		$this->channel = $channel;
	}
	
	public function processMessage($data)
	{
		// define here
	}

	public function run()
	{
		set_time_limit(0);
		do
		{
			$sock = null;
			try
			{
				$errorcode = "";
				if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					$this->log("Couldn't create socket: [$errorcode] $errormsg \n");
					continue;
				}
				$errorcode = '';

				if(!socket_connect($sock, $this->server, $this->port)) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);

					$this->log("Could not connect: [$errorcode] $errormsg \n");
					continue;
				}
				if(!$errorcode)
				{
					$this->log("Connection established \n");
					$message = json_encode(array(
						'command' => 'connect',
						'type' => 'receiver', 
						'id' => uniqid().time(0),
						'channel'=>$this->channel,
						'data' => array(
							'id'=>uniqid().time(0),
							'time' => gmdate('Y-m-d H:i:s')
						)
					));
					if(!socket_send($sock, $message, strlen($message), 0)) {
						$errorcode = socket_last_error();
						$errormsg = socket_strerror($errorcode);
						$this->log("Could not send data: [$errorcode] $errormsg \n");
						continue;
					}
					do
					{
						$data = @socket_read($sock, 8192,  PHP_BINARY_READ);
						if($data === false)
						{
							continue 2;
						}
						if($data !== null)
						{
							if($errorcode)
							{
								$this->log("Could not read data: [$errorcode] $errormsg \n");
							}
							$this->processMessage($data);
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
	public function log($text)
	{
		if($this->showLog)
		{
			echo $text;
		}
	}
}


?>
