<?php

$servername = getenv('MYSQLHOST') ?: 'localhost';
$username   = getenv('MYSQLUSER') ?: 'root';
$password   = getenv('MYSQLPASSWORD') ?: 'PUPTRACKER2027';
$database   = getenv('MYSQLDATABASE') ?: 'pup_trackersys';
$port       = getenv('MYSQLPORT') ?: 3306;

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>