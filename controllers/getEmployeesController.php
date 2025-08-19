<?php
include_once __DIR__ . '/../config/dbConnect.php';

function getEmployees() {
    global $conn;
    $sql = "SELECT e.empID, u.userID, u.email, u.username, u.fname, u.lname, u.roleID, u.rateID, u.created_at, r.rate_name, r.rate, ro.role_name FROM employees e LEFT JOIN users u ON e.userID = u.userID LEFT JOIN rates r ON u.rateID = r.rateID LEFT JOIN roles ro ON e.roleID = ro.roleID";
    $result = $conn->query($sql);
    $employees = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    return $employees;
}

function getEmployeesCount() {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM employees";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
} 