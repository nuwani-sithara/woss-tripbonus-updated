<?php
session_start();
header("Content-Type: application/json");
include '../config/dbConnect.php';
require_once 'update_functions.php'; // make sure resolveClarification is defined here

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get userID and clarificationID from URL
$userID = isset($_GET['userID']) ? (int)$_GET['userID'] : null;
$clarificationID = isset($_GET['clarificationID']) ? (int)$_GET['clarificationID'] : 0;

if (!$userID) {
    echo json_encode(['success' => false, 'error' => 'Missing userID']);
    exit();
}

if ($clarificationID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid clarificationID']);
    exit();
}

// Get additional data from JSON body (optional)
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!$data) $data = $_POST;

// Call resolveClarification function
$result = resolveClarification($conn, $clarificationID, $userID, $data);

// Return JSON response
echo json_encode($result);
