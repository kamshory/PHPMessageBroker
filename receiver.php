<?php

class MQReceiver{
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
		set_time_limit(0);
		$server = $this->server;
		$port = $this->port;
		do{
			$sock = null;
			try
			{
				$errorcode = "";
				if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);

					$this->log("Couldn't create socket: [$errorcode] $errormsg \n");
					sleep(1);
					continue;

				}
				$errorcode = '';

				if(!socket_connect($sock, $server, $port)) {
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
						'client_type' => 'receiver', 
						'id' => uniqid().time(),
						'data' => array(
							'id'=>uniqid().time(),
							'date_time' => gmdate('Y-m-d H:i:s')
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
class Receiver extends MQReceiver{
	public function __construct($server = "127.0.0.1", $port = 8889)
	{
		$this->server = $server;
		$this->port = $port;
	}
	public function processMessage($data)
	{
		echo "\r\nReceiver.processMessage\r\n";
		print_r($data);
		echo "\r\n\r\n";
	}
}

function exception_error_handler($errno, $errstr, $errfile, $errline )
{
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}


function catchException($e)
{
    if (error_reporting() === 0)
    {
        return;
    }

    // Do some stuff
}

set_error_handler("exception_error_handler");
set_exception_handler('catchException');

$receiver = new Receiver("127.0.0.1", 8889);
$receiver->run();
?>
