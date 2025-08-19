<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $portID = $_POST['portID'] ?? null;
    $portname = $_POST['portname'] ?? null;
    
    if ($action === 'add') {
        if (!$portname) {
            echo json_encode(['success' => false, 'message' => 'Missing port name.']);
            exit;
        }
        // Check if port name already exists
        $check_stmt = $conn->prepare('SELECT portID FROM ports WHERE portname = ?');
        $check_stmt->bind_param('s', $portname);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Port name already exists.']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();
        $stmt = $conn->prepare('INSERT INTO ports (portname, created_date) VALUES (?, CURDATE())');
        $stmt->bind_param('s', $portname);
        if ($stmt->execute()) {
            $portID = $conn->insert_id;
            $created_date = date('Y-m-d');
            echo json_encode(['success' => true, 'port' => ['portID' => $portID, 'portname' => $portname, 'created_date' => $created_date]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding port: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'edit') {
        if (!$portID || !$portname) {
            echo json_encode(['success' => false, 'message' => 'Missing port ID or name.']);
            exit;
        }
        $stmt = $conn->prepare('UPDATE ports SET portname = ? WHERE portID = ?');
        $stmt->bind_param('si', $portname, $portID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating port: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        if (!$portID) {
            echo json_encode(['success' => false, 'message' => 'Missing port ID.']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM ports WHERE portID = ?');
        $stmt->bind_param('i', $portID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting port: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all ports
    $result = $conn->query('SELECT * FROM ports');
    $ports = [];
    while ($row = $result->fetch_assoc()) {
        $ports[] = $row;
    }
    echo json_encode(['success' => true, 'ports' => $ports]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
