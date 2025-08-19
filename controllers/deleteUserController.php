<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = $_POST['userID'] ?? null;
    if (!$userID) {
        echo json_encode(['success' => false, 'message' => 'Missing userID.']);
        exit;
    }
    // $conn is provided by dbConnect.php
    $stmt = $conn->prepare('DELETE FROM users WHERE userID=?');
    $stmt->bind_param('i', $userID);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
} 