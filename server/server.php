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
    public function __construct($port = 8887, $userList = null, $userFromFile = false, $keepData = false, $dbHost = null, $dbPort = null, $dbName = null, $dbUser = null, $dbPass = null)
    {
        parent::__construct($port, $userList, $userFromFile);
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
            $this->database = new PDO("mysql:host=".$this->dbHost.";port=" . $this->dbPort . ";dbname=" . $this->dbName, $this->dbUser, $this->dbPass);
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
            $num = $db_rs->rowCount() - $this->recordLimit;
            if($num < 0)
            {
                $num = 0;
            }
            $this->nexRecord = $num;

            $sql = "select * from data where channel = '$channel' limit 0, ".$this->recordLimit;
            $db_rs = $this->database->prepare($sql);
            $db_rs->execute();
            
            $rows = $db_rs->fetchAll(PDO::FETCH_ASSOC);
            $data = array();
            foreach($rows as $row)
            {
                $data[] = $row['data'];
            }
            return json_encode(array("command"=>"message", "data"=>$data));
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
$server = new Server($port, dirname(__FILE__)."/.htpasswd");
$server->showLog = false;
$server->run();
?>