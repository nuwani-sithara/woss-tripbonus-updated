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
$input = json_decode(file_get_contents('php://input'), true);
$tripDate = $input['tripDate'] ?? null;

if ($jobID <= 0 || !$tripDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing jobID or tripDate']);
    exit();
}

$tripDate .= ' ' . date('H:i:s');

try {
    $stmt = $conn->prepare("INSERT INTO trips (jobID, trip_date) VALUES (?, ?)");
    $stmt->bind_param("is", $jobID, $tripDate);

    if ($stmt->execute()) {
        $tripID = $stmt->insert_id;
        $stmt->close();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'tripID' => $tripID,
            'message' => 'Trip created successfully'
        ]);
    } else {
        throw new Exception('Failed to create trip');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
