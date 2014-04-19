<?php

class FtsServerException extends RuntimeException {
	var $http_status_code;
	
	public function __construct($http_status_code, $message='', $code=0) {
		$this->http_status_code = $http_status_code;
		return parent::__construct($message, $code);
	}
}

class FtsDataException extends FtsServerException {
	public function __construct($http_status_code, $message='', $code=0) {
		return parent::__construct($http_status_code, $message, $code);
	}
}

class Api {

	var $model;
	var $context;
	
	public function Api($context, $model) {
		$this->context = $context;
		$this->model = $model;
	}

	public function resolve_path_part($parent_id, $path_name) {
		$q = $this->model->query(
			"select id from nodes ".
			"where parent_id=? and name=? ".
			"limit 1;",
			"is", $parent_id, $path_name
			);
			
		$fetch_successful = $q->read();
		
		$q->close();
		
		if (!$fetch_successful)
			throw new FtsServerException(404, "Path not found.");
			
		return $q->row["id"];
	}
	
	public function resolve_path($full_path) {
		if ($full_path === "" || $full_path === "/")
			return 0; # root directory ID
	
		$path_parts = explode("/", $full_path);
		
		$parent_id = 0; // Start at root
		foreach ($path_parts as $path_part) {
			$parent_id = $this->resolve_path_part($parent_id, $path_part);
		}
		
		return $parent_id;
	}
	
	public function clean_path($path) {
		$parts = explode("/", $path);
		$parts = array_filter($parts, "strlen");
		return implode("/", $parts);
	}

	public function create_directory($context) {
		# Get the new directory's parent directory
		$parent_path = Node::path_up_one_level($context->request->full_path);
		
		$parent_id = 0;
		if ($parent_path !== "") {
			$parent_id = $this->resolve_path($parent_path);
		}
		
		# Make sure the new directory doesn't already exist
		$directory_exists = True;
		try {
			$this->resolve_path_part($parent_id, $new_path_name);
		} catch (FtsServerException $e) {
			if ($e->http_status_code === 404) {
				$directory_exists = False;
			}
		}
		
		if ($directory_exists)
			throw new FtsServerException(409, "Cannot create directory '".
				$context->request->full_path."': File exists");
		
		$descriptor = $context->request->descriptor;
		
		$descriptor->name = $new_path_name;
		$descriptor->parent_id = $parent_id;
		$descriptor->date_created = date("Y-m-d H:i:s");
		
		$descriptor->insert($this->model);
	}

	public function update_directory($context) {
		$path = $this->clean_path($context->request->full_path);
		$id = $this->resolve_path($path);
		
		$dir = Dir::get_by_id($this->model, $id);
		
		$d = $context->request->descriptor;
		
		if (isset($d->parent_id))
			$dir->parent_id = $d->parent_id;
		
		if (isset($d->user))
			$dir->user = $d->user;
			
		if (isset($d->group))
			$dir->group = $d->group;
			
		if (isset($d->mask))
			$dir->mask = $d->mask; 
		
		$dir->update($this->model);
	}

	public function list_directory($context) {
		$path = $this->clean_path($context->request->full_path);
		$id = $this->resolve_path($path);
		
		$q = $this->model->query(
			"select * from nodes where `parent_id` = ? order by `type`;", 
			"i", 
			$id);
		
		$nodes = array();
		
		try {
			$context->result->node_list_parent = Node::get_by_id(
				$this->model, 
				$context->request->node->parent_id
				);
		} catch (FtsServerException $e) {
			if ($e->http_status_code !== 404)
				throw $e;
		}
		
		while ($q->read()) {
			$node = new Node();
			$node->select($q->row);
			$nodes[] = $node;
		}
		
		$context->result->node_list = $nodes;
		
		$q->close();
	}
	
	public function delete_directory($context) {
		$path = $this->clean_path($context->request->full_path);
		$id = $this->resolve_path($path);
		
		$dir = new Dir();
		$dir->id = $id;
		$dir->delete($this->model);
	}
	
	public function file_info($context) {
	}

}

/*
function crypto_rand_secure($min, $max) {
    $range = $max - $min;
    if ($range == 0) return $min; // not so random...
    $log = log($range, 2);
    $bytes = (int) ($log / 8) + 1; // length in bytes
    $bits = (int) $log + 1; // length in bits
    $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
    do {
        $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes, $s)));
        $rnd = $rnd & $filter; // discard irrelevant bits
    } while ($rnd >= $range);
    return $min + $rnd;
}

function get_next_chunk_index_hint($db, $file_id) {
	$q = $db->prepare("select count(1) from chunks where file_id = ?;");
	$q->bind_param("s", $file_id);
	$q->execute();
	$q->bind_result($chunks_remaining);
	if (!$q->fetch()) die("Couldn't find a remaining chunk.");
	$q->close();
	
	if ($chunks_remaining < 1)
		return NULL;
		
	$offset = crypto_rand_secure(1, $chunks_remaining) - 1;
	
	$q = $db->prepare("select chunk_index from chunks where file_id = ? limit ?, 1;");
	$q->bind_param("si", $file_id, $offset);
	$q->execute();
	$q->bind_result($chunk_index);
	if (!$q->fetch()) die("Couldn't find a remaining chunk.");
	$q->close();
	
	return $chunk_index;
}

function generate_file_id($db) {
	while (true) {
		$id = "";
	
		for ($i = 0; $i < 10; $i++) {
			$c = crypto_rand_secure(0, 61);
		
			// 0-9 are digits
			if ($c <= 9)
				$id .= $c;

			// 10-35 are upper-case letters (ASCII 65+)
			elseif ($c <= 35)
				$id .= chr(($c - 10) + 65);
		
			// 36-61 are lower-case letters (ASCII 97+)
			else
				$id .= chr(($c - 36) + 97);
		}
		
		$q = $db->prepare("select count(1) from files where id = ?;");
		$q->bind_param("s", $id);
		$q->execute();
		$q->bind_result($count);
		if (!$q->fetch()) die("Couldn't look up existing file IDs.");
		$q->close();

		if ($count == 0)
			return $id;
	}
}

function starts_with($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}

function ends_with($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}
*/
?>
