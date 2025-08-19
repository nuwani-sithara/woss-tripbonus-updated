<?php
// api/vessels.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Allow API calls from anywhere
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

include '../config/dbConnect.php';

$response = [
    'success' => false,
    'data' => [],
    'error' => ''
];

try {
    // Query vessels
    $sql = "SELECT vesselID, vessel_name FROM vessels ORDER BY vessel_name";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $vessels = [];
    while ($row = $result->fetch_assoc()) {
        $vessels[] = [
            'vesselID' => (int) $row['vesselID'],
            'vessel_name' => $row['vessel_name']
        ];
    }

    $response['success'] = true;
    $response['data'] = $vessels;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} finally {
    $conn->close();
    echo json_encode($response);
}
