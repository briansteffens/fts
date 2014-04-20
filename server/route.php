<?php

require_once("config.php");
require_once("shared.php");
require_once("model.php");
require_once("auth.php");

$request = new stdClass;

$request->method = $_SERVER['REQUEST_METHOD'];

$request->full_path = $_GET["url"];
if ($request->full_path === "")
	$request->full_path = "/";

// Figure out the kind of resource (node/chunk)
$request->resource_type = "node";
$request->chunk_index = NULL;
if (isset($_GET["chunk"])) {
	$request->resource_type = "chunk";
	$request->chunk_index = $_GET["chunk"];
}

// Get the expected response content type.
$request->content_type = "html";
if (isset($_GET["json"]))
	$request->content_type = "json";

// Import the appropriate content_type handler.
require_once($request->content_type.".handler.php");

$context = new stdClass;
$context->request = $request;

$context->auth = new Auth();
$context->auth->check_for_credentials($context);

// Get the resource descriptor
$request->descriptor = new Node();
$request->descriptor->user = $context->auth->user;
$request->descriptor->group = $context->auth->groups[0];
$request->descriptor->mask = "755";
Handler::set_resource_descriptor($request);

$context->result = new stdClass;
$context->result->status_code = 200;
$context->result->message = "";

$model = new Model();
$api = new Api($context, $model);

$node_id = NULL;
$request->node = NULL;
$request->node_type = NULL;
$request->node_parent = NULL;
try {
	$node_id = $api->resolve_path($request->full_path);
	$request->node = Node::get_by_id($api->model, $node_id);
	$request->node_type = $request->node->type;
	
	if (isset($request->node->parent_id)) {
		$request->node_parent = Node::get_by_id(
			$api->model, $request->node->parent_id);
	}
} catch (FtsServerException $e) {
	if ($e->http_status_code !== 404)
		throw $e;
		
	if (isset($request->descriptor->type))
		$request->node_type = $request->descriptor->type;
}

try {
	// Check permission
	try {
		$context->auth->check_node_permission($context);
	} catch (FtsAuthException $e) {
		if (!isset($_SERVER["PHP_AUTH_USER"]) && $context->auth->user === "anon") {
			header('WWW-Authenticate: Basic realm="FTS Realm"');
			header('HTTP/1.0 401 Unauthorized');
			echo "?!";
			exit;
		}
	
		throw new FtsAuthException(403, "Forbidden");
	}
	
	// Dispatch
	switch ($request->node_type) {
		case "dir":
			switch ($context->request->method) {
				case "GET":
					$api->list_directory($context);
					break;
				case "POST":
					$api->create_directory($context);
					break;
				case "PUT":
					$api->update_directory($context);
					break;
				case "DELETE":
					$api->delete_directory($context);
					break;
			}
			
			break;
			
		case "file":
			if ($context->request->method === "GET") {
				// Download file metadata
			} elseif ($context->request->method === "PUT") {
				// Upload file digest (start chunked create/update file)
			} elseif ($context->request->method === "DELETE") {
				// Delete file
			}
			
			break;
	}
} catch (FtsServerException $e) {
	$context->result->status_code = $e->http_status_code;
	$context->result->message = $e->getMessage();
}

$model->close();

Handler::process_response($context);

?>
