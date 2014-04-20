<?php

class Node extends Entity {

	var $parent_id;
	var $type;
	var $name;
	var $date_created;

	// Permissions	
	var $user;
	var $group;
	var $permissions;
	
	// File only
	var $file_size;
	var $chunk_size;
	var $file_hash;

	public static function table_name() {
		return "nodes";
	}
	
	public static function entity_name() {
		return "Node";
	}
	
	public function select($row) {
		$this->id = $row["id"];
		$this->parent_id = $row["parent_id"];
		$this->type = $row["type"];
		$this->name = $row["name"];
		$this->user = $row["user"];
		$this->group = $row["group"];
		$this->permissions = $row["permissions"];
		$this->date_created = $row["date_created"];
		$this->file_size = $row["file_size"];
		$this->chunk_size = $row["chunk_size"];
		$this->file_hash = $row["file_hash"];
	}
	
	protected function sql_insert() {
		return [
			"insert into ".static::table_name()." (".
			"`parent_id`,`type`,`name`,`user`,`group`,`permissions`,".
			"`date_created`,`file_size`,`chunk_size`,`file_hash`".
			") values (?,?,?,?,?,?,?,?,?,?);",
			"issssssiis",
			$this->parent_id,
			$this->type,
			$this->name,
			$this->user,
			$this->group,
			$this->permissions,
			$this->date_created,
			$this->file_size,
			$this->chunk_size,
			$this->file_hash
			];
	}
	
	protected function sql_update() {
		return [
			"update ".static::table_name()." set ".
			"parent_id = ?,".
			"type = ?,".
			"name = ?,".
			"user = ?,".
			"group = ?,".
			"permissions = ?,".
			"date_created = ?,".
			"file_size = ?,".
			"chunk_size = ?,".
			"file_hash = ? ".
			"where id = ?",
			"issssssiisi",
			$this->parent_id,
			$this->type,
			$this->name,
			$this->user,
			$this->group,
			$this->permissions,
			$this->date_created,
			$this->file_size,
			$this->chunk_size,
			$this->file_hash,
			$this->id
			];
	}

	public static function path_up_one_level($path) {
		$path_parts = explode("/", $path);
		$path_parts = array_filter($path_parts, "strlen");
		$new_path_name = array_slice($path_parts, -1)[0];
		$path_parts = array_slice($path_parts, 0, -1);
		return implode("/", $path_parts);
	}

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
	
	
	protected function sql_delete() {
		return [
			"delete from ".static::table_name()." where id = ?",
			"i",
			$this->id
		];
	}

	public function delete($model) {
		if (!isset($this->id))
			throw new FtsServerException(500,
				static::entity_name()." can't be deleted: ".
				"it doesn't have an ID.");
		
		$q = $model->query($this->sql_delete());
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
