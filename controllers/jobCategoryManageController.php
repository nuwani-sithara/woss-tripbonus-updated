<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $jobtypeID = $_POST['jobtypeID'] ?? null;
    $type_name = $_POST['type_name'] ?? null;
    
    if ($action === 'add') {
        if (!$type_name) {
            echo json_encode(['success' => false, 'message' => 'Missing job type name.']);
            exit;
        }
        // Check if job type name already exists
        $check_stmt = $conn->prepare('SELECT jobtypeID FROM jobtype WHERE type_name = ?');
        $check_stmt->bind_param('s', $type_name);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Job type name already exists.']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();
        $stmt = $conn->prepare('INSERT INTO jobtype (type_name) VALUES (?)');
        $stmt->bind_param('s', $type_name);
        if ($stmt->execute()) {
            $jobtypeID = $conn->insert_id;
            echo json_encode(['success' => true, 'jobtype' => ['jobtypeID' => $jobtypeID, 'type_name' => $type_name]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding job type: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'edit') {
        if (!$jobtypeID || !$type_name) {
            echo json_encode(['success' => false, 'message' => 'Missing job type ID or name.']);
            exit;
        }
        $stmt = $conn->prepare('UPDATE jobtype SET type_name = ? WHERE jobtypeID = ?');
        $stmt->bind_param('si', $type_name, $jobtypeID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating job type: ' . $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        if (!$jobtypeID) {
            echo json_encode(['success' => false, 'message' => 'Missing job type ID.']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM jobtype WHERE jobtypeID = ?');
        $stmt->bind_param('i', $jobtypeID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting job type: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all job types
    $result = $conn->query('SELECT * FROM jobtype');
    $jobtypes = [];
    while ($row = $result->fetch_assoc()) {
        $jobtypes[] = $row;
    }
    echo json_encode(['success' => true, 'jobtypes' => $jobtypes]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
