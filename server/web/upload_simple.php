<?php

/*
	POST /upload
	Example: https://example.com/upload
	
	Simple upload. HTTP Basic Authentication required.
	
	Request query string:
		file_name=[string]		// Optional filename, used when downloading  
		content_type=[string]	// Optional content (mime) type, used when downloading
	
	Request post body:
		Raw binary file data
		
	Response:
		{
			"message": [string], 			// Informative message		
			"file_id": [string], 			// The server-generated unique ID of the new file (in URL form)
		}
*/

$username = authenticate_user();

$db = db_connect();

$file_id = generate_file_id($db);

$db->close();

$cache_filename = $config["cache_path"].$file_id;

$file_name = $file_id;
if (isset($_GET["file_name"]))
	$file_name = $_GET["file_name"];

$content_type = "text/plain";
if (isset($_GET["content_type"]))
	$content_type = $_GET["content_type"];

if (strpos($_SERVER["CONTENT_TYPE"], "multipart/form-data") === 0) {
	$file_name = $_FILES["file"]["name"];
	$content_type = $_POST["content_type"];
	if ($_FILES["file"]["error"] !== UPLOAD_ERR_OK) die("Upload error: ".$_FILES["file"]["error"]);
	if (!is_uploaded_file($_FILES["file"]["tmp_name"])) die("PHP doesn't trust file.");
	if (move_uploaded_file($_FILES["file"]["tmp_name"], $cache_filename) === FALSE)
		echo "ERROR";
} else {
	$input = fopen("php://input", "rb");
	$dest = fopen($cache_filename, "wb");
	do {
		$buf = fread($input, $config["buffer_size"]);
		fwrite($dest, $buf);
	} while (!feof($input));
	fclose($dest);
	fclose($input);
}

$file_size = filesize($config["cache_path"].$file_id);
$file_hash = hash_file("sha256", $config["cache_path"].$file_id);
	
$db = db_connect();

$q = $db->prepare("insert into files (id, file_size, file_hash, file_name, ".
                  "content_type, date_started, date_created, user_id) values ".
                  "(?,?,?,?,?,now(),now(),".
                  "(select id from users where username = ?));");
$q->bind_param("sissss", $file_id, $file_size, $file_hash, $file_name,
               $content_type, $username);
$q->execute() or die('Error: ' . mysqli_error($db));
$q->close();

$db->close();

if (ends_with($url, ".json")) {
	$result["message"] = "File uploaded successfully.";
	$result["file_id"] = $config["server_url"].$file_id;
	echo json_encode($result);
} elseif (ends_with($url, ".html")) {
	echo "<a target='_blank' href='".$config["server_url"].$file_id."'>".$config["server_url"].$file_id."</a>";
}

?>
