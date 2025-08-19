<?php
header('Content-Type: application/json');
include '../config/dbConnect.php';

$response = ['success' => false, 'data' => []];

try {
    // Query to get all employees with their user details
    $query = "SELECT e.empID, u.userID, u.fname, u.lname 
              FROM employees e
              JOIN users u ON e.userID = u.userID
              ORDER BY u.fname, u.lname";
              
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'empID' => $row['empID'],
            'userID' => $row['userID'],
            'name' => $row['fname'] . ' ' . $row['lname']
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $employees;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("Error in getEmployeesController: " . $e->getMessage());
} finally {
    $conn->close();
    echo json_encode($response);
}