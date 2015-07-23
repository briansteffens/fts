#!/usr/bin/php
<?php

/*

FTS command line tool

Usage:

    fts-server user add john
    fts-server user add john password_here

*/

require_once('/etc/fts-server.conf');
require_once($config['src_path'] . 'shared.php');

// Only adding users is supported for now.
if (sizeof($argv) < 4 || $argv[1] != 'user' || $argv[2] != 'add')
    die("Usage: fts-server user add <username>\n");

// Determine the password
if (sizeof($argv) >= 5) {
    // If present, use the password from the command line arguments.
    $password = $argv[4];
} else {
    // Read a password interactively.
    $password = readline("Password: ");
    $confirm_password = readline("Confirm password: ");

    if ($password != $confirm_password)
        die("Passwords must match.");
}

// Add the user to the database
$db = db_connect();
if (!$db) die("Fail");

$q = $db->prepare("insert into users (username, password) values (?, ?);");
$q->bind_param("ss", $argv[3], hash_pass($password));
$q->execute() or die('Error adding user: '.mysqli_error($db)."\n");
$q->close();

$db->close();

echo "User " . $argv[3] . " added.\n";

?>