<?php

require_once("/etc/fts-server.conf");
require_once("../shared.php");

$url = substr($_GET["url"], 1);

# http://example.com/upload - simple single post upload
if (starts_with($url, "upload")) {
	require_once("upload_simple.php");
	exit;
}

# http://example.com/start - upload a file digest, starting a chunked file upload
if ($url == "start") {
	require_once("upload_start.php");
	exit;
}

# http://example.com/resume - resume a chunked file upload
if ($url == "resume") {
	require_once("upload_resume.php");
	exit;
}

$parts = explode("/", $url);

# http://example.com/ - nothing/index
if (sizeof($parts) == 0 || (sizeof($parts) == 1 && $parts[0] === "")) {
	require_once("upload_form.php");
	exit;
}

$file_id = $parts[0];

# http://example.com/iehe7j38d7 - attempt to download a file
if (sizeof($parts) == 1) {
	require_once("download.php");
	exit;
}

$chunk_index = $parts[1];

# http://example.com/iehe7j38d7/10 - upload a chunk
if (sizeof($parts) == 2) {
	require_once("upload_chunk.php");
	exit;
}

?>
