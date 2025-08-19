<?php
include '../config/dbConnect.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT vesselID, vessel_name FROM vessels ORDER BY vessel_name";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $vessels = [];
    while ($row = $result->fetch_assoc()) {
        $vessels[] = [
            'vesselID' => $row['vesselID'],
            'vessel_name' => $row['vessel_name']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $vessels]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 