<?php
session_start();
header("Content-Type: application/json");
include '../config/dbConnect.php';

$latestStandbyEmployees = [];
$sql = "SELECT 
          sa.standby_attendanceID,
          sa.date,
          ea.empID,
          u.fname,
          u.lname,
          r.role_name
        FROM standby_attendance sa
        JOIN standbyassignments ea ON sa.standby_attendanceID = ea.standby_attendanceID
        JOIN employees e ON ea.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN roles r ON e.roleID = r.roleID
        WHERE ea.status = 1
          AND u.roleID IN (2, 8, 9)
        ORDER BY sa.date DESC, u.fname, u.lname
        LIMIT 10";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $latestStandbyEmployees[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "data" => $latestStandbyEmployees,
    "count" => count($latestStandbyEmployees)
]);
