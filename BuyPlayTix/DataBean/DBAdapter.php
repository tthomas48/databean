<?php

namespace BuyPlayTix\DataBean;

class DBAdapter implements IAdapter
{

  function load($databean, $param = "")
  {
    try {
      $db = DB::getInstance($databean->connectionString);

      $params = [];
      if (is_array($param)) {
        $databean->fields[$param[0]] = $param[1];
        $this->handleNullParam($params, $param[1]);
        $databean->setWhereClause('where ' . $param[0] . $this->handleNull($param[1]), $params);

      } elseif (strlen($param) > 0) {
        $databean->fields[$databean->getPk()] = $param;
        $this->handleNullParam($params, $param);
        $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($param), $params);
      } else {
        $uuid = UUID::get();
        $databean->fields[$databean->getPk()] = $uuid;
        $this->handleNullParam($params, $uuid);
        $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($uuid), $params);
        return;
      }
      $sql = "select * from " . $databean->getTable() . " " . $databean->getWhereClause();
      $sth = $db->prepare($sql);
      $db->execute($sth, $databean->getWhereParams());

      $params = [];
      $fields = $db->fetchAssocArray($sth);
      if ($fields !== false) {

        $databean->fields = $fields;
        $uuid = $fields[$databean->getPk()];
        $this->handleNullParam($params, $uuid);
        $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($uuid), $params);
        $databean->setNew(false);
      } else {
        // we need to make sure a UID is assigned if we don't return a record from the database
        $uuid = UUID::get();
        $databean->fields[$databean->getPk()] = $uuid;
        $this->handleNullParam($params, $uuid);
        $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($uuid), $params);
        $databean->setNew(true);
      }
    } catch (\Exception $e) {
      throw new Exception("Unable to instantiate databean " . __CLASS__ . ": " . $e);
    }
  }

  function hydrate($databean, $fields)
  {
    $databean->fields = $fields;
    $databean->setNew(false);
    $uuid = $fields[$databean->getPk()];
    $params = [];
    $this->handleNullParam($params, $uuid);
    $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($uuid), $params);
  }

  function duplicate($databean)
  {
    $db = DB::getInstance($databean->connectionString);

    $databean->setNew(true);
    $uuid = UUID::get();
    $databean->fields[$databean->getPk()] = $uuid;
    $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($uuid));
    return $databean;
  }

  function loadAll($databean, $field = "", $param = "", $andClause = "")
  {
    $sql = "";
    $params = [];
    try {
      $db = DB::getInstance($databean->connectionString);

      if (strlen($field) > 0) {
        if (is_array($param) && count($param) == 1) {
          $whereClause = ' where ' . $field . $this->handleNull($param[0]);
          $this->handleNullParam($params, $param[0]);
        } else {
          $valList = $this->_parseList($param);
          $params = $param;
          $whereClause = ' where ' . $field . ' in ' . $valList;
        }
      } elseif (is_array($param) && count($param) > 0) {
        if (is_array($param) && count($param) == 1) {
          $whereClause = ' where ' . $databean->getPk() . $this->handleNull($param[0]);
          $this->handleNullParam($params, $param[0]);
        } else {
          $valList = $this->_parseList($param);
          $params = $param;
          $whereClause = ' where ' . $databean->getPk() . ' in ' . $valList;
        }
      } else {
        $whereClause = "";
      }

      $andSql = $andClause;
      if (is_array($andClause) && count($andSql) == 2) {
        $andSql = $andClause[0];
        if (!is_array($andClause[1])) {
          $andClause[1] = [$andClause[1]];
        }
        $params = array_merge($params, $andClause[1]);
      }

      $sql = "select * from " . $databean->getTable() . " " . trim($whereClause) . " " . trim($andSql);
      $sth = $db->prepare(trim($sql));
      $sth->execute($params);
      $databeans = Array();
      while ($row = $db->fetchAssocArray($sth)) {
        $classname = get_class($databean);
        $d = new $classname();
        $this->hydrate($d, $row);
        $databeans[] = $d;
      }
      return $databeans;
    } catch (\Exception $e) {
      throw new Exception("Unable to return all " . $databean->getTable() . " databeans: " . $e->getMessage() . ": $sql");
    }
  }

  function update($databean)
  {
    try {
      $db = DB::getInstance($databean->connectionString);

      $fields = [];
      $replacements = [];
      $values = [];
      if ($databean->isNew()) {
        foreach ($databean->fields as $field => $value) {
          $fields[] = "`$field`";
          if ($value === null) {
            $replacements[] = "null";
          } else {
            $replacements[] = "?";
            $values[] = $value;
          }
        }

        $sql = "insert into " . $databean->getTable() . " " . "(" . implode($fields, ",") . ") " . "values (" . implode($replacements, ",") . ")";
        $databean->setNew(false);
      } else {
        foreach ($databean->fields as $field => $value) {
          if ($value === null) {
            $fields[] = "`$field`";
            $replacements[] = "null";
          } else {
            $fields[] = "`$field`";
            $replacements[] = "?";
            $values[] = $value;
          }
        }
        $valueList = [];
        for($i = 0; $i < count($fields); $i++) {
          $valueList[] = $fields[$i] . " = " . $replacements[$i];
        }
        $sql = "update " . $databean->getTable() . " set " . implode($valueList, ",") . " " . $databean->getWhereClause();
        $values = array_merge($values, $databean->getWhereParams());
      }
      return $db->executeSql($sql, $values);
    } catch (\Exception $e) {
      throw new Exception("Unable to update object: " . $e);
    }
  }

  function delete($databean)
  {
    try {
      $db = DB::getInstance($databean->connectionString);
      if (array_key_exists($databean->getPk(), $databean->fields) === false) {
        throw new Exception("no primary key specified for databean");
      }

      $sql = sprintf("delete from %s where %s = ?", $databean->getTable(), $databean->getPk());
      $sth = $db->prepare($sql);
      return $db->execute($sth, $databean->fields[$databean->getPk()]);
    } catch (\Exception $e) {
      throw new Exception("Unable to delete object: " . $e);
    }
  }

  private function _parseList($params = Array())
  {
    $db = DB::getInstance();

    $quotedParams = [];
    foreach ($params as $param) {
      $quotedParams[] = '?';
    }
    return "(" . implode(",", $quotedParams) . ")";
  }

  function raw_delete($table, $fields = array())
  {
    $db = DB::getInstance();

    $where_clause = array();
    $values = array();
    foreach ($fields as $name => $v) {
      $where_clause[] = $this->getWhereClause($name, $v);
      $this->getWhereValue($values, $v);
    }

    $sql = "delete
    from " . $table . "
    where " . implode(" AND ", $where_clause);

    $sth = $db->prepare($sql);
    return $db->execute($sth, $values);
  }

  function raw_insert($table, $insert_fields = array())
  {

    $this->_raw_insert("INSERT", $table, $insert_fields);
  }

  function raw_replace($table, $insert_fields = array())
  {

    $this->_raw_insert("REPLACE", $table, $insert_fields);
  }


  private function _raw_insert($verb, $table, $insert_fields = [])
  {
    $db = DB::getInstance();

    $fields = array();
    $values = array();
    foreach ($insert_fields as $name => $value) {
      $fields[] = $name;
      $placeholders[] = "?";
      $values[] = $value;
    }

    $sql = $verb . "
    into " . $table . "
    (" . implode(',', $fields) . ") values (" . implode(',', $placeholders) . ")";
    $sth = $db->prepare($sql);
    return $db->execute($sth, $values);
  }

  function raw_update($table, $fields = array(), $where_fields = array())
  {
    $db = DB::getInstance();

    $values = array();
    $set_clauses = array();
    foreach ($fields as $name => $value) {
      $set_clause[] = $name . " = ? ";
      $values[] = $value;
    }
    $where_clause = [];
    foreach ($where_fields as $name => $v) {
      $where_clause[] = $this->getWhereClause($name, $v);
      $this->getWhereValue($values, $v);
    }

    $sql = "update " . $table . " set " . implode(",", $set_clause) . " where " . implode(" AND ", $where_clause);
    $sth = $db->prepare($sql);
    return $db->execute($sth, $values);
  }

  private function getWhereClause($name, $v)
  {
    $condition = '=';
    $value = $v;
    $static = false;
    if (is_array($v)) {
      if (array_key_exists('condition', $v)) {
        $condition = $v['condition'];
      }
      if (array_key_exists('value', $v)) {
        $value = $v['value'];
      }
      if (array_key_exists('static', $v)) {
        $static = $v['static'];
      }
      if ($condition === 'in') {
        return $name . " in " . $this->_parseList($value) . " ";
      } else {
        return $name . " $condition " . ($static ? ' ' . $value . ' ' : ' ? ');
      }
    }
    return $name . " $condition " . ($static ? ' ' . $value . ' ' : ' ? ');
  }

  private function getWhereValue(&$values, $v)
  {
    $condition = '=';
    $value = $v;
    $static = false;
    if (is_array($v)) {
      if (array_key_exists('condition', $v)) {
        $condition = $v['condition'];
      }
      if (array_key_exists('value', $v)) {
        $value = $v['value'];
      }
      if (array_key_exists('static', $v)) {
        $static = $v['static'];
      }
      if ($condition === 'in') {
        // skip it
      } else {
        if (!$static) {
          $values[] = $value;
        }
      }
    } else {
      if (!$static) {
        $values[] = $value;
      }
    }
  }

  function raw_select(
    $table,
    $fields = array(),
    $where_fields = array(),
    $cast_class = null,
    $order = array(),
    $group = array()
  ) {
    $db = DB::getInstance();

    $values = array();
    $where_clause = array();
    foreach ($where_fields as $name => $v) {
      $where_clause[] = $this->getWhereClause($name, $v);
      $this->getWhereValue($values, $v);
    }
    $formatted_fields = array();
    foreach ($fields as $field) {
      if (is_array($field)) {
        $field_name = $field['name'];
        $field_alias = $field['alias'];
        switch ($field['aggregation']) {
          case 'count':
            $formatted_fields[] = "count(" . $field_name . ")" . (empty($field_alias) ? '' : ' ' . $field_alias);
            break;
          case 'sum':
            $formatted_fields[] = "sum(" . $field_name . ")" . (empty($field_alias) ? '' : ' ' . $field_alias);
            break;
          default:
            throw new \Exception("Unknown aggregation type for field $field_name.");
        }
      } else {
        $formatted_fields[] = $field;
      }
    }
    $sql = "SELECT " . implode(",",
        $formatted_fields) . " FROM " . $table . (count($where_clause) ? " WHERE " . implode(" AND ",
          $where_clause) : '') . (count($order) ? ' ORDER BY ' . implode(",",
          $order) : '') . (count($group) ? ' GROUP BY ' . implode(",", $group) : '');

    $sth = $db->prepare($sql);
    $result = $db->execute($sth, $values);

    $results = array();
    if ($cast_class != null) {
      while ($row = $db->fetchArray($sth)) {
        $results[] = new $cast_class($row[0]);
      }
    } else {
      while ($row = $db->fetchAssocArray($sth)) {
        $results[] = $row;
      }
    }
    return $results;
  }

  private static $statement_cache = array();

  function named_query($name, $sql = "", $params = array(), $hash = true)
  {
    $db = DB::getInstance();

    $sth = null;
    if (isset($statement_cache[$name])) {
      $sth = $statement_cache[$name];
    } else {
      $sth = $db->prepare($sql);
      $statement_cache[$name] = $sth;
    }
    $result = $db->execute($sth, $params);

    $fetchFunc = "fetchAssocArray";
    if (!$hash) {
      $fetchFunc = "fetchArray";
    }
    $rows = array();
    while ($row = $db->$fetchFunc($sth)) {
      $rows[] = $row;
    }
    return $rows;
  }

  private function handleNull($param)
  {
    $db = DB::getInstance();
    if ($param === null) {
      return ' is null ';
    }
    return ' = ?';
  }
  private function handleNullParam(&$params, $param)
  {
    if ($param === null) {
      return;
    }
    $params[] = $param;
  }
}
