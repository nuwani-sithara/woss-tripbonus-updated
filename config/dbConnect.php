<?php

date_default_timezone_set('Asia/Colombo');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "woss_tripbonus";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>