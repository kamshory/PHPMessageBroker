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
    public function __construct($port = 8887, $numberOfReceiver = 1, $userList = null, $userFromFile = false, 
        $keepData = false, $dbHost = null, $dbPort = null, $dbName = null, $dbUser = null, $dbPass = null)
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
            $url = "mysql:host=".$this->dbHost.";port=" . $this->dbPort . ";dbname=" . $this->dbName;
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

                $sql = "select * from data where channel = '$channel' order by data_id asc limit 0, ".$this->recordLimit;
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
$server = new Server($port, 0, dirname(__FILE__)."/.htpasswd", true, true, "localhost", 3306, "message_broker", "root", "alto1234");
$server->showLog = true;
$server->run();
?>