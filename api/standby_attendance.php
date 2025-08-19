<?php
// api/standby_attendance.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../config/dbConnect.php';

$response = ['success' => false, 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Read JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload');
    }

    // Validate required parameters
    if (empty($data['userIDs']) || !is_array($data['userIDs'])) {
        throw new Exception('Missing or invalid userIDs');
    }
    if (empty($data['vesselID'])) {
        throw new Exception('Missing vesselID');
    }
    if (empty($data['supervisorUserID'])) { 
        throw new Exception('Missing supervisorUserID');
    }

    $userIDs = $data['userIDs'];
    $vesselID = (int) $data['vesselID'];
    $supervisorUserID = (int) $data['supervisorUserID'];

    $conn->begin_transaction();

    // Insert into standby_attendance
    $stmt = $conn->prepare("
        INSERT INTO standby_attendance (date, attendance_status, approved_attendance_status, approved_date, vesselID)
        VALUES (?, ?, ?, ?, ?)
    ");
    $date = date('Y-m-d');
    $status = 1;
    $approved_status = 1;
    $approved_date = date('Y-m-d H:i:s');
    $stmt->bind_param('siisi', $date, $status, $approved_status, $approved_date, $vesselID);
    $stmt->execute();
    $standbyAttendanceID = $stmt->insert_id;
    $stmt->close();

    // Prepare statements
    $empStmt = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
    $insertStmt = $conn->prepare("INSERT INTO standbyassignments (standby_attendanceID, empID, status) VALUES (?, ?, ?)");
    $checkStmt = $conn->prepare("
        SELECT status FROM standbyassignments sa
        JOIN employees e ON sa.empID = e.empID
        WHERE e.userID = ? AND sa.status = 1
    ");

    $eaidMap = [];

    // Assign divers
    foreach ($userIDs as $userID) {
        $empStmt->bind_param('i', $userID);
        $empStmt->execute();
        $result = $empStmt->get_result();
        $employee = $result->fetch_assoc();
        if (!$employee) {
            throw new Exception("No employee found for userID: $userID");
        }
        $status = 1;
        $insertStmt->bind_param('iii', $standbyAttendanceID, $employee['empID'], $status);
        $insertStmt->execute();
        $eaidMap[$userID] = $insertStmt->insert_id;
    }

    // Handle supervisor
    $empStmt->bind_param('i', $supervisorUserID);
    $empStmt->execute();
    $result = $empStmt->get_result();
    $supervisor = $result->fetch_assoc();
    if (!$supervisor) {
        throw new Exception("No employee found for supervisor userID: $supervisorUserID");
    }

    $checkStmt->bind_param('i', $supervisorUserID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows === 0) {
        $status = 1;
        $insertStmt->bind_param('iii', $standbyAttendanceID, $supervisor['empID'], $status);
        $insertStmt->execute();
        $eaidMap[$supervisorUserID] = $insertStmt->insert_id;
    }

    $conn->commit();
    $response['success'] = true;
    $response['standbyAttendanceID'] = $standbyAttendanceID;
    $response['eaidMap'] = $eaidMap;

} catch (Exception $e) {
    $conn->rollback();
    $response['error'] = $e->getMessage();
} finally {
    if (isset($empStmt) && $empStmt instanceof mysqli_stmt) $empStmt->close();
    if (isset($insertStmt) && $insertStmt instanceof mysqli_stmt) $insertStmt->close();
    if (isset($checkStmt) && $checkStmt instanceof mysqli_stmt) $checkStmt->close();
    $conn->close();
    echo json_encode($response);
}
