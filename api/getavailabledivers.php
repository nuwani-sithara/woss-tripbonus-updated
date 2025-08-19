<?php
// session_start();
include '../config/dbConnect.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

$response = [
    'success' => false,
    'data' => [],
    'error' => ''
];

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method. Use GET.');
    }

    // Get all divers who are either not in standbyassignments or checked out
    $sql = "
        SELECT 
            u.userID, 
            u.fname, 
            u.lname, 
            u.username, 
            u.email, 
            u.created_at,
            e.empID
        FROM users u
        JOIN employees e ON u.userID = e.userID
        LEFT JOIN (
            SELECT empID, status 
            FROM standbyassignments 
            WHERE status = 1
            GROUP BY empID
        ) sa ON e.empID = sa.empID
        WHERE u.roleID = 2 
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
    echo json_encode($response, JSON_PRETTY_PRINT);
}
