<?php

require_once("config.php");
require_once("shared.php");
require_once("model.php");
require_once("auth.php");

$req = new stdClass;

$req->method = $_SERVER['REQUEST_METHOD'];

$req->full_path = $_GET["url"];
if ($req->full_path === "")
	$req->full_path = "/";

// Figure out the kind of resource (node/chunk)
$req->resource_type = "node";
$req->chunk_index = NULL;
if (isset($_GET["chunk"])) {
	$req->resource_type = "chunk";
	$req->chunk_index = $_GET["chunk"];
}

$req->meta = isset($_GET["meta"]);

require_once("json.handler.php");

$ctx = new stdClass;
$ctx->req = $req;

$ctx->auth = new Auth();
$ctx->auth->check_for_credentials($ctx);

// Get the resource descriptor
$req->descriptor = new Node();
$req->descriptor->user = $ctx->auth->user;
$req->descriptor->group = $ctx->auth->groups[0];
$req->descriptor->permissions = "755";
Handler::set_resource_descriptor($req);

$ctx->res = new stdClass;
$ctx->res->status_code = 200;
$ctx->res->message = "";

$model = new Model();
$api = new Api($ctx, $model);

$node_id = NULL;
$req->node = NULL;
$req->node_type = NULL;
$req->node_parent = NULL;

try {
	$node_id = $api->resolve_path($req->full_path);
	$req->node = Node::get_by_id($api->model, $node_id);
	$req->node_type = $req->node->type;
	if (isset($req->node->parent_id)) {
		$req->node_parent = Node::get_by_id(
			$api->model, $req->node->parent_id);
	}
} catch (FtsServerException $e) {
	if ($e->http_status_code !== 404)
		throw $e;
	if (isset($req->descriptor->type))
		$req->node_type = $req->descriptor->type;
}

if (!isset($req->node) && $req->method === "POST") {
	$parent_path = Node::path_up_one_level($req->full_path);
	$req->node_parent = Node::get_by_id($api->model, $parent_path);
}

try {
	if ($req->method !== "POST" && !isset($req->node)) {
		throw new FtsServerException(404, "File not found");
	}

	// Check permission
	try {
		$ctx->auth->check_node_permission($ctx);
	} catch (FtsAuthException $e) {
		if (!isset($_SERVER["PHP_AUTH_USER"]) && 
			$ctx->auth->user === "anon") {
			header('WWW-Authenticate: Basic realm="FTS Realm"');
			header('HTTP/1.0 401 Unauthorized');
			exit;
		}
	
		throw new FtsAuthException(403, "Forbidden");
	}
	
	if ($ctx->req->method == "POST") {
		if (isset($req->chunk_index)) {
			$api->upload_chunk_data($ctx);
		} else {
			$api->create_node($ctx);
		}
	} else {
		// Old? dispatch
		switch ($req->node_type) {
			case "dir":
				switch ($ctx->req->method) {
					case "GET":
						$api->list_directory($ctx);
						break;
					case "PUT":
						$api->update_node($ctx);
						break;
					case "DELETE":
						$api->delete_node($ctx);
						break;
				}
			
				break;
			
			case "file":
				switch ($ctx->req->method) {
					case "GET":
						$api->get_file($ctx);
						break;
					case "PUT":
						$api->update_node($ctx);
						break;
					case "DELETE":
						$api->delete_node($ctx);
						break;
				}
			
				break;
		}
	}
} catch (FtsServerException $e) {
	$ctx->res->status_code = $e->http_status_code;
	$ctx->res->message = $e->getMessage();
}

$model->close();

Handler::process_response($ctx);

?>
