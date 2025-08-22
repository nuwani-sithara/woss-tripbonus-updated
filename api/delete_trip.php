<?php
session_start();
header("Content-Type: application/json");
include '../config/dbConnect.php';
include 'helpers.php';
include 'update_functions.php'; // make sure deleteTrip is defined here

// ✅ Allow both POST and GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

// Get input (JSON → POST → GET)
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!$data) $data = $_POST;
if (!$data) $data = $_GET;

// Determine userID
$userID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : null;
if (!$userID && isset($data['userID'])) {
    $userID = (int)$data['userID'];
}

if (!$userID) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get tripID
$tripID = isset($data['tripID']) ? (int)$data['tripID'] : 0;
if ($tripID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid tripID']);
    exit();
}

// Call deleteTrip function
$result = deleteTrip($conn, $tripID, $userID);

// Return result as JSON
echo json_encode($result);
