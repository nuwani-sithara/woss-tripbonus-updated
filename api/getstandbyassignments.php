<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable direct error output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

include '../config/dbConnect.php';

$response = [
    'success' => false,
    'data' => [],
    'error' => ''
];

// ✅ Check DB connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit();
}

try {
    // ✅ Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Invalid request method. Use GET.');
    }

    // ✅ Prepare query
    $sql = "
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
            sta.date AS checkInDate
        FROM standbyassignments sa
        JOIN employees e ON sa.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN standby_attendance sta ON sa.standby_attendanceID = sta.standby_attendanceID
        LEFT JOIN vessels v ON sta.vesselID = v.vesselID
        WHERE sa.status = 1
        ORDER BY sta.date DESC, u.fname, u.lname
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // ✅ Execute query
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $assignments = [];

    while ($row = $result->fetch_assoc()) {
        $checkInDate = new DateTime($row['checkInDate']);
        $currentDate = new DateTime();
        $interval = $checkInDate->diff($currentDate);
        $duration = $interval->days;

        $assignments[] = [
            'EAID' => $row['EAID'],
            'empID' => $row['empID'],
            'standbyAttendanceID' => $row['standby_attendanceID'],
            'checkOutDate' => $row['checkOutDate'],
            'status' => $row['status'],
            'standby_count' => $row['standby_count'],
            'fname' => $row['fname'],
            'lname' => $row['lname'],
            'username' => $row['username'],
            'email' => $row['email'],
            'vessel_name' => $row['vessel_name'],
            'checkInDate' => $row['checkInDate'],
            'duration' => $duration,
            'status_text' => 'Checked In'
        ];
    }

    $response['success'] = true;
    $response['data'] = $assignments;

    http_response_code(200);

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("Error in getStandbyAssignments API: " . $e->getMessage());
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
    echo json_encode($response);
    exit();
}
