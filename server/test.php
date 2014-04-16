<?php

require_once("shared.php");

class Query {
	
	var $db;
	var $q;
	
	function Query($db, $sql) {
		$this->db = $db;
		$this->q = $this->db->prepare($sql);
		//var_dump($this->db->error);
	}
	
	function params() {
		call_user_func_array(array($this->q, "bind_param"), func_get_args());
		return $this;
	}
	
	function result() {
		call_user_func_array(array($this->q, "bind_result"), func_get_args());
		return $this;
	}
	
	function close() {
		$this->q->close();
		return $this;
	}
	
}

$context = new stdClass;
$api = new Api($context);

$q = new Query($api->db, "select ? + 10;");
$q->params("i", "7");
//$r = NULL;
$q->q->bind_result($r);
var_dump($q->db->error);
//$q->result($r);
var_dump($q->q->fetch());
var_dump($r);

$api->close();

?>
