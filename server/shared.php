<?php

function crypto_rand_secure($min, $max) {
    $range = $max - $min;
    if ($range == 0) return $min; // not so random...
    $log = log($range, 2);
    $bytes = (int) ($log / 8) + 1; // length in bytes
    $bits = (int) $log + 1; // length in bits
    $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
    do {
        $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes, $s)));
        $rnd = $rnd & $filter; // discard irrelevant bits
    } while ($rnd >= $range);
    return $min + $rnd;
}

function get_next_chunk_index_hint($db, $file_id) {
	$q = $db->prepare("select count(1) from chunks where file_id = ?;");
	$q->bind_param("s", $file_id);
	$q->execute();
	$q->bind_result($chunks_remaining);
	if (!$q->fetch()) die("Couldn't find a remaining chunk.");
	$q->close();
	
	if ($chunks_remaining < 1)
		return NULL;
		
	$offset = crypto_rand_secure(1, $chunks_remaining) - 1;
	
	$q = $db->prepare("select chunk_index from chunks where file_id = ? limit ?, 1;");
	$q->bind_param("si", $file_id, $offset);
	$q->execute();
	$q->bind_result($chunk_index);
	if (!$q->fetch()) die("Couldn't find a remaining chunk.");
	$q->close();
	
	return $chunk_index;
}

function generate_file_id($db) {
	while (true) {
		$id = "";
	
		for ($i = 0; $i < 10; $i++) {
			$c = crypto_rand_secure(0, 61);
		
			// 0-9 are digits
			if ($c <= 9)
				$id .= $c;

			// 10-35 are upper-case letters (ASCII 65+)
			elseif ($c <= 35)
				$id .= chr(($c - 10) + 65);
		
			// 36-61 are lower-case letters (ASCII 97+)
			else
				$id .= chr(($c - 36) + 97);
		}
		
		$q = $db->prepare("select count(1) from files where id = ?;");
		$q->bind_param("s", $id);
		$q->execute();
		$q->bind_result($count);
		if (!$q->fetch()) die("Couldn't look up existing file IDs.");
		$q->close();

		if ($count == 0)
			return $id;
	}
}

function db_connect() {
	global $config;
	
	$mysqli = new mysqli($config["db"]["host"], $config["db"]["user"], $config["db"]["pass"], $config["db"]["schema"]);
	if (!$mysqli) die("Fail");
	
	return $mysqli;
}

function starts_with($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}

function ends_with($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function hash_pass($raw) {
    return password_hash($raw, PASSWORD_BCRYPT);
}

// Validates the current request with HTTP Basic Authentication and returns
// the username if successful. Otherwise, returns FALSE.
function authenticate_user() {
    // Request HTTP Basic Authentication if it's not already present.
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="fts"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication is required in order to upload files.';
        exit;
    }

    // Get the hashed password from the database.
    $db = db_connect();

    $q = $db->prepare("select password from users where username = ?;");
    $q->bind_param("s", $_SERVER['PHP_AUTH_USER']);
    $q->execute() or die('Error: '.mysqli_error($db)."\n");
    $q->bind_result($hash);
    $q->fetch() or die('Invalid username or password.');
    $q->close();

    $db->close();

    // Validate the stored password hash against the input.
    if (!password_verify($_SERVER['PHP_AUTH_PW'], $hash))
        die('Invalid username or password.');

    return $_SERVER['PHP_AUTH_USER'];
}

?>
