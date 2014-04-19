<?php

require_once("config.php");
require_once("shared.php");
require_once("model.php");

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

// Get the resource descriptor
$request->descriptor = new Node();
$request->descriptor->user = "brian";
$request->descriptor->group = "users";
$request->descriptor->mask = "755";
Handler::set_resource_descriptor($request);

$context = new stdClass;
$context->request = $request;

$context->result = new stdClass;
$context->result->status_code = 200;
$context->result->message = "";

$model = new Model();
$api = new Api($context, $model);

$node_id = NULL;
$request->node = NULL;
$request->node_type = NULL;
try {
	$node_id = $api->resolve_path($request->full_path);
	$request->node = Node::get_by_id($api->model, $node_id);
	$request->node_type = $request->node->type;
} catch (FtsServerException $e) {
	if ($e->http_status_code !== 404)
		throw $e;
		
	if (isset($request->descriptor->type))
		$request->node_type = $request->descriptor->type;
}

// Dispatch
try {
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
