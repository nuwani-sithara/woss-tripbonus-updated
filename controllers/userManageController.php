<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;
    $username = $_POST['username'] ?? null;
    $fname = $_POST['fname'] ?? null;
    $lname = $_POST['lname'] ?? null;
    $roleID = $_POST['roleID'] ?? null;
    $password = $_POST['password'] ?? null;
    $rateID = $_POST['rateID'] ?? null;

    // Make password optional only for drivers (roleID 2)
    $isDriver = ($roleID == 2);
    
    if (!$email || !$username || !$fname || !$lname || !$roleID || (!$isDriver && !$password)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    // Convert empty rateID to NULL for database
    if (empty($rateID)) {
        $rateID = null;
    }

    // For drivers, set a default password if not provided
    if ($isDriver && empty($password)) {
        $password = 'driver_default_password'; // You might want to generate something random
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    // Check if email or username already exists
    $check_stmt = $conn->prepare('SELECT userID FROM users WHERE email = ? OR username = ?');
    $check_stmt->bind_param('ss', $email, $username);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email or username already exists.']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Hash the password
    // $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $conn->begin_transaction();
    try {
        // Insert into users
        $stmt = $conn->prepare('INSERT INTO users (email, username, password, fname, lname, roleID, rateID, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('sssssis', $email, $username, $password, $fname, $lname, $roleID, $rateID);
        if (!$stmt->execute()) {
            throw new Exception('Error creating user: ' . $stmt->error);
        }
        $userID = $conn->insert_id;
        $stmt->close();

        // Role-specific inserts
        if ($roleID == 1) {
            // Insert into supervisors
            $sup_stmt = $conn->prepare('INSERT INTO supervisors (userID) VALUES (?)');
            $sup_stmt->bind_param('i', $userID);
            if (!$sup_stmt->execute()) {
                throw new Exception('Error creating supervisor: ' . $sup_stmt->error);
            }
            $sup_stmt->close();
            // Insert into employees
            $emp_stmt = $conn->prepare('INSERT INTO employees (userID, roleID) VALUES (?, ?)');
            $emp_stmt->bind_param('ii', $userID, $roleID);
            if (!$emp_stmt->execute()) {
                throw new Exception('Error creating employee: ' . $emp_stmt->error);
            }
            $emp_stmt->close();
        } elseif ($roleID == 2 || $roleID == 8 || $roleID == 9) {
            // Insert into employees
            $emp_stmt = $conn->prepare('INSERT INTO employees (userID, roleID) VALUES (?, ?)');
            $emp_stmt->bind_param('ii', $userID, $roleID);
            if (!$emp_stmt->execute()) {
                throw new Exception('Error creating employee: ' . $emp_stmt->error);
            }
            $emp_stmt->close();
        }

        $conn->commit();
        // Fetch rate_name for response (only if rateID is provided)
        $rate_name = '';
        if ($rateID !== null) {
            $rate_stmt = $conn->prepare('SELECT rate_name FROM rates WHERE rateID = ?');
            $rate_stmt->bind_param('i', $rateID);
            $rate_stmt->execute();
            $rate_stmt->bind_result($rate_name);
            $rate_stmt->fetch();
            $rate_stmt->close();
        }
        $created_at = date('Y-m-d H:i:s');
        $user = [
            'userID' => $userID,
            'email' => $email,
            'username' => $username,
            'fname' => $fname,
            'lname' => $lname,
            'roleID' => $roleID,
            'rateID' => $rateID,
            'rate_name' => $rate_name,
            'created_at' => $created_at
        ];
        echo json_encode(['success' => true, 'user' => $user]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?> 