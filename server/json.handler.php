<?php

class Handler {

	public static function set_resource_descriptor($request) {
		if ($request->method !== "POST" && $request->method !== "PUT")
			return;
	
		$post_body = file_get_contents('php://input');

		if (!isset($post_body))
			return;
		
		$request->descriptor = json_decode($post_body);
	}
	
	public static function process_response($context) {
		header($_SERVER["SERVER_PROTOCOL"]." ".$context->result->status_code);
		
		$result = new stdClass;
		$result->status_code = $context->result->status_code;
		$result->message = $context->result->message;
		
		$container = new stdClass;
		$container->result = $result;
		
		echo json_encode(get_object_vars($container));
	}

}

/*
if ($resource_type === "directory") {
	if ($request_method === "GET") {
		// List directory
	} elseif ($request_method === "PUT") {
		// Create directory
	} elseif ($request_method === "DELETE") {
		// Delete directory
	}
} elseif ($resource_type === "file") {
	if ($request_method === "GET") {
		// Download file metadata
	} elseif ($request_method === "PUT") {
		// Upload file digest (start chunked create/update file)
	} elseif ($request_method === "DELETE") {
		// Delete file
	}
}
*/

?>
