<?php

/*
	PUT /[file_id]/[chunk_index]
	Example: https://example.com/284H38EU2H/15

	Upload chunk number [chunk_index] of file [file_id].

	Request:
		Raw binary chunk data

	Response:
		{
			"message": [string],            // Informative message
            "next_chunk_index_hint": [int], // Another missing chunk that could
                                            // be uploaded
            "chunks_remaining": [int],      // Total number of chunks still
                                            // missing from the file
		}
*/

function respond($db, $message) {
	global $file_id;
	$res["message"] = $message;
	$res["next_chunk_index_hint"] = get_next_chunk_index_hint($db, $file_id);
	$res["chunks_remaining"] = chunks_remaining($db);
	echo json_encode($res);
	$db->close();
	exit;
}

function chunks_remaining($db) {
	global $file_id;
	$q = $db->prepare("select count(1) from chunks where file_id = ?;");
	$q->bind_param("s", $file_id);
	$q->execute();
	$q->bind_result($chunks_remaining);
	if (!$q->fetch()) respond($db, "Couldn't poll remaining chunks.");
	$q->close();
	return $chunks_remaining;
}

$db = db_connect();
if (!$db) respond($db, "Internal server error");

# Get file details from the database
$q = $db->prepare(
    "select chunk_size, file_hash from files where id = ? limit 1;");
$q->bind_param("s", $file_id);
$q->execute();
$q->bind_result($chunk_size, $file_hash);
if (!$q->fetch()) respond($db, "Couldn't find the file");
$q->close();

# Get the chunk hash from the database
$q = $db->prepare("select chunk_hash from chunks where file_id = ? and ".
    "chunk_index = ? limit 1;");
$q->bind_param("si", $file_id, $chunk_index);
$q->execute();
$q->bind_result($expected_chunk_hash);
# No matching chunk found
if (!$q->fetch()) respond($db,
    "Chunk not found. Collision with another client?");
$q->close();

$db->close();


# Read post request (should be the binary chunk data)
$chunk = file_get_contents("php://input");

# Hash the post data
$hash = hash("sha256", $chunk);

# Validate post data against expected chunk hash.
if ($hash != $expected_chunk_hash) respond($db,
    "Chunk hashes didn't match. Corrupted?");


# Open the incomplete cache file, seek to the chunk start position, and write
# the chunk data
$cache_filename = $config["cache_path"].$file_id;
$f = fopen($cache_filename, "cb");
fseek($f, $chunk_index * $chunk_size);
fwrite($f, $chunk);
fclose($f);

# Reopen the cache file and read the just-written chunk data back
$f = fopen($cache_filename, "c+b");
fseek($f, $chunk_index * $chunk_size);
$chunk_validate = fread($f, $chunk_size);
fclose($f);
# Make sure the write was successful
if ($chunk_validate != $chunk) respond($db,
    "Unable to validate the chunk write.");


$db = db_connect();
if (!$db) die("Fail");

# Chunk upload appears successful: delete the chunk record from the database
$q = $db->prepare("delete from chunks where file_id = ? and chunk_index = ?;");
$q->bind_param("si", $file_id, $chunk_index);
$q->execute();
$q->close();

# No chunks remaining: file finished?
if (chunks_remaining($db) == 0) {
	if ($file_hash != hash_file("sha256", $cache_filename))
		respond($db, "File appears complete, but failed final hash check.");

    # Update files record in the database so it will show as created and become
    # available to download
	$q = $db->prepare("update files set date_created = now() where id = ?;");
	$q->bind_param("s", $file_id);
	$q->execute();
	$q->close();

	respond($db, "File upload complete.");
}

respond($db, "Chunk uploaded successfully.");

?>
