<?php
namespace BuyPlayTix\DataBean;
class ObjectAdapter implements IAdapter {

  private $beans = array();
  private $tables = array();

  public function __construct() {
  }

  function load($databean, $param = "") {

    if(!isset($this->beans[get_class($databean)])) {
      $this->beans[get_class($databean)] = array();
    }
    $b = $this->beans[get_class($databean)];

    if(is_array($param))
    {
      foreach($b as $bean) {
        if($bean->$param[0] == $param[1]) {
          $databean->fields = $bean->fields;
          $databean->setNew(false);
          return $databean;
        }
      }
    }
    elseif (strlen($param) > 0)
    {
      $pk = $databean->getPk();
      foreach($b as $bean) {
        if($bean->$pk == $param[1]) {
          $databean->fields = $bean->fields;
          $databean->setNew(false);
          return $databean;
        }
      }
    }
    $uuid = UUID::get();
    $databean->fields[$databean->getPk()] = $uuid;
    $databean->setNew(true);
    return $databean;
  }
  function loadAll($databean, $field = "", $param = "", $andClause = "") {

    if(strlen($field) > 0)
    {
      if(is_array($param) && count($param) == 1) {
        $whereClause = ' where ' . $field . ' = ' . $param[0];
      } else {
        $valList = $this->_parseList($param);
        $whereClause = ' where ' . $field . ' in ' . $valList;
      }
    }
    elseif(count($param) > 0 && is_array($param))
    {
      if(is_array($param) && count($param) == 1) {
        $whereClause = ' where ' . $databean->getPk() . ' = ' . $param[0];
      } else {
        $valList = $this->_parseList($param);
        $whereClause = ' where ' . $databean->getPk() . ' in ' . $valList;
      }
    }
    else
    {
      $whereClause = "";
    }

    $group_by = "";
    $order_by = "";
    $where = array();

    $clause = $whereClause . " " . $andClause;
    $chunks = preg_split("/\s*GROUP\s+BY\s*/i", $clause);
    if(count($chunks) == 2) {
      $clause = $chunks[0];
      $group_by = $chunks[1];

    }
    $chunks = preg_split("/\s*ORDER\s+BY\s*/i", $clause);
    if(count($chunks) == 2) {
      $clause = $chunks[0];
      $order_by = $chunks[1];
    }
    $clause = preg_replace("/\s*WHERE\s*/i", "", $clause);
    $chunks = preg_split("/\s*AND\s*/i", $clause);
    foreach($chunks as $chunk) {
      $where_chunks = preg_split("/\s*=\s*/i", $chunk);
      if(count($where_chunks) == 2) {
        $field = strtoupper(trim($where_chunks[0]));
        $value = trim($where_chunks[1], ' \'');
        $where[$field] = $value;
      }
      $where_chunks = preg_split("/\s*in\s*/i", $chunk);
      if(count($where_chunks) == 2) {
      	$field = strtoupper(trim($where_chunks[0]));
      	$value = str_replace(')', '', str_replace('(', '', $where_chunks[1]));
      	$values = explode(',', $value);
      	for($i = 0; $i < count($values); $i++) {
      		$values[$i] = trim($values[$i], ' \'');
      	}
      	$where[$field] = $values;
      }
    }
    
    $databeans = Array();
    $b = $this->beans[get_class($databean)];
    foreach($b as $bean) {
      $bean_matches = true;
      foreach($where as $field => $value) {
      	if(is_array($value)) {
      		$found_match = false;
      		foreach($value as $v) {
      			if($bean->$field == $v) {
      				$found_match = true;
      			}
      		}
      		if(!$found_match) {
      			$bean_matches = false;
      		}
      	}
        elseif($bean->$field != $value) {
          $bean_matches = false;
        }
      }
      if($bean_matches) {
        $databeans[] = $bean;
      }
    }
    return $databeans;
  }
  function update($databean) {
    if(!isset($this->beans[get_class($databean)])) {
      $this->beans[get_class($databean)] = array();
    }
    $this->beans[get_class($databean)][$this->get_pk_value($databean)] = $databean;
    $databean->setNew(false);
    return $databean;
  }
  function delete($databean) {
    if(!isset($this->beans[get_class($databean)])) {
      $this->beans[get_class($databean)] = array();
    }
    if(isset($this->beans[get_class($databean)][$this->get_pk_value($databean)])) {
      unset($this->beans[get_class($databean)][$this->get_pk_value($databean)]);
    }
    return $databean;
  }
  private function get_pk_value($databean) {
    $pk_value = $databean->get($databean->getPk());
    if($pk_value != NULL) {
      return $pk_value;
    }
    return UUID::get();
  }

  function raw_delete($table, $where_fields = array()) {
    if(!isset($this->tables[$table])) {
      $this->tables[$table] = array();
    }
    $t = $this->tables[$table];
    foreach($t as $index => $row) {
      $found_match = false;
      foreach($where_fields as $name => $v) {
        $value = $v;
        if(is_array($v)) {
          switch($v['condition']) {
            case '<>':
            case '!=':
              if($row[$name] == $value) {
                $found_match = true;
              }
              break;
            case '>':
              if($row[$name] > $value) {
                $found_match = true;
              }
              break;
            case '<':
              if($row[$name] < $value) {
                $found_match = true;
              }
              break;
            case 'in':
              if(!in_array($row[$name], $value)) {
                $found_match = true;
              }
              break;
            case '=':
            default:
              if($row[$name] != $value) {
                $found_match = true;
              }
              break;
          }
          $condition = $v['condition'];
          $value = $v['value'];
        } else {
          if($row[$name] != $value) {
            $found_match = true;
          }
        }
      }
      if($found_match) {
        unset($t[$index]);
      }
    }
  }
  function raw_insert($table, $fields = array()) {
    if(!isset($this->tables[$table])) {
      $this->tables[$table] = array();
    }
    $t = $this->tables[$table];
    foreach($t as $index => $row) {
      $found_match = true;
      foreach($fields as $name => $value) {
        if($row[$name] != $value) {
          $found_match = false;
        }
      }
      if($found_match) {
        throw new Exception("Unique constraint failure.");
      }
    }
    $this->tables[$table][] = $fields;
  }
  // TODO: Add order and grouping
  function raw_select($table, $fields = array(), $where_fields = array(), $cast_class = NULL, $order = array(), $group = array()) {
    if(!isset($this->tables[$table])) {
      $this->tables[$table] = array();
    }
    $results = array();
    $t = $this->tables[$table];
    foreach($t as $index => $row) {
      $found_match = true;
      foreach($where_fields as $name => $v) {
        $value = $v;
        if(is_array($v)) {
          switch($v['condition']) {
            case '<>':
            case '!=':
              if($row[$name] == $value) {
                $found_match = false;
              }
              break;
            case '>':
              if($row[$name] > $value) {
                $found_match = false;
              }
              break;
            case '<':
              if($row[$name] < $value) {
                $found_match = false;
              }
              break;
            case 'in':
              if(!in_array($row[$name], $value)) {
                $found_match = false;
              }
              break;
            case '=':
            default:
              if($row[$name] != $value) {
                $found_match = false;
              }
              break;
          }
          $condition = $v['condition'];
          $value = $v['value'];
        } else {
          if($row[$name] != $value) {
            $found_match = false;
          }
        }
      }
      if($found_match) {
        if($cast_class != NULL) {
          $results[] = $cast_class($row[0]);
        } else {
          $ret_row = array();
          foreach($fields as $field) {
            $ret_row[$field] = $row[$field];
          }
          $results[] = $ret_row;
        }
      }
    }
    return $results;
  }
  function raw_update($table, $fields = array(), $where_fields = array()) {
    if(!isset($this->tables[$table])) {
      $this->tables[$table] = array();
    }
    $t = $this->tables[$table];
    foreach($t as $index => $row) {
      $found_match = true;
      foreach($where_fields as $name => $v) {
        $value = $v;
        if(is_array($v)) {
          switch($v['condition']) {
            case '<>':
            case '!=':
              if($row[$name] == $value) {
                $found_match = false;
              }
              break;
            case '>':
              if($row[$name] > $value) {
                $found_match = false;
              }
              break;
            case '<':
              if($row[$name] < $value) {
                $found_match = false;
              }
              break;
            case 'in':
              if(!in_array($row[$name], $value)) {
                $found_match = false;
              }
              break;
            case '=':
            default:
              if($row[$name] != $value) {
                $found_match = false;
              }
              break;
          }
          $condition = $v['condition'];
          $value = $v['value'];
        } else {
          if($row[$name] != $value) {
            $found_match = false;
          }
        }
      }
      if($found_match) {
        foreach($row as $row_name => $row_value) {
          foreach($fields as $field_name => $field_value) {
            if($row_name == $field_name) {
              $row[$row_name] = $field_value;
            }
          }
        }
      }
    }
  }

  private static $named_query_values = array();
  public function set_named_query_value($name, $value) {
    ObjectAdapter::$named_query_values[$name] = $value;
  }
  function named_query($name, $sql = "", $params = array(), $hash = true) {
    if(!isset(ObjectAdapter::$named_query_values[$name])) {
      throw new \Exception("No value set for named query: " . $name);
    }
    return ObjectAdapter::$named_query_values[$name];
  }
  private function _parseList($param= Array())
  {
    return "('" . implode("','",$param) . "')";
  }


}
