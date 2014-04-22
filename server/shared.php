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



class Util {

	public static function rand($min, $max) {
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
	
	public static function now() {
		return date("Y-m-d H:i:s");
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
		$full_path = $this->clean_path($full_path);
	
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

	public function create_node($context) {
		global $config;
	
		# Get the new directory's parent directory
		$parts = explode("/", $context->request->full_path);
		$parts = array_filter($parts, "strlen");
		$new_path_name = $parts[count($parts) - 1];
		$parts = array_slice($parts, 0, -1);
		$parent_path = implode("/", $parts);
		
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
			throw new FtsServerException(409, "Cannot create node '".
				$context->request->full_path."': File exists");
		
		$descriptor = $context->request->descriptor;
		
		$descriptor->name = $new_path_name;
		$descriptor->parent_id = $parent_id;
		$descriptor->date_created = Util::now();		

		if ($context->request->content_type === "") {
			$descriptor->type = "file";
		}
		
		if ($descriptor->type === "file") {
			if (isset($descriptor->file_size) && 
				!isset($descriptor->chunk_size)) {
				$descriptor->chunk_size = $descriptor->file_size;
				if ($descriptor->chunk_size > $config["max_chunk_size"])
					$descriptor->chunk_size = $config["max_chunk_size"];
			}
			$descriptor->date_created = NULL;
		}
		
		$descriptor->insert($this->model);

		if ($context->request->content_type === "json" &&
			$descriptor->type === "file") {
			$chunk_index = 0;
			foreach ($descriptor->chunk_hashes as $chunk_hash) {
				$chunk = new Chunk();
				$chunk->node_id = $descriptor->id;
				$chunk->index = $chunk_index;
				$chunk->hash = $chunk_hash;
				$chunk->insert($this->model);
				
				$chunk_index++;
			}
			$chunk_index++;
		}

		if ($context->request->content_type !== "")
			return;
		
		if ($context->request->content_type === "") {
			$post = fopen("php://input", "r");
			$file_size = 0;
			$chunk_size = $config["max_chunk_size"];
			$chunk_index = 0;
			while (!feof($post)) {
				$buffer = fread($post, $chunk_size);
				if (!$buffer)
					break;
				$file_size += strlen($buffer);
				
				$chunk = new Chunk();
				$chunk->node_id = $descriptor->id;
				$chunk->index = $chunk_index;
				$chunk->hash = "some";
				$chunk->chunk = $buffer;
				$chunk->insert($this->model);
				
				$chunk_index++;
			}
			
			fclose($post);
			
			$descriptor->file_size = $file_size;
			$descriptor->chunk_size = $chunk_size;
			if ($descriptor->chunk_size > $descriptor->file_size)
				$descriptor->chunk_size = $descriptor->file_size;
			$descriptor->date_created = date("Y-m-d H:i:s");
			$descriptor->update($this->model);
		}
	}

	public function update_directory($context) {
		$path = $this->clean_path($context->request->full_path);
		$id = $this->resolve_path($path);
		
		$dir = Node::get_by_id($this->model, $id);
		
		$d = $context->request->descriptor;
		
		if (isset($d->parent_id))
			$dir->parent_id = $d->parent_id;
		
		if (isset($d->user))
			$dir->user = $d->user;
			
		if (isset($d->group))
			$dir->group = $d->group;
		
		if (isset($d->permissions))
			$dir->permissions = $d->permissions; 
		
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
		
		$context->result->node = $context->request->node;
		$context->result->node_list = $nodes;
		
		$q->close();
	}
	
	public function get_file($context) {
		//$path = $this->clean_path($context->request->full_path);
		//$id = $this->resolve_path($path);
		
		if ($context->request->content_type === "json") {
			$context->result->node = $context->request->node;
			return;
		}
		
		if ($context->request->content_type === "") {
			if (!isset($context->request->node->date_created))
				throw new FtsServerException(409, "File incomplete");
		
			$total_chunks = $context->request->node->get_total_chunks();
			for ($index = 0; $index < $total_chunks; $index++) {
				$chunk = Chunk::get_by_index(
					$this->model,
					$context->request->node->id, 
					$index
					);
					
				echo $chunk->chunk;
				flush();
			}
			exit;
		}
	}
	
	public function delete_directory($context) {
		$path = $this->clean_path($context->request->full_path);
		$id = $this->resolve_path($path);

		$node = Node::get_by_id($this->model, $id);
		$node->delete($this->model);
	}
	
	public function upload_chunk_data($context) {
		$post = fopen("php://input", "r");
		
		$buffer = fread($post, $context->request->node->chunk_size);
		if (!$buffer)
			throw new FtsServerException(400, "Unable to read chunk data.");
		
		fclose($post);
		
		$chunk = Chunk::get_by_index(
			$this->model, 
			$context->request->node->id, 
			$context->request->chunk_index
			);
		
		if (hash("sha256", $buffer) !== $chunk->hash)
			throw new FtsServerException(400, "Chunk failed hash check.");
		
		$chunk->chunk = $buffer;
		$chunk->update($this->model);
		
		$hint = $context->request->node->get_next_hint($this->model);
		if (isset($hint)) {
			//$context->response->next_hint = $hint;
			return;
		}
		
		if (!$context->request->node->check_hash($this->model))
			throw new FtsServerException(500, "File failed hash check.");
		
		$context->request->node->date_created = Util::now();
		$context->request->node->update($this->model);
	}
	
	public function file_info($context) {
	}

}

/*

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
