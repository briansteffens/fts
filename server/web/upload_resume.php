<?php

/*
	POST /resume
	Example: https://example.com/resume

	Resume a chunked upload.

	Request:
		{
			"file_hash": [string],		// Full file hash (sha256)
		}

	Response:
		{
			"message": [string], 	        // Informative message
			"file_id": [string], 	        // The file's unique ID (URL form)
            "next_chunk_index_hint": [int], // A missing chunk that could be
                                            // uploaded
            "chunks_remaining": [int], 		// Total number of chunks still
                                            // missing from the file
            "chunk_size": [int],	        // Size in bytes of each chunk.
                                            // Set by a previous /start
		}
*/

$file = json_decode(file_get_contents('php://input'));

$mysqli = db_connect();

$query = $mysqli->prepare("select id, chunk_size from files ".
    "where file_hash = ? and date_created is null limit 1;");
$query->bind_param("s", $file->file_hash);
$query->execute();
$query->bind_result($file_id, $chunk_size);
if (!$query->fetch()) die("Couldn't find a matching pending file upload.");
$query->close();

$mysqli->close();

$result["file_id"] = $config["server_url"].$file_id;
$result["chunk_size"] = $chunk_size;

echo json_encode($result);

?>
