<?php
error_reporting(0);
session_start();
include '../config/dbConnect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Basic validation
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both username and password.';
        header('Location: ../index.php');
        exit();
    }

    // Prepare and execute query
    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? AND password = ?');
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Debug information
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        // echo "User data from database:<br>";
        // var_dump($user);
        
        // Set session variables
        $_SESSION['userID'] = $user['userID'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['roleID'] = $user['roleID'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'];
        
        // Debug session after setting
        // echo "<br>Session after setting:<br>";
        // var_dump($_SESSION);
        
        // Redirect to dashboard or home
        $accessRole = $_SESSION['roleID'];
        switch($accessRole) {
            case 1: 
                header("Location: ../views/supervisordashboard.php");
                break;
            case 3: 
                header("Location: ../views/attendanceverify.php");
                break;
            case 4: 
                header('Location: ../views/admindashboard.php');
                // die('<a href="../views/admindashboard.php">Click here</a>');
                //echo '<script>window.location.href = " ../views/admindashboard.php";</script>';

                break;
            case 5: 
                header("Location: ../views/paymentverification.php");
                break;
            case 6: 
                header("Location: ../views/directorverfication.php");
                break;
            case 7: 
                header("Location: ../views/directordashboard.php");
                break;
            case 13: 
                header("Location: ../views/supervisorinchargedashboard.php");
                break;
            default:
                header("Location: ../index.php");
                break;
        }
        exit();
    } else {
        $_SESSION['login_error'] = 'Invalid username or password.';
        header('Location: ../index.php');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
} 