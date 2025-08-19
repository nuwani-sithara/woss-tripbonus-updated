<?php
require_once(__DIR__ . '/../config/dbConnect.php');
require_once('update_functions.php'); // Keep all your existing functions
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get jobID and userID from URL
$jobID  = isset($_GET['jobID']) ? intval($_GET['jobID']) : null;
$userID = isset($_GET['userID']) ? intval($_GET['userID']) : null;

if (!$userID || !$jobID) {
    echo json_encode(['success' => false, 'error' => 'Missing userID or jobID']);
    exit;
}

// Call existing function from update_functions.php
$result = deleteJob($conn, $jobID, $userID);

// Return JSON response
echo json_encode($result);
