<?php

class Handler {

	public static function set_resource_descriptor($request) {
		if (isset($_GET["type"]))
			$request->descriptor->type = $_GET["type"];
	
		if (isset($_GET["user"]))
			$request->descriptor->user = $_GET["user"];

		if (isset($_GET["group"]))
			$request->descriptor->group = $_GET["group"];

		if (isset($_GET["bitmask"]))
			$request->descriptor->bitmask = $_GET["bitmask"];

		if (isset($_GET["file_size"]))
			$request->descriptor->file_size = $_GET["file_size"];

		if (isset($_GET["chunk_size"]))
			$request->descriptor->chunk_size = $_GET["chunk_size"];

		if (isset($_GET["file_hash"]))
			$request->descriptor->file_hash = $_GET["file_hash"];

		if (isset($_GET["chunk_hashes"]))
			$request->descriptor->chunk_hashes = $_GET["chunk_hashes"];		
	}
	
	public static function process_response($context) {
		global $config;
	
		header($_SERVER["SERVER_PROTOCOL"]." ".$context->result->status_code);
		
		echo "<html>";
		echo "<head>";
		echo '<style type="text/css">';
		echo 'body { font-family: "Lucida Console", Monaco, monospace; }';
		echo '</style>';
		echo "</head>";
		echo "<body>";
		
		if ($context->result->status_code !== 200) {
			echo "<h2>".$context->result->status_code."</h2>";
			echo "<h4>".$context->result->message."</h3>";
			return;
		}
		
		//$current_path = substr($context->request->full_path, 1);
		$current_path = $context->request->full_path;
		if ($current_path === "/")
			$current_path = "";
		
		if (isset($context->result->node_list)) {
			echo '<table border="1" cellpadding="5" cellspacing="0">';
			
			$context->request->node->url = 
				$config["server_url"].$current_path;
			Handler::echo_node($context->request->node, ".");
			
			if (isset($context->result->node_list_parent)) {
				$context->result->node_list_parent->url = 
					$config["server_url"].
					Node::path_up_one_level($current_path);
				Handler::echo_node($context->result->node_list_parent, "..");
			}
			foreach ($context->result->node_list as $node) {
				$temp = $config["server_url"].$current_path;
				if (substr($temp, -1) !== "/")
					$temp .= "/";
				$node->url = $temp.$node->name;
				Handler::echo_node($node);
			}
			echo "</table>";
		}
		
		echo "</body>";
		echo "</html>";
	}
	
	static function echo_node($node, $node_name=NULL) {
		if (!isset($node_name))
			$node_name = $node->name;
			
		echo "<tr>";
		echo '<td><a href="'.$node->url.'">'.$node_name."</a></td>";
		echo "<td>".$node->type."</td>";
		echo "<td>".$node->user."</td>";
		echo "<td>".$node->group."</td>";
		echo "<td>".$node->permissions."</td>";
		echo "<td>".$node->date_created."</td>";			
		echo "</tr>";
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
