<?php
include '../config/dbConnect.php';
session_start();

// Ensure supervisor is logged in
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

try {
    // Get all checked-in standby divers from all standby attendance records
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
    
    $diver_list = [];
    if ($diverResult && $diverResult->num_rows > 0) {
        while ($diver = $diverResult->fetch_assoc()) {
            $diver_list[] = $diver;
        }
    }
    
    // Still get the latest standby_attendanceID for reference
    $standbyQuery = "SELECT standby_attendanceID FROM standby_attendance ORDER BY date DESC LIMIT 1";
    $standbyResult = $conn->query($standbyQuery);
    
    $standby_attendanceID = null;
    if ($standbyResult && $standbyResult->num_rows > 0) {
        $standbyRow = $standbyResult->fetch_assoc();
        $standby_attendanceID = $standbyRow['standby_attendanceID'] ?? null;
    }
    
    echo json_encode([
        'success' => true,
        'standby_attendanceID' => $standby_attendanceID,
        'divers' => $diver_list
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching standby data: ' . $e->getMessage()
    ]);
}
?>