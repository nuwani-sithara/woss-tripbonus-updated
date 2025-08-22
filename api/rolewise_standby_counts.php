<?php
session_start();
header("Content-Type: application/json");
include '../config/dbConnect.php';

$roleWiseStandbyCounts = [];
$sql = "SELECT 
          r.roleID,
          r.role_name,
          COUNT(*) as employee_count
        FROM standbyassignments ea
        JOIN employees e ON ea.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN roles r ON e.roleID = r.roleID
        WHERE ea.status = 1
          AND u.roleID IN (2, 8, 9)
        GROUP BY r.roleID, r.role_name
        ORDER BY employee_count DESC";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $roleWiseStandbyCounts[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "data" => $roleWiseStandbyCounts,
    "count" => count($roleWiseStandbyCounts)
]);
