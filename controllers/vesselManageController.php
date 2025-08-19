<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $vesselID = $_POST['vesselID'] ?? null;
    $vessel_name = $_POST['vessel_name'] ?? null;
    
    if ($action === 'add') {
        if (!$vessel_name) {
            echo json_encode(['success' => false, 'message' => 'Missing vessel name.']);
            exit;
        }
        // Check if vessel name already exists
        $check_stmt = $conn->prepare('SELECT vesselID FROM vessels WHERE vessel_name = ?');
        $check_stmt->bind_param('s', $vessel_name);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Vessel name already exists.']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();
        $stmt = $conn->prepare('INSERT INTO vessels (vessel_name, create_date) VALUES (?, CURDATE())');
        $stmt->bind_param('s', $vessel_name);
        if ($stmt->execute()) {
            $vesselID = $conn->insert_id;
            $create_date = date('Y-m-d');
            echo json_encode(['success' => true, 'vessel' => ['vesselID' => $vesselID, 'vessel_name' => $vessel_name, 'create_date' => $create_date]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding vessel: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'edit') {
        if (!$vesselID || !$vessel_name) {
            echo json_encode(['success' => false, 'message' => 'Missing vessel ID or name.']);
            exit;
        }
        // Check if vessel name already exists for other vessels
        $check_stmt = $conn->prepare('SELECT vesselID FROM vessels WHERE vessel_name = ? AND vesselID != ?');
        $check_stmt->bind_param('si', $vessel_name, $vesselID);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Vessel name already exists.']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();
        $stmt = $conn->prepare('UPDATE vessels SET vessel_name = ? WHERE vesselID = ?');
        $stmt->bind_param('si', $vessel_name, $vesselID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating vessel: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        if (!$vesselID) {
            echo json_encode(['success' => false, 'message' => 'Missing vessel ID.']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM vessels WHERE vesselID = ?');
        $stmt->bind_param('i', $vesselID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting vessel: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all vessels
    $result = $conn->query('SELECT * FROM vessels');
    $vessels = [];
    while ($row = $result->fetch_assoc()) {
        $vessels[] = $row;
    }
    echo json_encode(['success' => true, 'vessels' => $vessels]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
