<?php
session_start();
include '../config/dbConnect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'data' => [], 'error' => ''];

try {
    // Get all divers who are either:
    // 1. Not in standbyassignments at all, OR
    // 2. Have status = 0 (checked out)
    $sql = "
        SELECT 
            u.userID, 
            u.fname, 
            u.lname, 
            u.username, 
            u.email, 
            u.created_at,
            e.empID,
            u.roleID,
            CASE 
                WHEN u.roleID = 2 THEN 'Diver'
                WHEN u.roleID = 8 THEN 'Mechanic'
                WHEN u.roleID = 9 THEN 'Marine Engineer'
                ELSE 'Unknown'
            END as role_name
        FROM users u
        JOIN employees e ON u.userID = e.userID
        LEFT JOIN (
            SELECT empID, status 
            FROM standbyassignments 
            WHERE status = 1
            GROUP BY empID
        ) sa ON e.empID = sa.empID
        WHERE u.roleID IN (2, 8, 9)
        AND sa.empID IS NULL
        ORDER BY u.fname, u.lname
    ";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $divers = [];
    while ($row = $result->fetch_assoc()) {
        $divers[] = $row;
    }
    
    $response['success'] = true;
    $response['data'] = $divers;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} finally {
    $conn->close();
    echo json_encode($response);
}