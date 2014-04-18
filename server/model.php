<?php

class Dir extends Entity {
	
	var $id = NULL;
	var $directory_name;
	var $parent_id;
	var $user;
	var $group;
	var $mask;
	
	public static function table_name() {
		return "directories";
	}
	
	public static function entity_name() {
		return "Dir";
	}
	
	public function select($row) {
		$this->id = $row["id"];
		$this->directory_name = $row["directory_name"];
		$this->parent_id = $row["parent_id"];
		$this->user = $row["p_user"];
		$this->group = $row["p_group"];
		$this->mask = $row["p_mask"];
	}
	
	protected function sql_insert() {
		return [
			"insert into ".static::table_name()." ".
			"(directory_name,parent_id,p_user,p_group,p_mask) ".
			"values (?,?,?,?,?);",
			"sisss",
			$this->directory_name,
			$this->parent_id,
			$this->user,
			$this->group,
			$this->mask
			];
	}
	
	protected function sql_update() {
		return [
			"update ".static::table_name()." set ".
			"directory_name = ?, ".
			"parent_id = ?, ".
			"p_user = ?, ".
			"p_group = ?, ".
			"p_mask = ? ".
			"where id = ?",
			"sisssi",
			$this->directory_name,
			$this->parent_id,
			$this->user,
			$this->group,
			$this->mask,
			$this->id
			];
	}
	
}


class File extends Entity {



}


class Entity {

	var $id = NULL;
	
	public static function table_name() {
		throw new FtsServerException(500, "Not implemented.");
	}
	
	public static function entity_name() {
		throw new FtsServerException(500, "Not implemented.");
	}
	
	
	
	public static function sql_get_by_id($id) {
		return [
			"select * from ".static::table_name()." where id = ? limit 1",
			"i",
			$id
		];
	}
	
	public static function get_by_id($model, $id) {
		$q = $model->query(static::sql_get_by_id($id));
		
		if (!$q->read())
			throw new FtsServerException(404, 
				static::entity_name()." ID [".$id."] not found.");
		
		$q->close();
		
		$class_name = get_called_class();
		$ret = new $class_name($this);
		$ret->select($q->row);
		return $ret;
	}


	public function select($row) {
		throw new FtsServerException(500, "Not implemented.");
	}



	protected function sql_insert() {
		throw new FtsServerException(500, "Not implemented.");
	}

	public function insert($model) {
		if (isset($this->id))
			throw new FtsServerException(500,
				static::entity_name()." can't be inserted, ".
				"it already has an ID: '".$this->id."'");
		
		$q = $model->query($this->sql_insert());
		$q->execute();
		$q->close();
		
		if ($q->affected_rows != 1)
			throw new FtsServerException(500, 
				"Unknown error creating ".static::entity_name.".");
		
		$this->id = $model->db->insert_id;
	}
	
	
	
	protected function sql_update() {
		throw new FtsServerException(500, "Not implemented.");
	}
	
	public function update($model) {
		if (!isset($this->id))
			throw new FtsServerException(500,
				static::entity_name()." can't be updated, ".
				"it doesn't have an ID.");
		
		$q = $model->query($this->sql_update());
		$q->execute();
		$q->close();
		
		if ($q->affected_rows > 1)
			throw new FtsServerException(500, 
				"Unknown error updating ".static::entity_name().
				" '".$this->id."'");
	}



	public function delete($model) {
		if (!isset($this->id))
			throw new FtsServerException(500,
				static::entity_name()." can't be deleted: ".
				"it doesn't have an ID.");
		
		$q = $model->query(
			"delete from ".static::table_name()." where id = ?",
			"i",
			$this->id
			);
		
		$q->execute();
		$q->close();
		
		if ($q->affected_rows != 1)
			throw new FtsServerException(500, 
				"Unknown error deleting ".static::entity_name().
				" '".$this->id."'");
	}

}

class Model {

	var $db;
	
	public function Model() {
		global $config;
	
		$this->db = new mysqli(
			$config["db"]["host"], 
			$config["db"]["user"], 
			$config["db"]["pass"], 
			$config["db"]["schema"]
			);
			
		if (!$this->db) die("Fail");
	}
	
	public function close() {
		$thread = $this->db->thread_id;
		$this->db->close();
		//$this->db->kill($thread);
	}

	public function query() {
		$params = func_get_args();
		
		if (is_array($params[0]))
			$params = $params[0];
		
		$q = new Query($this->db, $params[0]);
		
		if (count($params) >= 3)
			call_user_func_array(
				array($q, "params"), 
				array_slice($params, 1)
				);
				
		return $q;
	}

}

class Query {
	
	var $db;
	var $q;
	var $result;
	var $row;
	
	var $executed = FALSE;
	var $affected_rows = NULL;
	
	public function Query($db, $sql) {
		$this->db = $db;
		
		$this->q = $this->db->prepare($sql);
		if (!$this->q)
			throw new FtsDataException(500, 
				"Database rejected query. Error: [".$this->db->error."]"
				);
	}
	
	public function params() {
		if (!call_user_func_array(
			array($this->q, "bind_param"), func_get_args()))
			throw new FtsDataException(500,
				"Unable to bind parameters: [".$this->db->error."]"
				);
	}
	
	public function execute() {
		if ($this->executed)
			throw new FtsDataException(500, "Query already executed.");
		
		$this->executed = TRUE;
		
		if (!$this->q->execute())
			throw new FtsDataException(500,
				"Error executing query: [".$this->db->error."]"
				);
		
		$this->affected_rows = $this->db->affected_rows;
	}
	
	public function read() {
		if (!$this->executed)
			$this->execute();
	
		if ($this->result === NULL) {
			$this->result = $this->q->get_result();
			
			if (!$this->result)
				throw new FtsDataException(500,
					"Error on retrieve: [".$this->db->error."]"
					);
		}
		
		$this->row = $this->result->fetch_assoc();
		
		if ($this->row === NULL) {
			$this->close_result();
			return FALSE;
		}
		
		return TRUE;
	}
	
	function close_result() {
		if ($this->result !== NULL) {
			$this->result->close();
			$this->result = NULL;
		}
	}
	
	public function close() {
		$this->close_result();	
		
		$this->q->close();
		$this->q = NULL;
	}
	
}

?>
