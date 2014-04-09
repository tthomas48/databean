<?php
namespace BuyPlayTix\DataBean;
class DB {
  /**
   * contains the log to use for logging
   * @var object
   * @see DB()
   */
  public static $log;

  public static $instance;

  private $database = false;

  private $commit_depth;

  private static $user;
  private static $pass;
  private static $name;
  private static $host;
  private static $dsn;


  /**
   * Creates a new database instance
   *
   * A new database instance is created.
   *
   * @param string        $class   db class to connect to
   * @return reference to object
   * @access public
   * @see $class
   */
  private function __construct()
  {
    $this->connect();
  }
  public static function init($options) {
    DB::$user = $options["user"];
    DB::$pass = $options["pass"];
    DB::$name = $options["name"];
    DB::$host = $options["host"];
    if(array_key_exists("dsn", $options)) {
      DB::$dsn = $options["dsn"];
    }
  }
  public static function setLogger(IDBLogger $logger) {
    DB::$log = $logger;
  }
  public static function getInstance($class = "deprecated") {
    if(!isset(DB::$instance)) {
      DB::$instance = new DB();
    }
    return DB::$instance;
  }
  public static function setInstance($instance) {
    DB::$instance = $instance;
  }
  function connect()
  {
    if(empty(DB::$dsn)) {
      DB::$dsn = 'mysql:dbname=' . DB::$name . ";host=" . DB::$host;
    }
    $this->database = new \PDO(DB::$dsn, DB::$user, DB::$pass, array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'utf8\''));
    $this->database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $this->database->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); 
    $this->database->setAttribute(\PDO::ATTR_PERSISTENT, true); 
    $this->database->exec("set names 'utf8'");
    if(DB::$log == null) {
      DB::$log = new NullLogger();
    }
    register_shutdown_function(array('BuyPlayTix\DataBean\DB', 'shutdown'));
  }
  public function disconnect() {
    $this->database = null;
    DB::$instance = null;
  }
  public static function shutdown() {
    try {
      if(DB::$instance->database->inTransaction()) {
        DB::$instance->database->rollback();
      }
    } catch(\Exception $e) {
      DB::$log->error($e);
    }
  }
  public function setTimeZone($tz) {
  	// set timezone to UTC
  	$this->database->exec("set time_zone = '$tz'");
  }
  function query($sql)
  {
    return $this->database->query($sql);
  }
  function numRows($result)
  {
    return $result->rowCount();
  }
  function fetchArray($result)
  {
    return $result->fetch(\PDO::FETCH_NUM);
  }
  function fetchAssocArray($result)
  {
    return $result->fetch(\PDO::FETCH_ASSOC);
  }
  function fetchObject($result)
  {
    return $result->fetch(\PDO::FETCH_OBJ);
  }
  function quote($string)
  {
    return $this->database->quote($string);
  }
  function prepare($sql)
  {
    if(DB::$log != NULL) {
      DB::$log->debug($sql);
    }
    return $this->database->prepare($sql);
  }
  function execute($sth, $values = Array())
  {
    if(DB::$log != NULL) {
      DB::$log->debug($values);
    }
    return $sth->execute($values);
  }
  function executeSql($sql, $values = Array())
  {
    if(DB::$log != NULL) {
      DB::$log->debug($values);
    }
    $sth = $this->prepare($sql);
    return $sth->execute($values);
  }
  function querySql($sql, $values = Array())
  {
    if(DB::$log != NULL) {
      DB::$log->debug($values);
    }
    $sth = $this->prepare($sql);
    $sth->execute($values);
    return $sth->fetchAll();
  }
  function beginTransaction() {
    if($this->database->inTransaction()) {
      $this->commit_depth++;
      return true;
    }
    $this->commit_depth = 1;
    return $this->database->beginTransaction();
  }
  function autoCommit($autoCommit)
  {
    $this->commit();
    if(!$autoCommit) {
      return $this->database->beginTransaction();
    }
  }
  function commit()
  {
    if($this->commit_depth > 1) {
      $this->commit_depth--;
      return true;
    }
    return $this->database->commit();
  }
  function rollback()
  {
    if($this->commit_depth > 1) {
      $this->commit_depth--;
      return true;
    }
    return $this->database->rollback();
  }
  function lastInsertId()
  {
    return $this->database->lastInsertId();
  }
}
