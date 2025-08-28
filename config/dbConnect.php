<?php

date_default_timezone_set('Asia/Colombo');

$servername = "localhost";
$username = "subseacp_usr";
$password = "-OOaO[?uv65Fz0kE";
$dbname = "subseacp_dbs";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>