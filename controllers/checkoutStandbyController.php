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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['EAID'])) {
        throw new Exception('Missing EAID parameter');
    }

    $EAID = (int)$_POST['EAID'];
    $checkOutDate = date('Y-m-d H:i:s'); // Current date and time

    $conn->begin_transaction();

    // Step 1: Get standby_attendanceID and empID from standbyassignments
    $getAssignmentStmt = $conn->prepare("SELECT standby_attendanceID, empID FROM standbyassignments WHERE EAID = ?");
    if (!$getAssignmentStmt) {
        throw new Exception("Prepare failed for assignment query: " . $conn->error);
    }

    $getAssignmentStmt->bind_param('i', $EAID);
    if (!$getAssignmentStmt->execute()) {
        throw new Exception("Execute failed for assignment query: " . $getAssignmentStmt->error);
    }

    $assignmentResult = $getAssignmentStmt->get_result();
    $assignment = $assignmentResult->fetch_assoc();
    $assignmentResult->free();

    if (!$assignment) {
        throw new Exception("No standby assignment found for EAID: $EAID");
    }

    $standbyAttendanceID = $assignment['standby_attendanceID'];
    $empID = $assignment['empID'];

    // Step 2: Get checkInDate from standby_attendance table
    $getCheckInStmt = $conn->prepare("SELECT date FROM standby_attendance WHERE standby_attendanceID = ?");
    if (!$getCheckInStmt) {
        throw new Exception("Prepare failed for check-in query: " . $conn->error);
    }

    $getCheckInStmt->bind_param('i', $standbyAttendanceID);
    if (!$getCheckInStmt->execute()) {
        throw new Exception("Execute failed for check-in query: " . $getCheckInStmt->error);
    }

    $checkInResult = $getCheckInStmt->get_result();
    $checkInData = $checkInResult->fetch_assoc();
    $checkInResult->free();

    if (!$checkInData) {
        throw new Exception("No standby attendance record found for ID: $standbyAttendanceID");
    }

    $checkInDate = $checkInData['date'];

    // Step 3: Calculate standby_count (Y) = difference between checkIn and checkOut
    $checkInDateTime = new DateTime($checkInDate);
    $checkOutDateTime = new DateTime($checkOutDate);
    $interval = $checkInDateTime->diff($checkOutDateTime);
    $standbyCountY = $interval->days; // Total days difference

    // Step 4: Check if tripID exists for this standby_attendanceID in job_attendance
    $getTripStmt = $conn->prepare("SELECT tripID FROM job_attendance WHERE standby_attendanceID = ?");
    if (!$getTripStmt) {
        throw new Exception("Prepare failed for trip query: " . $conn->error);
    }

    $getTripStmt->bind_param('i', $standbyAttendanceID);
    if (!$getTripStmt->execute()) {
        throw new Exception("Execute failed for trip query: " . $getTripStmt->error);
    }

    $tripResult = $getTripStmt->get_result();
    $tripIDs = [];
    while ($trip = $tripResult->fetch_assoc()) {
        $tripIDs[] = $trip['tripID'];
    }
    $tripResult->free();

    $tripCountX = 0; // Initialize trip count

    // Step 5: If tripIDs exist, count distinct dates for this empID
    if (!empty($tripIDs)) {
        // Prepare placeholders for tripIDs
        $placeholders = str_repeat('?,', count($tripIDs) - 1) . '?';
        // Build types string for bind_param
        $types = 'i' . str_repeat('i', count($tripIDs));
        // Build params array for bind_param
        $params = array_merge([$empID], $tripIDs);

        // Query: count distinct dates from job_attendance for this empID and standby_attendanceID
        $query = "SELECT COUNT(DISTINCT ja.date) as trip_days
                  FROM jobassignments jass
                  JOIN job_attendance ja ON jass.tripID = ja.tripID
                  WHERE jass.empID = ? AND ja.standby_attendanceID = ? AND jass.tripID IN ($placeholders)";
        $types = 'ii' . str_repeat('i', count($tripIDs));
        $params = array_merge([$empID, $standbyAttendanceID], $tripIDs);
        $getTripCountStmt = $conn->prepare($query);
        if (!$getTripCountStmt) {
            throw new Exception("Prepare failed for trip count query: " . $conn->error);
        }
        $getTripCountStmt->bind_param($types, ...$params);
        if (!$getTripCountStmt->execute()) {
            throw new Exception("Execute failed for trip count query: " . $getTripCountStmt->error);
        }
        $tripCountResult = $getTripCountStmt->get_result();
        $tripCountData = $tripCountResult->fetch_assoc();
        $tripCountResult->free();
        $tripCountX = $tripCountData['trip_days'];
    }

    // Step 6: Calculate actual standby count = Y - X
    $actualStandbyCount = $standbyCountY - $tripCountX;

    // Ensure standby count is not negative
    if ($actualStandbyCount < 0) {
        $actualStandbyCount = 0;
    }

    // Step 7: Update standbyassignments table with checkout data
    $updateStmt = $conn->prepare("UPDATE standbyassignments SET checkOutDate = ?, status = 0, standby_count = ? WHERE EAID = ?");
    if (!$updateStmt) {
        throw new Exception("Prepare failed for update query: " . $conn->error);
    }

    $status = 0; // 0 means checkout
    $updateStmt->bind_param('sii', $checkOutDate, $actualStandbyCount, $EAID);
    if (!$updateStmt->execute()) {
        throw new Exception("Execute failed for update query: " . $updateStmt->error);
    }

    $conn->commit();

    $response['success'] = true;
    $response['message'] = 'Checkout completed successfully';
    $response['data'] = [
        'EAID' => $EAID,
        'standbyAttendanceID' => $standbyAttendanceID,
        'empID' => $empID,
        'checkInDate' => $checkInDate,
        'checkOutDate' => $checkOutDate,
        'standbyCountY' => $standbyCountY,
        'tripCountX' => $tripCountX,
        'actualStandbyCount' => $actualStandbyCount
    ];

} catch (Exception $e) {
    $conn->rollback();
    $response['error'] = $e->getMessage();
    error_log("Error in checkoutStandbyController: " . $e->getMessage());
} finally {
    // Close all statements
    if (isset($getAssignmentStmt)) $getAssignmentStmt->close();
    if (isset($getCheckInStmt)) $getCheckInStmt->close();
    if (isset($getTripStmt)) $getTripStmt->close();
    if (isset($getTripCountStmt)) $getTripCountStmt->close();
    if (isset($updateStmt)) $updateStmt->close();

    $conn->close();
    echo json_encode($response);
    exit();
}
