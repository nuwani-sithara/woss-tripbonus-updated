<?php
include '../config/dbConnect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    // Get form data
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $roleID = intval($_POST['roleID']);

    // Validate required fields
    if (empty($email) || empty($username) || empty($password) || empty($fname) || empty($lname) || empty($roleID)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit();
    }

    // Check if email or username already exists
    $check_query = "SELECT * FROM users WHERE email = '$email' OR username = '$username'";
    $result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($result) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email or username already exists']);
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Insert user data
        $insert_query = "INSERT INTO users (email, username, password, fname, lname, roleID, created_at) 
                        VALUES ('$email', '$username', '$password', '$fname', '$lname', '$roleID', NOW())";

        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception("Error creating user: " . mysqli_error($conn));
        }

        // Get the inserted user's ID
        $userID = mysqli_insert_id($conn);

        // Handle role-specific inserts
        if ($roleID == 1) {
            // Insert into supervisors table
            $supervisor_query = "INSERT INTO supervisors (userID) VALUES ($userID)";
            if (!mysqli_query($conn, $supervisor_query)) {
                throw new Exception("Error creating supervisor: " . mysqli_error($conn));
            }
            // Also insert into employees table for roleID 1
            $employee_query = "INSERT INTO employees (userID, roleID) VALUES ($userID, $roleID)";
            if (!mysqli_query($conn, $employee_query)) {
                throw new Exception("Error creating employee: " . mysqli_error($conn));
            }

        } elseif ($roleID == 2) {
            // Insert into employees table
            $employee_query = "INSERT INTO employees (userID, roleID) VALUES ($userID, $roleID)";
            if (!mysqli_query($conn, $employee_query)) {
                throw new Exception("Error creating employee: " . mysqli_error($conn));
            }
        }

        // If we got here, commit the transaction
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'User created successfully']);
        header("Location: ../views/createuser.php");
        exit();
    } catch (Exception $e) {
        // If there was an error, rollback the transaction
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?> 