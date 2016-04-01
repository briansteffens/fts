<?php

/*
	POST /start
	Example: https://example.com/start

	Start a chunked upload. HTTP Basic Authentication required.

	Request:
		{
			"file_size": [int],			// Size in bytes of file to be uploaded
			"file_hash": [string],		// Full file hash (sha256)
			"chunk_size": [int],		// Size in bytes of each chunk
			"chunk_hashes": [[string]],	// Each chunk's individual sha256 hash
			                            // in an array
			"file_name": [string],		// Optional filename, used when
			                            // downloading
			"content_type": [string],	// Optional content (mime) type, used
			                            // when downloading
		}

	Response:
		{
			"message": [string], 			// Informative message
			"file_id": [string], 			// The server-generated unique ID
			                                // of the new file (in URL form)
			"next_chunk_index_hint": [int], // A missing chunk that could be
			                                // uploaded
			"chunks_remaining": [int], 		// Total number of chunks still
			                                // missing from the file
		}
*/

$username = authenticate_user();

$digest = json_decode(file_get_contents('php://input'));

$db = db_connect();

$file_id = generate_file_id($db);

$q = $db->prepare("insert into files (id, file_size, chunk_size, file_hash, ".
                  "file_name, content_type, date_started, user_id) ".
                  "values (?,?,?,?,?,?,now(),".
                  "(select id from users where username = ?));");
$q->bind_param("siissss", $file_id, $digest->file_size, $digest->chunk_size,
               $digest->file_hash, $digest->file_name, $digest->content_type,
               $username);
$q->execute();
$q->close();

for ($i = 0; $i < sizeof($digest->chunk_hashes); $i++) {
	$q = $db->prepare("insert into chunks (file_id, chunk_index, chunk_hash) ".
	                  "values (?,?,?);");
	$q->bind_param("sis", $file_id, $i, $digest->chunk_hashes[$i]);
	$q->execute();
	$q->close();
}

$db->close();

$result["file_id"] = $config["server_url"].$file_id;
echo json_encode($result);

?>
