<?php
namespace BuyPlayTix\DataBean;
class DataBean implements \Iterator
{
    private static $cache = Array();
    /**
     * associate array containing fields and values for this table
     * @access private
     * @var array
     */
    public $fields = Array();
    
    /**
     * associate array containing descriptions of fields in the database
     * @access private
     * @var array
     */
    public static $field_defs = Array();
        
    /**
     * name of the primary key of this table,
     * @access private
     * @var string
     */
    protected $pk = 'ID';
    /**
     * name of the table that populates this class
     * @access private
     * @var string
     */
    protected $table;
    /**
     * any extra required where clause
     * @access private
     * @var string
     */
    public $whereClause;
    /**
     * flag designating if this class was created or loaded from the database
     * @access private
     * @var boolean
     */
    public $new = true;
    /**
     * database connection string
     * @access private
     * @var boolean
     */
    protected $connectionString = 'db/master';

    protected $log;

    protected static $adapterClass = '\BuyPlayTix\DataBean\DBAdapter';

    protected static $adapter;

    /**
     * Creates a new DataBean object
     *
     * A new BuyPlayTix_Db_DataBean object is created, if $param is an aBuyPlayTix_Db_DataBean the BuyPlayTix_Db_DataBean is looked up
     * by using the first value in the array as the field name and the second as the
     * field value. If $param is a string the BuyPlayTix_Db_DataBean is looked up using the primary
     * key as the field name and $param as the value. If $param is blank a new object
     * is created with a unique ID as the primary key. This object is not inserted
     * until an update is called.
     *
     * @param array/string  $param
     * @return reference to object
     * @access public
     */
    public static function setAdapter($adapter) {
        DataBean::$adapter = $adapter;
    }

    public static function getAdapter() {
        return DataBean::$adapter;
    }

    private static function cache($object) {
        $pk = $object->pk;
        if(!empty($object->$pk)) {
            DataBean::$cache[$object->$pk] = $object;
        }
    }

    private static function uncache($object) {
        $pk = $object->pk;
        unset(DataBean::$cache[$object->$pk]);
    }


    private static function load($pk) {
        $pk = trim($pk);
        if(empty($pk) || !array_key_exists($pk, DataBean::$cache)) {
            return false;
        }
        return DataBean::$cache[$pk];
    }
    
    public function diff() {
    	$cached_version = Databean::load($this->fields[$this->pk]);
    	if(!$cached_version) {
    		return "";
    	}
    	$old_fields = $cached_version->fields;
    	$new_fields = $this->fields;
    	
    	$diff = new Diff();
    	$diff->diff($old_fields, $new_fields);
    	return $diff->__toString();
    }

    public function duplicate() {
        DataBean::uncache($this);
         
        $databean = DataBean::$adapter->duplicate($this);
        $this->fields = $databean->fields;
        $this->new = $databean->new;
        $this->whereClause = $databean->whereClause;
        return $this;
    }

    public function __construct($param = "") {
        if($this->log == null) {
            $this->log = DB::$log;
        }
        require_once 'BuyPlayTix/Db/Builder/' . strtolower($this->table) . ".php";

        if(!is_array($param) && ($databean = DataBean::load($param)) !== false) {
            $this->fields = $databean->fields;
            $this->new = $databean->new;
            $this->whereClause = $databean->whereClause;
            return $this;
        }

        if(!DataBean::$adapter) {
            DataBean::$adapter = new DataBean::$adapterClass();
        }
        DataBean::$adapter->load($this, $param);
        // we don't want to cache new objects until we've saved
        if(!$this->isNew()) {
            DataBean::cache($this);
        }
        return $this;
    }
    //TODO: once we upgrade to php 5.3 we can use this
    public static function getObjects($field = "", $param = "", $andClause = "") {
        $className = get_called_class();
        $instance = new $className();
        return $instance->getDataBeans($field, $param, $andClause);
    }
    /**
     * Gets all databeans
     *
     * Returns all databeans specified, if both parameters are blank
     * all databeans are returned. If $field is blank, databeans will
     * be looked up by primary key.
     *
     * @param string        $field   (optional) look up databeans by this field
     * @param array         $param   (optional) values to lookup databeans by
     * @return array BuyPlayTix_Db_DataBeans
     * @access public
     */
    function getDatabeans($field = "", $param = "", $andClause = "")
    {
        return DataBean::$adapter->loadAll($this, $field, $param, $andClause);
    }
    /**
     * Gets a value
     *
     * Returns the value of the specified key
     *
     * @param string        $key     the field name to return the value of
     * @return string value
     * @access public
     */
    function get($key)
    {
        return $this->__get($key);
    }
    function __get($key)
    {
        if(isset($this->fields[$key])) {
            return $this->fields[$key];
        }
        return null;
    }
    public function __isset($key) {
        if(array_key_exists($key, $this->fields)) {
            return $this->fields[$key];
        }
        return false;
    }
    /**
     * Sets a value
     *
     * Sets the specified key to the specified value
     *
     * @param string        $key     the field name to set
     * @param string        $value   the value to set the key to
     * @return
     * @access public
     */
    function set($key, $value)
    {
        $this->__set($key, $value);
    }
    function __set($key, $value)
    {
        $this->fields[$key] = $value;
    }

    function __call($method, $value = "")
    {
        if (substr($method, 0, 3) == 'get') {
            $m = strtolower(substr($method, 3, 1)) . substr($method, 4);
            return $this->$m;
        }
        else if (substr($method, 0, 3) == 'set') {
            $m = strtolower(substr($method, 3, 1)) . substr($method, 4);
            $this->$m = $value;
        }
        else if (substr($method, 0, 2) == 'is') {
            $m = strtolower(substr($method, 2, 1)) . substr($method, 3);
            return $this->$m;
        }
        else {
            throw new Exception("Unknown method: $method");
        }
    }
    /**
     * Deletes this object
     *
     * Deletes this object from the database
     *
     * @return
     * @access public
     */
    function delete()
    {
        DataBean::$adapter->delete($this);
        DataBean::uncache($this);
    }
    /**
     * Updates this object
     *
     * Update this object in the database
     *
     * @return
     * @access public
     */
    function update()
    {
        $result = DataBean::$adapter->update($this);
        DataBean::cache($this);
        return $result;
    }

    private $valid = false;
    /**
     * Rewind the Iterator to the first element.
     * Similar to the reset() function for arrays in PHP
     * @return void
     */
    function rewind() {
        $this->valid = (false !== reset($this->fields));
    }

    /**
     * Return the current element.
     * Similar to the current() function for arrays in PHP
     * @return mixed current element from the collection
     */
    function current() {
        return current($this->fields);
    }

    /**
     * Return the identifying key of the current element.
     * Similar to the key() function for arrays in PHP
     * @return mixed either an integer or a string
     */
    function key() {
        return key($this->fields);
    }

    /**
     * Move forward to next element.
     * Similar to the next() function for arrays in PHP
     * @return void
     */
    function next() {
        $this->valid = (false !== next($this->fields));
    }

    /**
     * Check if there is a current element after calls to rewind() or next().
     * Used to check if we've iterated to the end of the collection
     * @return boolean FALSE if there's nothing more to iterate over
     */
    function valid() {
        return $this->valid;
    }

    public function getFields() {
        return $this->fields;
    }

    public function getPk() {
        return $this->pk;
    }

    public function getTable() {
        return $this->table;
    }

    public function getWhereClause() {
        return $this->whereClause;
    }

    public function setWhereClause($whereClause) {
        $this->whereClause = $whereClause;
    }

    public function isNew() {
        return $this->new;
    }

    public function setNew($isNew) {
        $this->new = $isNew;
    }
    
    public function __toString() {
        return get_class($this) . "[" . $this->fields[$this->pk] . "]";
    }

    public function __clone() {
    	 
    	$this->setNew(true);
    	$uuid = \BuyPlayTix_UUID::get();
      $this->setPk($uuid);
    }
    
    public function setPk($uuid) {
      $db = DB::getInstance();
      $this->fields[$this->getPk()] = $uuid;
      $this->setWhereClause('where ' . $this->getPk() . ' = ' . $db->quote($uuid));
    }
    
    public function hashCode() {
        ksort($this->fields);
        $hash = 0;
        foreach($this->fields as $key => $value) {
          $string = $key . "~" . $value;
          $stringLength = strlen($string);
          for($i = 0; $i < $stringLength; $i++){
              $hash = 31 * $hash + $string[$i];
          }
        }
        return $hash;
    }
}
