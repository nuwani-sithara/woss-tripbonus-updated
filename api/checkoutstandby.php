<?php
// checkoutStandby.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include '../config/dbConnect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

date_default_timezone_set('Asia/Colombo');

$response = ['success' => false, 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }

    // Get raw JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['EAID'])) {
        http_response_code(400);
        throw new Exception('Missing EAID parameter');
    }

    $EAID = (int)$input['EAID'];
    $checkOutDate = date('Y-m-d H:i:s');

    $conn->begin_transaction();

    // Step 1: Get standby_attendanceID and empID
    $getAssignmentStmt = $conn->prepare("
        SELECT standby_attendanceID, empID 
        FROM standbyassignments 
        WHERE EAID = ?
    ");
    $getAssignmentStmt->bind_param('i', $EAID);
    $getAssignmentStmt->execute();
    $assignment = $getAssignmentStmt->get_result()->fetch_assoc();

    if (!$assignment) {
        http_response_code(404);
        throw new Exception("No standby assignment found for EAID: $EAID");
    }

    $standbyAttendanceID = $assignment['standby_attendanceID'];
    $empID = $assignment['empID'];

    // Step 2: Get check-in date
    $getCheckInStmt = $conn->prepare("SELECT date FROM standby_attendance WHERE standby_attendanceID = ?");
    $getCheckInStmt->bind_param('i', $standbyAttendanceID);
    $getCheckInStmt->execute();
    $checkInData = $getCheckInStmt->get_result()->fetch_assoc();

    if (!$checkInData) {
        throw new Exception("No standby attendance record found for ID: $standbyAttendanceID");
    }

    $checkInDate = $checkInData['date'];

    // Step 3: Calculate standby count (Y)
    $checkInDateTime = new DateTime($checkInDate);
    $checkOutDateTime = new DateTime($checkOutDate);
    $standbyCountY = $checkInDateTime->diff($checkOutDateTime)->days;

    // Step 4: Get trip IDs for this standby
    $getTripStmt = $conn->prepare("SELECT tripID FROM job_attendance WHERE standby_attendanceID = ?");
    $getTripStmt->bind_param('i', $standbyAttendanceID);
    $getTripStmt->execute();
    $tripIDs = [];
    $tripResult = $getTripStmt->get_result();
    while ($trip = $tripResult->fetch_assoc()) {
        $tripIDs[] = $trip['tripID'];
    }

    // Step 5: Calculate trip count (X)
    $tripCountX = 0;
    if (!empty($tripIDs)) {
        $placeholders = implode(',', array_fill(0, count($tripIDs), '?'));
        $types = 'ii' . str_repeat('i', count($tripIDs));
        $params = array_merge([$empID, $standbyAttendanceID], $tripIDs);

        $query = "
            SELECT COUNT(DISTINCT ja.date) as trip_days
            FROM jobassignments jass
            JOIN job_attendance ja ON jass.tripID = ja.tripID
            WHERE jass.empID = ? AND ja.standby_attendanceID = ? AND jass.tripID IN ($placeholders)
        ";
        $getTripCountStmt = $conn->prepare($query);
        $getTripCountStmt->bind_param($types, ...$params);
        $getTripCountStmt->execute();
        $tripCountData = $getTripCountStmt->get_result()->fetch_assoc();
        $tripCountX = $tripCountData['trip_days'];
    }

    // Step 6: Actual standby count = Y - X
    $actualStandbyCount = max(0, $standbyCountY - $tripCountX);

    // Step 7: Update checkout data
    $updateStmt = $conn->prepare("
        UPDATE standbyassignments 
        SET checkOutDate = ?, status = 0, standby_count = ? 
        WHERE EAID = ?
    ");
    $updateStmt->bind_param('sii', $checkOutDate, $actualStandbyCount, $EAID);
    $updateStmt->execute();

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

    http_response_code(200);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    $response['error'] = $e->getMessage();
} finally {
    $conn->close();
    echo json_encode($response);
}
