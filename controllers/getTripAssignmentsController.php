<?php
include '../config/dbConnect.php';
session_start();

// Check if user is logged in and has role_id = 1 (supervisor)
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$jobID = $_GET['jobID'] ?? 0;
if ($jobID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Job ID']);
    exit();
}

try {
    // Get all checked-in standby divers from all standby attendance records
    $standby_divers = [];
    $diverQuery = "
        SELECT DISTINCT u.userID, u.fname, u.lname
        FROM standbyassignments sa
        JOIN employees e ON sa.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN standby_attendance sta ON sa.standby_attendanceID = sta.standby_attendanceID
        WHERE u.roleID IN (2, 8, 9)
          AND sa.status = 1
    ";
    $diverResult = $conn->query($diverQuery);
    while ($diver = $diverResult->fetch_assoc()) {
        $standby_divers[] = $diver;
    }

    // Still get the latest standby_attendanceID for reference
    $standbyQuery = "SELECT standby_attendanceID FROM standby_attendance ORDER BY date DESC LIMIT 1";
    $standbyResult = $conn->query($standbyQuery);
    $standbyRow = $standbyResult->fetch_assoc();
    $standby_attendanceID = $standbyRow['standby_attendanceID'] ?? null;

    // Get other divers (not currently checked-in to any standby)
    $other_divers = [];
    $allDiversQuery = "SELECT u.userID, u.fname, u.lname 
                       FROM users u 
                       WHERE u.roleID IN (2, 8, 9) 
                       AND u.userID NOT IN (
                           SELECT DISTINCT u.userID
                           FROM standbyassignments sa
                           JOIN employees e ON sa.empID = e.empID
                           JOIN users u ON e.userID = u.userID
                           WHERE sa.status = 1
                             AND u.roleID IN (2, 8, 9)
                       )";
    $allDiversResult = $conn->query($allDiversQuery);
    while ($diver = $allDiversResult->fetch_assoc()) {
        $other_divers[] = $diver;
    }

    // Get trips for this job with assigned employees
    $tripsQuery = "SELECT t.tripID, t.trip_date, t.jobID
                   FROM trips t
                   WHERE t.jobID = $jobID
                   ORDER BY t.trip_date";
    $tripsResult = $conn->query($tripsQuery);
    
    $trips = [];
    while ($trip = $tripsResult->fetch_assoc()) {
        $tripID = $trip['tripID'];
        
        // Get assigned employees for this trip
        $assignedQuery = "SELECT u.userID, u.fname, u.lname 
                          FROM jobassignments ja
                          JOIN employees e ON ja.empID = e.empID
                          JOIN users u ON e.userID = u.userID
                          WHERE ja.tripID = $tripID";
        $assignedResult = $conn->query($assignedQuery);
        
        $assigned_employees = [];
        while ($emp = $assignedResult->fetch_assoc()) {
            $assigned_employees[] = $emp;
        }
        
        $trip['assigned_employees'] = $assigned_employees;
        $trips[] = $trip;
    }

    echo json_encode([
        'success' => true,
        'trips' => $trips,
        'standby_divers' => $standby_divers,
        'other_divers' => $other_divers,
        'standby_attendanceID' => $standby_attendanceID
    ]);

} catch (Exception $e) {
    error_log("Error in getTripAssignmentsController: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>