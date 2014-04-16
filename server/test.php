<?php

require_once("shared.php");

$context = new stdClass;
$api = new Api($context);

$q = $api->query("select ? + 10 as `a`", "i", 7);
while ($q->read()) {
	echo $q->row["a"]."<br />";
}

$api->close();

?>
