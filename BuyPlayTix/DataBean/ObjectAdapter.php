<?php
namespace BuyPlayTix\DataBean;

class ObjectAdapter implements IAdapter
{

  public static $DB_DIR;

    private $tables = [];

    private $queries = [];

    public function __construct()
    {}

    function load($databean, $param = "")
    {
        $table = $databean->getTable();
        $pk = $databean->getPk();
        if (! array_key_exists($table, $this->tables)) {
            $this->tables[$table] = [];
        }
        
        $b = $this->tables[$table];
        
        if (is_array($param)) {
            foreach ($b as $bean) {
                if (array_key_exists($param[0], $bean) && $bean[$param[0]] == $param[1]) {
                    $databean->fields = $bean;
                    $databean->setNew(false);
                    return $databean;
                }
            }
        } elseif (strlen($param) > 0) {
            foreach ($b as $bean) {
                if (array_key_exists($pk, $bean) && $bean[$pk] == $param) {
                    $databean->fields = $bean;
                    $databean->setNew(false);
                    return $databean;
                }
            }
        }
        $uuid = UUID::get();
        $databean->fields[$pk] = $uuid;
        $databean->setNew(true);
        return $databean;
    }

    function loadAll($databean, $field = "", $param = "", $andClause = "")
    {

        if (strlen($field) > 0) {
            if (is_array($param) && count($param) == 1) {
                $whereClause = ' where ' . $field . ' = ' . $param[0];
            } else {
                $valList = $this->_parseList($param);
                $whereClause = ' where ' . $field . ' in ' . $valList;
            }
        } elseif (count($param) > 0 && is_array($param)) {
            if (is_array($param) && count($param) == 1) {
                $whereClause = ' where ' . $databean->getPk() . ' = ' . $param[0];
            } else {
                $valList = $this->_parseList($param);
                $whereClause = ' where ' . $databean->getPk() . ' in ' . $valList;
            }
        } else {
            $whereClause = "";
        }
        
        $group_by = "";
        $order_by = "";
        $where = array();
        
        $clause = $whereClause . " " . $andClause;
        $chunks = preg_split("/\s*GROUP\s+BY\s*/i", $clause);
        if (count($chunks) == 2) {
            $clause = $chunks[0];
            $group_by = $chunks[1];
        }
        $chunks = preg_split("/\s*ORDER\s+BY\s*/i", $clause);
        if (count($chunks) == 2) {
            $clause = $chunks[0];
            $order_by = $chunks[1];
        }
        $clause = preg_replace("/\s*WHERE\s*/i", "", $clause);
        $chunks = preg_split("/\s*AND\s*/i", $clause);
        foreach ($chunks as $chunk) {
            $where_chunks = preg_split("/\s*!=\s*/i", $chunk);
            if (count($where_chunks) == 2) {
                $field = strtoupper(trim($where_chunks[0]));
                $value = trim($where_chunks[1], ' \'');
                $where[$field] = array(
                    "v" => $value,
                    "condition" => "!="
                );
                continue;
            }
            $where_chunks = preg_split("/\s*=\s*/i", $chunk);
            if (count($where_chunks) == 2) {
                $field = strtoupper(trim($where_chunks[0]));
                $value = trim($where_chunks[1], ' \'');
                $where[$field] = array(
                    "v" => $value,
                    "condition" => "="
                );
                continue;
            }
            $where_chunks = preg_split("/\s+in\s*/i", $chunk);
            if (count($where_chunks) == 2) {
                $field = strtoupper(trim($where_chunks[0]));
                $value = str_replace(')', '', str_replace('(', '', $where_chunks[1]));
                $values = explode(',', $value);
                for ($i = 0; $i < count($values); $i ++) {
                    $values[$i] = trim($values[$i], ' \'');
                }
                $where[$field] = array(
                    "v" => $values,
                    "condition" => "="
                );
                continue;
            }
        }
        
        $databeans = array();
        $table = $databean->getTable();
        $pk = $databean->getPk();
        $b = $this->tables[$table];
        foreach ($b as $bean) {
            $bean_matches = true;
            foreach ($where as $field => $predicate) {
                $value = $predicate["v"];
                $condition = $predicate["condition"];
                
                if (is_array($value)) {
                    $found_match = false;
                    foreach ($value as $v) {
                        if (array_key_exists($field, $bean) && $this->isMatch($bean[$field], $condition, $v)) {
                            $found_match = true;
                        }
                    }
                    if (! $found_match) {
                        $bean_matches = false;
                    }
                } elseif (!array_key_exists($field, $bean) || ($this->isMatch($bean[$field], $condition, $value) === false)) {
                    $bean_matches = false;
                }
            }
            if ($bean_matches) {
                $className = get_class($databean);
                $newBean = new $className($bean[$pk]);
                $databeans[] = $newBean;
            }
        }
        return $databeans;
    }

    private function isMatch($beanValue, $condition, $value)
    {
        if ($condition === '=') {
            return $beanValue === $value;
        }
        if ($condition === '!=') {
            return $beanValue !== $value;
        }
        return false;
    }

    function update($databean)
    {
        $table = $databean->getTable();
        $pk = $databean->getPk();
        if (array_key_exists($table, $this->tables) === false) {
            $this->tables[$table] = [];
        }

        $existingRowKey = null;
        for($index = 0; $index < count($this->tables[$table]); $index++) {
            $row = $this->tables[$table][$index];
            if (array_key_exists($pk, $row) && $row[$pk] === $databean->$pk) {
                $existingRowKey = $index;
                break;
            }
        }
        
        $databean->setNew(false);
        if ($existingRowKey === null) {
            $this->tables[$table][] = $databean->getFields();
            return $databean;
        }
        foreach ($databean->getFields() as $key => $value) {
            $this->tables[$table][$existingRowKey][$key] = $value;
        }
        return $databean;
    }

    function delete($databean)
    {
        $table = $databean->getTable();
        $pk = $databean->getPk();
        if (! array_key_exists($table, $this->tables)) {
            return $databean;
        }
        
        foreach ($this->tables[$table] as $index => $row) {
            if (array_key_exists($pk, $row) && $row[$pk] === $databean->$pk) {
                unset($this->tables[$table][$index]);
                return $databean;
            }
        }
    }

    private function get_pk_value($databean)
    {
        $pk_value = $databean->get($databean->getPk());
        if ($pk_value != NULL) {
            return $pk_value;
        }
        return UUID::get();
    }

    function raw_delete($table, $where_fields = array())
    {
        if (array_key_exists($table, $this->tables) === false) {
            $this->tables[$table] = array();
        }
        $t = $this->tables[$table];
        foreach ($t as $index => $row) {
            $found_match = false;
            foreach ($where_fields as $name => $v) {
                $value = $v;
                if (is_array($v)) {
                    switch ($v['condition']) {
                        case '<>':
                        case '!=':
                            if ($row[$name] == $value) {
                                $found_match = true;
                            }
                            break;
                        case '>':
                            if ($row[$name] > $value) {
                                $found_match = true;
                            }
                            break;
                        case '<':
                            if ($row[$name] < $value) {
                                $found_match = true;
                            }
                            break;
                        case 'in':
                            if (! in_array($row[$name], $value)) {
                                $found_match = true;
                            }
                            break;
                        case '=':
                        default:
                            if ($row[$name] != $value) {
                                $found_match = true;
                            }
                            break;
                    }
                    $condition = $v['condition'];
                    $value = $v['value'];
                } else {
                    if ($row[$name] != $value) {
                        $found_match = true;
                    }
                }
            }
            if ($found_match) {
                unset($t[$index]);
            }
        }
    }

    function raw_insert($table, $fields = array())
    {
        if (! isset($this->tables[$table])) {
            $this->tables[$table] = array();
        }
        $t = $this->tables[$table];
        foreach ($t as $index => $row) {
            $found_match = true;
            foreach ($fields as $name => $value) {
                if ($row[$name] != $value) {
                    $found_match = false;
                }
            }
            if ($found_match) {
                throw new Exception("Unique constraint failure.");
            }
        }
        $this->tables[$table][] = $fields;
    }

    function raw_replace($table, $fields = []) {

      if (! isset($this->tables[$table])) {
        return $this->raw_insert($table, $fields);
      }
      $pk = "UID";
      if (array_key_exists("ID", $fields)) {
        $pk = "ID";
      }
      $foundRow = false;
      $t = $this->tables[$table];
      foreach ($t as $index => $row) {

        if ($row[$pk] === $fields[$pk]) {

          $foundRow = true;
          foreach($fields as $key => $value) {
            $this->tables[$table][$index][$key] = $value;
          }
        }
      }
      if (!$foundRow) {
        return $this->raw_insert($table, $fields);
      }
    }


    // TODO: Add order and grouping, aggregate
    function raw_select($table, $fields = array(), $where_fields = array(), $cast_class = NULL, $order = array(), $group = array())
    {
        if (array_key_exists($table, $this->tables) === false) {
            $this->tables[$table] = array();
        }
        $results = array();
        $t = $this->tables[$table];
        foreach ($t as $index => $row) {
            $found_match = true;
            foreach ($where_fields as $name => $v) {
                $value = $v;
                if (is_array($v)) {
                    switch ($v['condition']) {
                        case '<>':
                        case '!=':
                            if ($row[$name] == $value) {
                                $found_match = false;
                            }
                            break;
                        case '>':
                            if ($row[$name] > $value) {
                                $found_match = false;
                            }
                            break;
                        case '<':
                            if ($row[$name] < $value) {
                                $found_match = false;
                            }
                            break;
                        case 'in':
                            if (! in_array($row[$name], $value)) {
                                $found_match = false;
                            }
                            break;
                        case '=':
                        default:
                            if ($row[$name] != $value) {
                                $found_match = false;
                            }
                            break;
                    }
                    $condition = $v['condition'];
                    $value = $v['value'];
                } else {
                    if ($row[$name] != $value) {
                        $found_match = false;
                    }
                }
            }
            if ($found_match) {
                if ($cast_class != NULL) {
                    $class = new \ReflectionClass($cast_class);
                    $results[] = $class->newInstanceArgs(array(
                        $row[$fields[0]]
                    ));
                } else {
                    $ret_row = array();
                    
                    $aggregation = array();
                    foreach ($fields as $field) {
                        if (is_array($field)) {
                            $field_name = $field['name'];
                            $field_alias = $field['alias'];
                            if (empty($field_alias)) {
                                $field_alias = $field_name;
                            }
                            switch ($field['aggregation']) {
                                case 'count':
                                    if (isset($aggregation[$field_alias])) {
                                        $aggregation[$field_alias] = $aggregation[$field_alias]++;
                                        break;
                                    }
                                    $aggregation[$field_alias] = 1;
                                    break;
                                case 'sum':
                                    if (isset($aggregation[$field_alias])) {
                                        $aggregation[$field_alias] = $aggregation[$field_alias] += $row[$field_name];
                                        break;
                                    }
                                    $aggregation[$field_alias] = $row[$field_name];
                                    break;
                                default:
                                    throw new \Exception("Unknown aggregation type for field $field_name.");
                            }
                        } else {
                            if (array_key_exists($field, $row)) {
                              $ret_row[$field] = $row[$field];
                            } else {
                              $ret_row[$field] = null;
                            }
                        }
                    }
                    foreach ($aggregation as $field_name => $field_value) {
                        $ret_row[$field_name] = $field_value;
                    }
                    $results[] = $ret_row;
                }
            }
        }
        return $results;
    }

    function raw_update($table, $fields = array(), $where_fields = array())
    {
        if (array_key_exists($table, $this->tables) === false) {
        
            $this->tables[$table] = array();
        }
        $t = $this->tables[$table];
        foreach ($t as $index => $row) {
            $found_match = true;
            foreach ($where_fields as $name => $v) {
                $value = $v;
                if (is_array($v)) {
                    switch ($v['condition']) {
                        case '<>':
                        case '!=':
                            if ($row[$name] == $value) {
                                $found_match = false;
                            }
                            break;
                        case '>':
                            if ($row[$name] > $value) {
                                $found_match = false;
                            }
                            break;
                        case '<':
                            if ($row[$name] < $value) {
                                $found_match = false;
                            }
                            break;
                        case 'in':
                            if (! in_array($row[$name], $value)) {
                                $found_match = false;
                            }
                            break;
                        case '=':
                        default:
                            if ($row[$name] != $value) {
                                $found_match = false;
                            }
                            break;
                    }
                    $condition = $v['condition'];
                    $value = $v['value'];
                } else {
                    if ($row[$name] != $value) {
                        $found_match = false;
                    }
                }
            }
            if ($found_match) {
                foreach ($row as $row_name => $row_value) {
                    foreach ($fields as $field_name => $field_value) {
                        if ($row_name === $field_name) {
                            $this->tables[$table][$index][$row_name] = $field_value;
                        }
                    }
                }
            }
        }
    }

    public function set_named_query_value($name, $value)
    {
        $this->queries[$name] = $value;
    }

    function named_query($name, $sql = "", $params = array(), $hash = true)
    {
        if (! array_key_exists($name, $this->queries)) {
            throw new \Exception("No value set for named query: " . $name);
        }
        return $this->queries[$name];
    }

    private function _parseList($param = Array())
    {
        return "('" . implode("','", $param) . "')";
    }

    public function loadDatabase()
    {

        if (file_exists(ObjectAdapter::$DB_DIR . "tables.test.db")) {
          $lock = fopen(ObjectAdapter::$DB_DIR . "tables.test.db", 'rb');
          @flock($lock, LOCK_SH);
          $this->tables = unserialize(file_get_contents(ObjectAdapter::$DB_DIR . "tables.test.db"));
          @flock($lock, LOCK_UN);
          fclose($lock);
        }
        
        if (file_exists(ObjectAdapter::$DB_DIR .  "queries.test.db")) {
          $lock = fopen(ObjectAdapter::$DB_DIR . "queries.test.db", 'rb');
          @flock($lock, LOCK_SH);
          $this->queries = unserialize(file_get_contents(ObjectAdapter::$DB_DIR . "queries.test.db"));
          @flock($lock, LOCK_UN);
          fclose($lock);
        }
    }

    public function saveDatabase()
    {
        file_put_contents(ObjectAdapter::$DB_DIR . "tables.test.db", serialize($this->tables), LOCK_EX);
        file_put_contents(ObjectAdapter::$DB_DIR . "queries.test.db", serialize($this->queries), LOCK_EX);
        if ((fileperms(ObjectAdapter::$DB_DIR . "tables.test.db") & 0777) !== 0766) {
            chmod(ObjectAdapter::$DB_DIR . "tables.test.db", 0766);
        }
        if ((fileperms(ObjectAdapter::$DB_DIR . "queries.test.db") & 0777) !== 0766) {
            chmod(ObjectAdapter::$DB_DIR . "queries.test.db", 0766);
        }
    }

    public function printDatabase()
    {
      print "Queries: \n";
      print_r($this->queries);
      print "Tables: \n";
      print_r($this->tables);
    }

    public function clearDatabase()
    {
        $this->tables = [];
        $this->queries = [];
        $this->saveDatabase();
    }
}
