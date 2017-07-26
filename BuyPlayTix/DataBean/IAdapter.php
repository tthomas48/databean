<?php
namespace BuyPlayTix\DataBean;
interface IAdapter {

	function load($databean, $param = "");
	function loadAll($databean, $field = "", $param = "", $andClause = "");
	function update($databean);
	function delete($databean);
	function raw_delete($table, $fields = array());
	function raw_insert($table, $fields = array());
  function raw_replace($table, $fields = array());
	function raw_update($table, $fields = array(), $where_fields = array());
	function raw_select($table, $fields = array(), $cast_class = NULL, $order = array(), $group = array());
	function named_query($name, $sql = "", $params = array(), $hash = true);
}
?>
