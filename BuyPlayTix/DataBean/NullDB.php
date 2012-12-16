<?php
namespace BuyPlayTix\DataBean;
class NullDB {
  /**
   * contains the log to use for logging
   * @var object
   * @see Database()
   */
  private $log;

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
  }
  public static function setLogger($logger) {
  }


  public static function getInstance($class = "deprecated") {
    if(!isset(\BuyPlayTix_Db_Database::$instance)) {
      \BuyPlayTix_Db_Database::$instance = new NullDatabase();
    }
    return \BuyPlayTix_Db_Database::$instance;
  }
  public static function setInstance($instance) {
    \BuyPlayTix_Db_Database::$instance = $instance;
  }
  function connect()
  {
  }
  function query($sql)
  {
  }
  function numRows($result)
  {
  }
  function fetchArray($result)
  {
  }
  function fetchAssocArray($result)
  {
  }
  function fetchObject($result)
  {
  }
  function quote($string)
  {
  }
  function prepare($sql)
  {
  }
  function execute($sth, $values = Array())
  {
  }
  function executeSql($sql, $values = Array())
  {
  }
  function querySql($sql, $values = Array())
  {
  }
  function beginTransaction() {
  }
  function autoCommit($autoCommit)
  {
  }
  function commit()
  {
  }
  function rollback()
  {
  }
  function lastInsertId()
  {
  }
}
