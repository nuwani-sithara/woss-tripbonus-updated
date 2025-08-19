<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php_errors.log');

header('Content-Type: application/json');

include '../config/dbConnect.php';

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

date_default_timezone_set('Asia/Colombo');

$response = ['success' => false, 'error' => ''];
$stmt = $empStmt = $insertStmt = $checkStmt = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['userIDs'])) {
        throw new Exception('Missing userIDs parameter');
    }

    if (!isset($_POST['vesselID'])) {
        throw new Exception('Missing vesselID parameter');
    }

    // Get the supervisor's userID from session
    if (!isset($_SESSION['userID'])) {
        throw new Exception('Supervisor not logged in');
    }
    $supervisorUserID = $_SESSION['userID'];

    $userIDs = $_POST['userIDs'];
    $vesselID = $_POST['vesselID'];
    if (is_string($userIDs)) {
        $userIDs = json_decode($userIDs, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid userIDs format');
        }
    }

    if (!is_array($userIDs)) {
        $userIDs = [$userIDs];
    }

    error_log("Processing userIDs: " . print_r($userIDs, true));

    $conn->begin_transaction();

    // Insert into standby_attendance table
    $stmt = $conn->prepare("INSERT INTO standby_attendance 
                            (date, attendance_status, approved_attendance_status, approved_date, vesselID) 
                            VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $date = date('Y-m-d');
    $status = 1;
    $approved_status = 1;
    $approved_date = date('Y-m-d H:i:s');;

    $stmt->bind_param('siisi', $date, $status, $approved_status, $approved_date, $vesselID);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $standbyAttendanceID = $stmt->insert_id;
    $stmt->close();
    $stmt = null;

    // Prepare employee fetch and attendance insert
    $empStmt = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
    if (!$empStmt) {
        throw new Exception("Employee query prepare failed: " . $conn->error);
    }

    $insertStmt = $conn->prepare("INSERT INTO standbyassignments (standby_attendanceID, empID, status) VALUES (?, ?, ?)");
    if (!$insertStmt) {
        throw new Exception("Insert query prepare failed: " . $conn->error);
    }

    // Prepare statement to check supervisor's status
    $checkStmt = $conn->prepare("SELECT status FROM standbyassignments sa 
                                JOIN employees e ON sa.empID = e.empID 
                                WHERE e.userID = ? AND sa.status = 1");
    if (!$checkStmt) {
        throw new Exception("Check query prepare failed: " . $conn->error);
    }

    $eaidMap = [];
    foreach ($userIDs as $userID) {
        $empStmt->bind_param('i', $userID);
        if (!$empStmt->execute()) {
            throw new Exception("Employee query execute failed: " . $empStmt->error);
        }

        $result = $empStmt->get_result();
        $employee = $result->fetch_assoc();
        $result->free();

        if (!$employee) {
            throw new Exception("No employee found for userID: $userID");
        }

        $status = 1; // 1 means checkin
        $insertStmt->bind_param('iii', $standbyAttendanceID, $employee['empID'], $status);
        if (!$insertStmt->execute()) {
            throw new Exception("Insert execute failed: " . $insertStmt->error);
        }
        $eaidMap[$userID] = $insertStmt->insert_id; // Store EAID for this user
    }

    // Handle supervisor separately
    $empStmt->bind_param('i', $supervisorUserID);
    if (!$empStmt->execute()) {
        throw new Exception("Employee query execute failed for supervisor: " . $empStmt->error);
    }

    $result = $empStmt->get_result();
    $supervisor = $result->fetch_assoc();
    $result->free();

    if (!$supervisor) {
        throw new Exception("No employee found for supervisor userID: $supervisorUserID");
    }

    // Check if supervisor is already checked in (status = 1)
    $checkStmt->bind_param('i', $supervisorUserID);
    if (!$checkStmt->execute()) {
        throw new Exception("Check query execute failed: " . $checkStmt->error);
    }

    $checkResult = $checkStmt->get_result();
    $isCheckedIn = $checkResult->num_rows > 0;
    $checkResult->free();

    if (!$isCheckedIn) {
        // Only insert supervisor if not already checked in
        $status = 1; // 1 means checkin
        $insertStmt->bind_param('iii', $standbyAttendanceID, $supervisor['empID'], $status);
        if (!$insertStmt->execute()) {
            throw new Exception("Insert execute failed for supervisor: " . $insertStmt->error);
        }
        $eaidMap[$supervisorUserID] = $insertStmt->insert_id;
    }

    $conn->commit();
    $response['success'] = true;
    $response['standbyAttendanceID'] = $standbyAttendanceID;
    $response['eaidMap'] = $eaidMap;

} catch (Exception $e) {
    $conn->rollback();
    $response['error'] = $e->getMessage();
    error_log("Error in controller: " . $e->getMessage());
} finally {
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if ($empStmt instanceof mysqli_stmt) {
        $empStmt->close();
    }
    if ($insertStmt instanceof mysqli_stmt) {
        $insertStmt->close();
    }
    if ($checkStmt instanceof mysqli_stmt) {
        $checkStmt->close();
    }

    $conn->close();

    echo json_encode($response);
    exit();
}