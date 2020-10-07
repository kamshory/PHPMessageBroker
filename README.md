# PHPMessageBroker

# Introduction

Sometime, you needed a very lightweight message broker that would run on a system with very minimum specifications. On the other hand, your server is ready with the PHP runtime.

PHPMessageBroker is 100% PHP. You can use MariaDB or MySQL database to ensure that message received to the receiver. However, you can use another DBMS by modifying a little of the sorce code.

Using a very light library is your choice because you don't want to sacrifice enormous resources for a very simple task.

PHPMessageBroker is one of your choices. With a very easy installation and only using two server-side files, you can create a message broker that can forward messages from one client to another.

# Topolgy

![Topology] (https://raw.githubusercontent.com/kamshory/PHPMessageBroker/main/topology.png)

# User Credentials

To generate user password, use tool like https://www.htaccesstools.com/htpasswd-generator/

Supported Algorithm:

1. SHA
2. APR1

# Database

If you want to keep data to a database to ensure that message received to the receiver, database structure shown below:

```sql
CREATE TABLE IF NOT EXISTS `data` (
  `data_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `channel` varchar(100) DEFAULT NULL,
  `data` longtext,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`data_id`),
  KEY `channel` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
```

# Example 

**Server Without Database**
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

**Server With Database**

```php
<?php
function error_handler($errno, $errstr, $errfile, $errline)
{
}
$old_error_handler = set_error_handler("error_handler");

require_once dirname(__FILE__)."/lib/MQServer.php";

class Server extends MQServer{

    private $database = null;
    private $dbHost = null;
    private $dbPort = null;
    private $dbName = null;
    private $dbUser = null;
    private $dbPass = null;
    private $recordLimit = 5;
	public function __construct($port = 8887, $numberOfReceiver = 0, $userList = null, 
		$userFromFile = false, $keepData = false, $dbHost = null, $dbPort = null, 
		$dbName = null, $dbUser = null, $dbPass = null)
    {
        parent::__construct($port, $numberOfReceiver, $userList, $userFromFile);
        if($keepData)
        {
            $this->keepData = $keepData;
            $this->dbHost = $dbHost;
            $this->dbPort = $dbPort;
            $this->dbName = $dbName;
            $this->dbUser = $dbUser;
            $this->dbPass = $dbPass;
            $this->initDatabase();
        }
    }

    private function initDatabase()
    {
        // Init database here
        try
        {
            $url = "mysql:host=".$this->dbHost.";port=".$this->dbPort.";dbname=".$this->dbName;
            $this->database = new PDO($url, $this->dbUser, $this->dbPass);
        }
        catch(PDOException $e)
        {
            $this->keepData = false;
            $this->log("Can not connect to database. Host : " . DB_HOST);
        }
    }

    /**
	 * Load channel data from database
	 * @return String eesage to be sent to the client or null if data not exists
	 */
    public function loadFromDatabase($channel)
    {
        try
        {
            $channel = addslashes($channel);
            $sql = "select * from data where channel = '$channel' ";
            $db_rs = $this->database->prepare($sql);
            $db_rs->execute();
            $rowCount = $db_rs->rowCount();
            if($rowCount > 0)
            {
                $num = $rowCount - $this->recordLimit;
                if($num < 0)
                {
                    $num = 0;
                }
                $this->nextRecord = $num;

                $sql = "select * from data where channel = '$channel' limit 0, ".$this->recordLimit;
                $db_rs = $this->database->prepare($sql);
                $db_rs->execute();
                
                $rows = $db_rs->fetchAll(PDO::FETCH_ASSOC);
                $data = array();
                $dataIDs = array();
                foreach($rows as $row)
                {
                    $data[] = json_decode($row['data']);
                    $dataIDs[] = $row['data_id'];
                }
                if(!empty($dataIDs))
                {
                    $sql = "delete from data where data_id in(".implode(", ", $dataIDs).")";
                    $db_rs = $this->database->prepare($sql);
                    $db_rs->execute();
                }
                return json_encode(array("command"=>"message", "data"=>$data));
            }
            else
            {
                return null;
            }
        }
        catch(Exception $e)
        {
            $this->initDatabase();
            return null;
        }
 	}

	public function saveToDatabase($clientData)
	{
        try
        {
            $channel = addslashes($clientData->channel);
            $data = addslashes(json_encode($clientData->data));
            $sql = "insert into data(channel, data, created) values ('$channel', '$data', now())";
            $db_rs = $this->database->prepare($sql);
            $db_rs->execute();    
        }
        catch(Exception $e)
        {
            $this->initDatabase();
        }
	}
}

$port = 8887;
$server = new Server($port, 0, dirname(__FILE__)."/.htpasswd", true, 
	true, "localhost", 3306, "message_broker", "root", "alto1234");
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
$address = "127.0.0.1";
$port = 8887;
$username = 'manager';
$password = 'Albasiko2020^';
$sender = new MQSender($address, $port, $username, $password);

$data = array(
	'id'=>uniqid(),
	'time' => time(0),
	'receiver'=>'+6281200000000',
	'message'=>"Kode OTP Anda adalah ".mt_rand(100000, 999999)
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

$address = "127.0.0.1";
$port = 8887;
$username = 'manager';
$password = 'Albasiko2020^';
$channel = 'sms';
$receiver = new Receiver($address, $port, $username, $password, $channel);
$receiver->showLog = false;
$receiver->run();
?>
```
