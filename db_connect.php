<?php
// Database connection parameters
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "peertutor_database";

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
?>