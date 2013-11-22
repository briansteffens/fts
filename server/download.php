<?php

/*
	GET /[file_id]
	Example: https://example.com/284H38EU2H
	
	Downloads a previously uploaded file.
		
	Response:
		Raw binary data.
*/

$mysqli = db_connect();
if (!$mysqli) die("Fail");

$query = $mysqli->prepare("select file_name, content_type from files where id = ? and not date_created is null;");
$query->bind_param("s", $file_id);
$query->execute();
$query->bind_result($filename, $content_type);
if (!$query->fetch()) die("Couldn't find the file.");
$query->close();

$mysqli->close();

header('Content-type: '.$content_type);
header('Content-Disposition: attachment; filename="'.$filename.'"');

$cache_filename = $config["cache_path"].$file_id;

$buffer_size = 65536;
$f = fopen($cache_filename, "rb");
while (!feof($f)) {
	echo fread($f, $buffer_size);
	flush();
}
fclose($f);

?>
