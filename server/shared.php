<?php

class FtsServerException extends RuntimeException {
	var $http_status_code;
	
	public function __construct($http_status_code, $message='', $code=0) {
		$this->http_status_code = $http_status_code;
		return parent::__construct($message, $code);
	}
}

class Api {

	var $db;
	var $context;
	
	public function Api($context) {
		$this->context = $context;
		$this->db = db_connect();
	}
	
	public function close() {
		$thread = $this->db->thread_id;
		$this->db->close();
		//$this->db->kill($thread);
	}

	public function resolve_directory_part($parent_id, $path_name) {
		$q = $this->db->prepare(
			"select id from directories ".
			"where parent_id=? and directory_name=? ".
			"limit 1;"
			);
			
		$q->bind_param("is", $parent_id, $path_name);
		
		$q->execute();
		
		$q->bind_result($directory_id);
		$fetch_successful = $q->fetch();
		
		$q->close();
		
		if (!$fetch_successful)
			throw new FtsServerException(404, "Path not found.");
			
		return $directory_id;
	}
	
	public function resolve_directory($full_path) {
		$path_parts = explode("/", $full_path);
		
		$parent_id = 0; // Start at root
		foreach ($path_parts as $path_part) {
			$parent_id = $this->resolve_directory_part($parent_id, $path_part);
		}
		
		return $parent_id;
	}

	public function create_directory($context) {
		# Get the new directory's parent directory
		$path_parts = explode("/", $context->request->full_path);
		$path_parts = array_filter($path_parts, "strlen");
		$new_path_name = array_slice($path_parts, -1)[0];
		$path_parts = array_slice($path_parts, 0, -1);
		$parent_path = implode("/", $path_parts);
		
		$parent_id = 0;
		if ($parent_path !== "") {
			$parent_id = $this->resolve_directory($parent_path);
		}
		
		# Make sure the new directory doesn't already exist
		$directory_exists = True;
		try {
			$this->resolve_directory_part($parent_id, $new_path_name);
		} catch (FtsServerException $e) {
			if ($e->http_status_code === 404) {
				$directory_exists = False;
			}
		}
		
		if ($directory_exists)
			throw new FtsServerException(409, "Cannot create directory '".
				$context->request->full_path."': File exists");
		
		$descriptor = $context->request->descriptor;
		
		$q = $this->db->prepare(
			"insert into directories ".
			"(directory_name,parent_id,p_user,p_group,p_mask) ".
			"values (?,?,?,?,?);"
			);
		
		$q->bind_param("sisss", 
			$new_path_name, 
			$parent_id,
			$context->request->descriptor->user, 
			$context->request->descriptor->group, 
			$context->request->descriptor->permissions
			);
			
		$q->execute();
		$affected_rows = $this->db->affected_rows;
		$q->close();
		if ($affected_rows != 1)
			throw new FtsServerException(500, 
				"Unknown error creating directory '".
				$context->request->full_path."'");
	}

	public static function list_directory($context) {
	}
	
	public static function delete_directory($context) {
	}
	
	public static function file_info($context) {
	}

}

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

function db_connect() {
	global $config;
	
	$mysqli = new mysqli($config["db"]["host"], $config["db"]["user"], $config["db"]["pass"], $config["db"]["schema"]);
	if (!$mysqli) die("Fail");
	
	return $mysqli;
}

function starts_with($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}

function ends_with($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

?>
