<?php

class Handler {

	public static function set_resource_descriptor($req) {
		if ($req->method !== "POST" && $req->method !== "PUT")
			return;
		
		if ($req->meta) {
			$req->post_body = file_get_contents('php://input');

			if (!isset($req->post_body)) {
				return;
			}
			
			$node = json_decode($req->post_body);
		
			if (!$node) {
				return;
			}
			
			if (isset($node->type))
				$req->descriptor->type = $node->type;
		
			if (isset($node->user))
				$req->descriptor->user = $node->user;
		
			if (isset($node->group))
				$req->descriptor->group = $node->group;
			
			if (isset($node->permissions))
				$req->descriptor->permissions = $node->permissions;
	
			if (isset($node->file_size))
				$req->descriptor->file_size = $node->file_size;

			if (isset($node->chunk_size))
				$req->descriptor->chunk_size = $node->chunk_size;

			if (isset($node->file_hash))
				$req->descriptor->file_hash = $node->file_hash;

			if (isset($node->chunk_hashes))
				$req->descriptor->chunk_hashes = $node->chunk_hashes;
		}	
	}
	
	public static function process_response($ctx) {
		header($_SERVER["SERVER_PROTOCOL"]." ".$ctx->res->status_code);
		
		$res = new stdClass;
		$res->status_code = $ctx->res->status_code;
		$res->message = $ctx->res->message;
		
		$container = new stdClass;
		$container->res = $res;
		$container->response = new stdClass;
		
		if (isset($ctx->res->node)) {
			$container->response->node = $ctx->res->node;
		}
		
		if (isset($ctx->res->node_list)) {
			$container->response->node_list = $ctx->res->node_list;
		}
		
		echo json_encode(get_object_vars($container));
	}

}

/*
if ($resource_type === "directory") {
	if ($req_method === "GET") {
		// List directory
	} elseif ($req_method === "PUT") {
		// Create directory
	} elseif ($req_method === "DELETE") {
		// Delete directory
	}
} elseif ($resource_type === "file") {
	if ($req_method === "GET") {
		// Download file metadata
	} elseif ($req_method === "PUT") {
		// Upload file digest (start chunked create/update file)
	} elseif ($req_method === "DELETE") {
		// Delete file
	}
}
*/

?>
