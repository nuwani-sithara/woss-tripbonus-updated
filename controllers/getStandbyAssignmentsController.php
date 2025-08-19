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

$response = ['success' => false, 'data' => [], 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Get all checked-in standby assignments (status = 1)
    $getAssignmentsStmt = $conn->prepare("
        SELECT 
            sa.EAID,
            sa.empID,
            sa.standby_attendanceID,
            sa.checkOutDate,
            sa.status,
            sa.standby_count,
            u.fname,
            u.lname,
            u.username,
            u.email,
            v.vessel_name,
            sta.date as checkInDate
        FROM standbyassignments sa
        JOIN employees e ON sa.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN standby_attendance sta ON sa.standby_attendanceID = sta.standby_attendanceID
        LEFT JOIN vessels v ON sta.vesselID = v.vesselID
        WHERE sa.status = 1
        ORDER BY sta.date DESC, u.fname, u.lname
    ");
    
    if (!$getAssignmentsStmt) {
        throw new Exception("Prepare failed for assignments query: " . $conn->error);
    }

    if (!$getAssignmentsStmt->execute()) {
        throw new Exception("Execute failed for assignments query: " . $getAssignmentsStmt->error);
    }

    $assignmentsResult = $getAssignmentsStmt->get_result();
    $assignments = [];
    
    while ($assignment = $assignmentsResult->fetch_assoc()) {
        // Calculate duration
        $checkInDate = new DateTime($assignment['checkInDate']);
        $currentDate = new DateTime();
        $interval = $checkInDate->diff($currentDate);
        $duration = $interval->days;
        
        $assignments[] = [
            'EAID' => $assignment['EAID'],
            'empID' => $assignment['empID'],
            'standbyAttendanceID' => $assignment['standby_attendanceID'],
            'checkOutDate' => $assignment['checkOutDate'],
            'status' => $assignment['status'],
            'standby_count' => $assignment['standby_count'],
            'fname' => $assignment['fname'],
            'lname' => $assignment['lname'],
            'username' => $assignment['username'],
            'email' => $assignment['email'],
            'vessel_name' => $assignment['vessel_name'],
            'checkInDate' => $assignment['checkInDate'],
            'duration' => $duration,
            'status_text' => 'Checked In'
        ];
    }
    
    $assignmentsResult->free();

    $response['success'] = true;
    $response['data'] = $assignments;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("Error in getStandbyAssignmentsController: " . $e->getMessage());
} finally {
    if (isset($getAssignmentsStmt)) $getAssignmentsStmt->close();
    $conn->close();
    echo json_encode($response);
    exit();
}