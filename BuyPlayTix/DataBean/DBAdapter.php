<?php
namespace BuyPlayTix\DataBean;

class DBAdapter implements IAdapter
{

    function load($databean, $param = "")
    {
        try {
            $db = DB::getInstance($databean->connectionString);
            
            if (is_array($param)) {
                $databean->fields[$param[0]] = $param[1];
                $databean->setWhereClause('where ' . $param[0] . $this->handleNull($param[1]));
            } elseif (strlen($param) > 0) {
                $databean->fields[$databean->getPk()] = $param;
                $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($param));
            } else {
                $uuid = UUID::get();
                $databean->fields[$databean->getPk()] = $uuid;
                $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($uuid));
                return;
            }
            $sql = "select *
      from " . $databean->getTable() . " " . $databean->getWhereClause();
            $sth = $db->query($sql);
            
            if ($db->numRows($sth) > 0) {
                $databean->fields = $db->fetchAssocArray($sth);
                $databean->setNew(false);
            } else {
                // we need to make sure a UID is assigned if we don't return a record from the database
                $uuid = UUID::get();
                $databean->fields[$databean->getPk()] = $uuid;
                $databean->setWhereClause('where ' . $databean->getPk() . $this->handleNull($uuid));
                $databean->setNew(true);
            }
        } catch (\Exception $e) {
            throw new Exception("Unable to instantiate databean " . __CLASS__ . ": " . $e);
        }
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
        try {
            $db = DB::getInstance($databean->connectionString);
            
            if (strlen($field) > 0) {
                if (is_array($param) && count($param) == 1) {
                    $whereClause = ' where ' . $field . $this->handleNull($param[0]);
                } else {
                    $valList = $this->_parseList($param);
                    $whereClause = ' where ' . $field . ' in ' . $valList;
                }
            } elseif (count($param) > 0 && is_array($param)) {
                if (is_array($param) && count($param) == 1) {
                    $whereClause = ' where ' . $databean->getPk() . $this->handleNull($param[0]);
                } else {
                    $valList = $this->_parseList($param);
                    $whereClause = ' where ' . $databean->getPk() . ' in ' . $valList;
                }
            } else {
                $whereClause = "";
            }
            
            $sql = "select " . $databean->getPk() . "
      from " . $databean->getTable() . " " . $whereClause . " " . $andClause;
            $result = $db->query($sql);
            $databeans = Array();
            while ($row = $db->fetchArray($result)) {
                $classname = get_class($databean);
                $databeans[] = new $classname($row[0]);
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
            
            $fieldList = "";
            $valueList = "";
            if ($databean->isNew()) {
                foreach ($databean->fields as $field => $value) {
                    $fieldList .= "`$field`" . ",";
                    if ($value === null) {
                        $valueList .= "null,";
                    } else {
                        $valueList .= $db->quote($value) . ",";
                    }
                }
                $fieldList = rtrim($fieldList, ',');
                $valueList = rtrim($valueList, ',');
                
                $sql = "insert into " . $databean->getTable() . " " . "(" . $fieldList . ") " . "values (" . $valueList . ")";
                $databean->setNew(false);
            } else {
                foreach ($databean->fields as $field => $value) {
                    if ($value === null) {
                        $valueList .= "`$field`" . " = null,\n";
                    } else {
                        $valueList .= "`$field`" . " = " . $db->quote($value) . ",\n";
                    }
                }
                $valueList = rtrim($valueList, ",\n");
                
                $sql = " update " . $databean->getTable() . " " . " set " . $valueList . " " . $databean->getWhereClause();
            }
            return $db->executeSql($sql);
        } catch (\Exception $e) {
            throw new Exception("Unable to update object: " . $e);
        }
    }

    function delete($databean)
    {
        try {
            $db = DB::getInstance($databean->connectionString);
            
            $sql = "delete
      from " . $databean->getTable() . "
      where " . $databean->getPk() . " = " . $db->quote($databean->fields[$databean->getPk()]);
            
            return $db->query($sql);
        } catch (\Exception $e) {
            throw new Exception("Unable to delete object: " . $e);
        }
    }

    private function _parseList($params = Array())
    {
        $db = DB::getInstance();
        
        $quotedParams = [];
        foreach($params as $param) {
            $quotedParams[] = $db->quote($param);
        }
        return "(" . implode(",", $quotedParams) . ")";
    }

    function raw_delete($table, $fields = array())
    {
        $db = DB::getInstance();
        
        $restrictions = array();
        $values = array();
        foreach ($fields as $name => $v) {
            $condition = '=';
            $value = $v;
            if (is_array($v)) {
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

    function raw_insert($table, $insert_fields = array())
    {
        $db = DB::getInstance();
        
        $fields = array();
        $values = array();
        foreach ($insert_fields as $name => $value) {
            $fields[] = $name;
            $placeholders[] = "?";
            $values[] = $value;
        }
        
        $sql = "insert
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
        $where_clause = "";
        foreach ($where_fields as $name => $v) {
            $condition = '=';
            $value = $v;
            if (is_array($v)) {
                $condition = $v['condition'];
                $value = $v['value'];
            }
            $where_clause[] = $name . " $condition ? ";
            $values[] = $value;
        }
        
        $sql = "update " . $table . " set " . implode(",", $set_clause) . " where " . implode(" AND ", $where_clause);
        $sth = $db->prepare($sql);
        return $db->execute($sth, $values);
    }

    function raw_select($table, $fields = array(), $where_fields = array(), $cast_class = NULL, $order = array(), $group = array())
    {
        $db = DB::getInstance();
        
        $values = array();
        $where_clause = array();
        foreach ($where_fields as $name => $v) {
            $condition = '=';
            $value = $v;
            if (is_array($v)) {
                $condition = $v['condition'];
                $value = $v['value'];
                if ($condition === 'in') {
                    $value = $this->_parseList($value);
                    $where_clause[] = $name . " in " . $value . " ";
                } else {
                    $where_clause[] = $name . " $condition ? ";
                    $values[] = $value;
                }
            } else {
                $where_clause[] = $name . " $condition ? ";
                $values[] = $value;
            }
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
        $sql = "SELECT " . implode(",", $formatted_fields) . " FROM " . $table . (count($where_clause) ? " WHERE " . implode(" AND ", $where_clause) : '') . (count($order) ? ' ORDER BY ' . implode(",", $order) : '') . (count($group) ? ' GROUP BY ' . implode(",", $group) : '');
        $sth = $db->prepare($sql);
        $result = $db->execute($sth, $values);
        
        $results = array();
        if ($cast_class != NULL) {
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
        
        $sth = NULL;
        if (isset($statement_cache[$name])) {
            $sth = $statement_cache[$name];
        } else {
            $sth = $db->prepare($sql);
            $statement_cache[$name] = $sth;
        }
        $result = $db->execute($sth, $params);
        
        $fetchFunc = "fetchAssocArray";
        if (! $hash) {
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
        return ' = ' . $db->quote($param);
    }
}
