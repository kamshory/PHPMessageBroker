# PHPMessageBroker

# Introduction

Once upon a time, you needed a very lightweight message broker that would run on a system with very minimum specifications. On the other hand, your server is ready with the PHP runtime.

Using a very light library is your choice because you don't want to sacrifice enormous resources for a very simple task.

PHPMessageBroker is one of your choices. With a very easy installation and only using two server-side files, you can create a message broker that can forward messages from one client to another.

# Password Generation

To generate user password, use tool like https://www.htaccesstools.com/htpasswd-generator/

Supported Algorithm:

1. SHA
2. APR1

# Example 

**Server**
```php
<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQServer.php";

$port = 8887;
$server = new MQServer($port, dirname(__FILE__)."/.htpasswd", true);
$server->showLog = false;
$server->run();
?>
```

**Sender**
```php
<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQSender.php";
$address = "domain.tld";
$port = 8887;
$username = 'manager';
$password = 'Albasiko2020^';
$sender = new MQSender($address, $port, $username, $password);

$data = array(
	'id'=>uniqid(),
	'time' => time(0),
	'receiver'=>'+6281200000000',
	'message'=>"Kode OTP Anda adalah "
	.mt_rand(100000, 999999)
	."\r\n>>>Jangan memberitahukan kode ini kepada siapapun<<<"
);

$channel = 'sms';
$sender->showLog = false;
$sender->send($data, $channel);
?>
```

**Receiver**
```php
<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQReceiver.php";
class Receiver extends MQReceiver{
	public function processMessage($message)
	{
		$object = json_decode($message);
		$rows = $object->data;
		foreach($rows as $idx=>$data)
		{
			echo "Time     : ".date('j F Y H:i:s', $data->time)
			."\r\nReceiver : ".$data->receiver
			."\r\nMessage  : ".$data->message."\r\n\r\n";
		}
	}
}

$address = "domain.tld";
$port = 8887;
$username = 'manager';
$password = 'Albasiko2020^';
$channel = 'sms';
$receiver = new Receiver($address, $port, $username, $password, $channel);
$receiver->showLog = false;
$receiver->run();
?>
```
