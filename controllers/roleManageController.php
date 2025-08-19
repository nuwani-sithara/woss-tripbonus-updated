<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $role_name = $_POST['role_name'] ?? null;
        if (!$role_name) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        // Check if role_name already exists
        $check_stmt = $conn->prepare('SELECT roleID FROM roles WHERE role_name = ?');
        $check_stmt->bind_param('s', $role_name);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Role name already exists.']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();
        $stmt = $conn->prepare('INSERT INTO roles (role_name) VALUES (?)');
        $stmt->bind_param('s', $role_name);
        if ($stmt->execute()) {
            $roleID = $conn->insert_id;
            $roleData = [
                'roleID' => $roleID,
                'role_name' => $role_name
            ];
            echo json_encode(['success' => true, 'role' => $roleData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating role.']);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'update') {
        $roleID = $_POST['roleID'] ?? null;
        $role_name = $_POST['role_name'] ?? null;
        if (!$roleID || !$role_name) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        $stmt = $conn->prepare('UPDATE roles SET role_name = ? WHERE roleID = ?');
        $stmt->bind_param('si', $role_name, $roleID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating role.']);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'delete') {
        $roleID = $_POST['roleID'] ?? null;
        if (!$roleID) {
            echo json_encode(['success' => false, 'message' => 'Missing roleID.']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM roles WHERE roleID = ?');
        $stmt->bind_param('i', $roleID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting role.']);
        }
        $stmt->close();
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $roles = [];
    $sql = "SELECT * FROM roles";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
    }
    echo json_encode(['success' => true, 'roles' => $roles]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
