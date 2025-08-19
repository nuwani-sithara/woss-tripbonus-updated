<?php
header('Content-Type: application/json');
include '../config/dbConnect.php';

$response = ['success' => false, 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required field (only empID needed now)
    if (empty($_POST['empID'])) {
        throw new Exception("Missing required field: empID");
    }

    $empID = (int)$_POST['empID'];

    // Start transaction for atomic operations
    $conn->begin_transaction();

    try {
        // 1. Get the latest standby_attendanceID
        $getLatestStmt = $conn->prepare("SELECT standby_attendanceID 
                                       FROM standby_attendance 
                                       ORDER BY standby_attendanceID DESC 
                                       LIMIT 1");
        $getLatestStmt->execute();
        $latestResult = $getLatestStmt->get_result();
        
        if ($latestResult->num_rows === 0) {
            throw new Exception('No standby attendance records found');
        }
        
        $latestRow = $latestResult->fetch_assoc();
        $standbyAttendanceID = $latestRow['standby_attendanceID'];

        // 2. Verify the employee exists
        $checkEmpStmt = $conn->prepare("SELECT empID FROM employees WHERE empID = ?");
        $checkEmpStmt->bind_param('i', $empID);
        $checkEmpStmt->execute();
        $empResult = $checkEmpStmt->get_result();
        
        if ($empResult->num_rows === 0) {
            throw new Exception('Invalid employee ID - does not exist');
        }

        // 3. Check for existing assignment
        $checkAssignmentStmt = $conn->prepare("SELECT * FROM jobreport_preparation 
                                            WHERE standby_attendanceID = ?");
        $checkAssignmentStmt->bind_param('i', $standbyAttendanceID);
        $checkAssignmentStmt->execute();
        $assignmentResult = $checkAssignmentStmt->get_result();
        
        if ($assignmentResult->num_rows > 0) {
            throw new Exception('This standby attendance already has a report preparer assigned');
        }

        // 4. Insert the new record
        $insertStmt = $conn->prepare("INSERT INTO jobreport_preparation 
                                    (standby_attendanceID, report_preparation_by) 
                                    VALUES (?, ?)");
        $insertStmt->bind_param('ii', $standbyAttendanceID, $empID);
        
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to assign report preparation: " . $insertStmt->error);
        }

        $conn->commit();
        
        $response['success'] = true;
        $response['jobreportID'] = $insertStmt->insert_id;
        $response['standbyAttendanceID'] = $standbyAttendanceID;

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("Error in assignReportPreparationController: " . $e->getMessage());
} finally {
    // Close all statements
    if (isset($getLatestStmt)) $getLatestStmt->close();
    if (isset($checkEmpStmt)) $checkEmpStmt->close();
    if (isset($checkAssignmentStmt)) $checkAssignmentStmt->close();
    if (isset($insertStmt)) $insertStmt->close();
    $conn->close();
    echo json_encode($response);
}