<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = $_POST['userID'] ?? null;
    $eno = $_POST['eno'] ?? null; // Get eno value
    $email = $_POST['email'] ?? null;
    $username = $_POST['username'] ?? null;
    $fname = $_POST['fname'] ?? null;
    $lname = $_POST['lname'] ?? null;
    $roleID = $_POST['roleID'] ?? null;
    $rateID = $_POST['rateID'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!$userID || !$email || !$username || !$fname || !$lname || !$roleID) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    // Convert empty eno to NULL for database
    if (empty($eno)) {
        $eno = null;
    }

    // Convert empty rateID to NULL for database
    if (empty($rateID)) {
        $rateID = null;
    }

    // Prepare base query
    $query = 'UPDATE users SET email=?, eno=?, username=?, fname=?, lname=?, roleID=?, rateID=?';
    $params = [$email, $eno, $username, $fname, $lname, $roleID, $rateID];
    $types = 'ssssssi';

    // Add password to query if provided
    if (!empty($password)) {
        $query .= ', password=?';
        $params[] = $password;
        $types .= 's';
    }

    $query .= ' WHERE userID=?';
    $params[] = $userID;
    $types .= 'i';

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}