<?php
namespace BuyPlayTix\DataBean;
class DBAdapter implements IAdapter {

  function load($databean, $param = "") {
    try {
      $db = BuyPlayTix_Db_Database::getInstance($databean->connectionString);

      if(is_array($param))
      {
        $databean->fields[$param[0]] = $param[1];
        $databean->setWhereClause('where ' . $param[0] . ' = ' . $db->quote($param[1]));
      }
      elseif (strlen($param) > 0)
      {
        $databean->fields[$databean->getPk()] = $param;
        $databean->setWhereClause('where ' . $databean->getPk() . ' = ' . $db->quote($param));
      }
      else
      {
        $uuid = BuyPlayTix_UUID::get();
        $databean->fields[$databean->getPk()] = $uuid;
        $databean->setWhereClause('where ' . $databean->getPk() . ' = ' . $db->quote($uuid));
        return;
      }
      $sql = "select *
      from " . $databean->getTable() . " " . $databean->getWhereClause();
      $sth = $db->query($sql);

      if($db->numRows($sth) > 0) {
        $databean->fields = $db->fetchAssocArray($sth);
        $databean->setNew(false);
      } else {
        // we need to make sure a UID is assigned if we don't return a record from the database
        $uuid = BuyPlayTix_UUID::get();
        $databean->fields[$databean->getPk()] = $uuid;
        $databean->setWhereClause('where ' . $databean->getPk() . ' = ' . $db->quote($uuid));
        $databean->setNew(true);
      }
    }
    catch (Exception $e) {
      throw new BuyPlayTix_Db_DatabaseException("Unable to instantiate databean " . __CLASS__ . ": " . $e);
    }
  }

  function duplicate($databean) {
    $db = BuyPlayTix_Db_Database::getInstance($databean->connectionString);

    $databean->setNew(true);
    $uuid = BuyPlayTix_UUID::get();
    $databean->fields[$databean->getPk()] = $uuid;
    $databean->setWhereClause('where ' . $databean->getPk() . ' = ' . $db->quote($uuid));
    return $databean;
  }

  function loadAll($databean, $field = "", $param = "", $andClause = "") {
    $sql = "";
    try {
      $db = BuyPlayTix_Db_Database::getInstance($databean->connectionString);

      if(strlen($field) > 0)
      {
        if(is_array($param) && count($param) == 1) {
          $whereClause = ' where ' . $field . ' = ' . $db->quote($param[0]);
        } else {
          $valList = $this->_parseList($param);
          $whereClause = ' where ' . $field . ' in ' . $valList;
        }
      }
      elseif(count($param) > 0 && is_array($param))
      {
        if(is_array($param) && count($param) == 1) {
          $whereClause = ' where ' . $databean->getPk() . ' = ' . $db->quote($param[0]);
        } else {
          $valList = $this->_parseList($param);
          $whereClause = ' where ' . $databean->getPk() . ' in ' . $valList;
        }
      }
      else
      {
        $whereClause = "";
      }

      $sql = "select " . $databean->getPk() . "
      from " . $databean->getTable() . " " . $whereClause . " " . $andClause;
      $result = $db->query($sql);
      $databeans = Array();
      while($row = $db->fetchArray($result))
      {
        $classname = get_class($databean);
        $databeans[] = new $classname ($row[0]);
      }
      return $databeans;
    }
    catch (Exception $e) {
      throw new BuyPlayTix_Db_DatabaseException("Unable to return all " . $databean->getTable() . " databeans: " . $e->getMessage() . ": $sql");
    }
  }

  function update($databean) {
    try {
      $db = BuyPlayTix_Db_Database::getInstance($databean->connectionString);


      $fieldList = "";
      $valueList = "";
      if($databean->isNew())
      {
        foreach ($databean->fields as $field => $value)
        {
          $fieldList .= "`$field`" . ",";
          $valueList .= $db->quote($value) . ",";
        }
        $fieldList = rtrim($fieldList,',');
        $valueList = rtrim($valueList,',');

        $sql = "insert into " . $databean->getTable() . " " .
            "(" . $fieldList . ") ".
            "values (" . $valueList . ")";
        $databean->setNew(false);
      }
      else
      {
        foreach ($databean->fields as $field => $value)
        {
          $valueList .= "`$field`" . " = " . $db->quote($value) . ",\n";
        }
        $valueList = rtrim($valueList,",\n");

        $sql = " update " . $databean->getTable() . " " .
            " set " . $valueList .
            " " . $databean->getWhereClause();
      }
      return $db->executeSql($sql);
    }
    catch (Exception $e) {
      throw new BuyPlayTix_Db_DatabaseException("Unable to update object: " . $e);
    }

  }
  function delete($databean) {
    try {
      $db = BuyPlayTix_Db_Database::getInstance($databean->connectionString);

      $sql = "delete
      from " . $databean->getTable() . "
      where " . $databean->getPk() . " = " . $db->quote($databean->fields[$databean->getPk()]);

      return $db->query($sql);
    }
    catch (Exception $e) {
      throw new BuyPlayTix_Db_DatabaseException("Unable to delete object: " . $e);
    }
  }

  private function _parseList($param= Array())
  {
    return "('" . implode("','",$param) . "')";
  }

  function raw_delete($table, $fields = array()) {

    $db = BuyPlayTix_Db_Database::getInstance();

    $restrictions = array();
    $values = array();
    foreach($fields as $name => $v) {
      $condition = '=';
      $value = $v;
      if(is_array($v)) {
        $condition = $v['condition'];
        $value = $v['value'];
      }
      $restrictions[] = $name . " $condition ? ";
      $values[] = $value;
    }


    $sql = "delete
    from " . $table . "
    where " . implode(" AND ", $restrictions);
    $sth = $db->prepare($sql);
    return $db->execute($sth, $values);
  }
  function raw_insert($table, $insert_fields = array()) {

    $db = BuyPlayTix_Db_Database::getInstance();

    $fields = array();
    $values = array();
    foreach($insert_fields as $name => $value) {
      $fields[] = $name;
      $values[] = $db->quote($value);
    }

    $sql = "insert
    into " . $table . "
    (" . implode(',', $fields) . ") values (" . implode(',', $values) . ")";
    return $db->query($sql);


  }
  function raw_update($table, $fields = array(), $where_fields = array()) {

    $db = BuyPlayTix_Db_Database::getInstance();

    $values = array();
    $set_clauses = array();
    foreach($fields as $name => $value) {
      $set_clause[] = $name . " = ? ";
      $values[] = $value;
    }
    $where_clause = "";
    foreach($where_fields as $name => $v) {
      $condition = '=';
      $value = $v;
      if(is_array($v)) {
        $condition = $v['condition'];
        $value = $v['value'];
      }
      $where_clause[] = $name . " $condition ? ";
      $values[] = $value;
    }

    $sql = "update " . $table . " set " . implode(",", $set_clause) .
    " where " . implode(" AND ", $where_clause);
    $sth = $db->prepare($sql);
    return $db->execute($sth, $values);


  }

  function raw_select($table, $fields = array(), $where_fields = array(), $cast_class = NULL) {

    $db = BuyPlayTix_Db_Database::getInstance();

    $values = array();
    $where_clause = "";
    foreach($where_fields as $name => $v) {
      $condition = '=';
      $value = $v;
      if(is_array($v)) {
        $condition = $v['condition'];
        $value = $v['value'];
      }
      $where_clause[] = $name . " $condition ? ";
      $values[] = $value;
    }
    $sql = "SELECT " . implode(",", $fields) . " FROM " . $table . " WHERE " . implode(" AND ", $where_clause);
    $sth = $db->prepare($sql);
    $result = $db->execute($sth, $values);

    $results = array();
    if($cast_class != NULL) {
      while($row = $db->fetchArray($sth))
      {
        $results[] = new $cast_class($row[0]);
      }
    } else {
      while($row = $db->fetchAssocArray($sth))
      {
        $results[] = $row;
      }
    }
    return $results;
  }

  private static $statement_cache = array();
  function named_query($name, $sql = "", $params = array(), $hash = true) {
    $db = BuyPlayTix_Db_Database::getInstance();

    $sth = NULL;
    if(isset($statement_cache[$name])) {
      $sth = $statement_cache[$name];
    } else {
      $sth = $db->prepare($sql);
      $statement_cache[$name] = $sth;
    }
    $result = $db->execute($sth, $params);

    $fetchFunc = "fetchAssocArray";
    if(!$hash) {
      $fetchFunc = "fetchArray";
    }
    $rows = array();
    while($row = $db->$fetchFunc($sth)) {
      $rows[] = $row;
    }
    return $rows;
  }
}
