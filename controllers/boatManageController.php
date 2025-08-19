<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $boatID = $_POST['boatID'] ?? null;
    $boat_name = $_POST['boat_name'] ?? null;
    
    if ($action === 'add') {
        if (!$boat_name) {
            echo json_encode(['success' => false, 'message' => 'Missing boat name.']);
            exit;
        }
        // Check if boat name already exists
        $check_stmt = $conn->prepare('SELECT boatID FROM boats WHERE boat_name = ?');
        $check_stmt->bind_param('s', $boat_name);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Boat name already exists.']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();
        $stmt = $conn->prepare('INSERT INTO boats (boat_name, created_date) VALUES (?, CURDATE())');
        $stmt->bind_param('s', $boat_name);
        if ($stmt->execute()) {
            $boatID = $conn->insert_id;
            $created_date = date('Y-m-d');
            echo json_encode(['success' => true, 'boat' => ['boatID' => $boatID, 'boat_name' => $boat_name, 'created_date' => $created_date]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding boat: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'edit') {
        if (!$boatID || !$boat_name) {
            echo json_encode(['success' => false, 'message' => 'Missing boat ID or name.']);
            exit;
        }
        $stmt = $conn->prepare('UPDATE boats SET boat_name = ? WHERE boatID = ?');
        $stmt->bind_param('si', $boat_name, $boatID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating boat: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        if (!$boatID) {
            echo json_encode(['success' => false, 'message' => 'Missing boat ID.']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM boats WHERE boatID = ?');
        $stmt->bind_param('i', $boatID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting boat: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all boats
    $result = $conn->query('SELECT * FROM boat');
    $boats = [];
    while ($row = $result->fetch_assoc()) {
        $boats[] = $row;
    }
    echo json_encode(['success' => true, 'boats' => $boats]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?> 