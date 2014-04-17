<?php

class Handler {

	public static function set_resource_descriptor($request) {
		if (isset($_GET["user"]))
			$request->descriptor->user = $_GET["user"];

		if (isset($_GET["group"]))
			$request->descriptor->group = $_GET["group"];

		if (isset($_GET["permissions"]))
			$request->descriptor->mask = $_GET["permissions"];

		if ($request->resource_type === "file") {	
			if (isset($_GET["file_size"]))
				$request->descriptor->file_size = $_GET["file_size"];

			if (isset($_GET["chunk_size"]))
				$request->descriptor->chunk_size = $_GET["chunk_size"];

			if (isset($_GET["file_hash"]))
				$request->descriptor->file_hash = $_GET["file_hash"];

			if (isset($_GET["chunk_hashes"]))
				$request->descriptor->chunk_hashes = $_GET["chunk_hashes"];		
		}
	}
	
	public static function process_response($context) {
		header($_SERVER["SERVER_PROTOCOL"]." ".$context->result->status_code);
		
		if ($context->result->status_code !== 200) {
			echo "<h2>".$context->result->status_code."</h2>";
			echo "<h4>".$context->result->message."</h3>";
		}
	}

}



/*
if ($resource_type === "directory") {
	if ($request_method === "GET") {
		// List directory contents
	} elseif ($request_method === "PUT") {
		// Create directory
	} elseif ($request_method === "DELETE") {
		// Delete directory
	}
} elseif ($resource_type === "file") {
	if ($request_method === "GET") {
		// Download file
	} elseif ($request_method === "PUT") {
		// Upload file
	} elseif ($request_method === "DELETE") {
		// Delete file
	}
} elseif ($resource_type === "chunk") {
	if ($request_method === "PUT") {
		// Upload chunk
	}
}
*/

?>
