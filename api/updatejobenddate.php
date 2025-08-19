<?php
include '../config/dbConnect.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method is allowed']);
    exit();
}

// Get jobID from URL
$jobID = isset($_GET['jobID']) ? (int)$_GET['jobID'] : 0;

// Get JSON input for endDate
$input = json_decode(file_get_contents('php://input'), true);
$endDate = $input['endDate'] ?? null;

if ($jobID <= 0 || !$endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing jobID or endDate']);
    exit();
}

$endDate .= ' ' . date('H:i:s');

try {
    $stmt = $conn->prepare("UPDATE jobs SET end_date = ? WHERE jobID = ?");
    $stmt->bind_param("si", $endDate, $jobID);

    if ($stmt->execute()) {
        $stmt->close();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'jobID' => $jobID,
            'message' => 'Job end date updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update job');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
