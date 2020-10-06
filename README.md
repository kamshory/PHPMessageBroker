# PHPMessageBroker

# Introduction


Once upon a time, you needed a very lightweight message broker that would run on a system with very minimum specifications. On the other hand, your server is ready with the PHP runtime.

Using a very light library is your choice because you don't want to sacrifice enormous resources for a very simple task.

PHPMessageBroker is one of your choices. With a very easy installation and only using two server-side files, you can create a message broker that can forward messages from one client to another.

## Message Specification

**Receiver**

```json
{
	"command":"connect",
	"id":"123456",
	"type":"receiver",
	"channel":"sms",
	"data":[
	]
}
```

**Sender**
```json
{
	"command":"message",
	"id":"123456",
	"type":"sender",
	"channel":"sms",
	"data":[
	{
		"id":"123",
		"time":1601973328,
		"message":"Your OTP is 876543",
		"receiver":"+6281200000000"
	}
	]
}
```

1. **command**
Command 
2. **id**
Client ID
3. **type**
Client type, "sender" or "receiver"
4. **channel**
Channel of the message. User can make many channel and server will only send the message to the same channel
5. **data**
Data to send

# Example 

**Server**
```php
<?php
require_once dirname(__FILE__)."/lib/MQServer.php";
$server = new MQServer(8887);
$server->run();
?>
```

**Sender**
```php
<?php
require_once dirname(__FILE__)."/lib/MQSender.php";
$sender = new MQSender('127.0.0.1', 8887);
$message = json_encode(array(
	'id' => uniqid().time(),
	'command' => 'message',
	'type' => 'sender', 
	'channel'=>'sms',
	'data' => array(
		'id'=>uniqid(),
		'time' => time(0),
		'receiver'=>'+6281200000000',
		'message'=>"Kode OTP Anda adalah "
		.mt_rand(100000, 999999)
		."\r\n>>>Jangan memberitahukan kode ini kepada siapapun<<<"
	)
));
$sender->showLog = false;
$sender->send($message);
?>
```

**Receiver**
```php
<?php
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

$receiver = new Receiver("127.0.0.1", 8887, 'sms');
$receiver->run();
?>
```
