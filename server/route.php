<?php

require_once("config.php");
require_once("shared.php");
//require_once("test.php");
//exit;

$request = new stdClass;

$request->method = $_SERVER['REQUEST_METHOD'];
$request->full_path = $_GET["url"];

// Figure out which kind of resource the URL is referencing (directory/file/chunk).
$request->resource_type = "file";
$request->chunk_index = NULL;
if (substr($request->full_path, -1) === "/")
	$request->resource_type = "directory";
elseif (isset($_GET["chunk"])) {
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
$request->descriptor = new stdClass;
$request->descriptor->user = "brian";
$request->descriptor->group = "users";
$request->descriptor->permissions = "0755";
Handler::set_resource_descriptor($request);

$context = new stdClass;
$context->request = $request;

$api = new Api($context);

$context->result = new stdClass;
$context->result->status_code = 200;
$context->result->message = "";

// Dispatch
try {
	switch ($context->request->resource_type) {
		case "directory":
			switch ($context->request->method) {
				case "GET":
					$api->list_directory($context);
					break;
				case "PUT":
					$api->create_directory($context);
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

$api->close();

Handler::process_response($context);

?>
