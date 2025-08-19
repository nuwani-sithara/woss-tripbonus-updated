<?php
session_start();
header("Content-Type: application/json");
include '../config/dbConnect.php';
require_once 'update_functions.php'; // contains getSupervisorEmpID and updateJob

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

// Get jobID and userID from URL
$jobID = isset($_GET['jobID']) ? (int)$_GET['jobID'] : 0;
$userID = isset($_GET['userID']) ? (int)$_GET['userID'] : 0;

if ($jobID <= 0 || $userID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid jobID/userID']);
    exit();
}

// Get update data (JSON body)
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!$data) {
    // fallback for form-data
    $data = $_POST;
}

// Run the update
$result = updateJob($conn, $jobID, $userID, $data);

// Return JSON response
echo json_encode($result);
