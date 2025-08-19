<?php
include_once __DIR__ . '/../config/dbConnect.php';

function getSystemUsers() {
    global $conn;
    $sql = "SELECT u.userID, u.email, u.username, u.fname, u.lname, u.roleID, u.rateID, u.created_at, r.rate_name FROM users u LEFT JOIN rates r ON u.rateID = r.rateID WHERE u.roleID IN (1,3,4,5,6,7)";
    $result = $conn->query($sql);
    $users = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    return $users;
}

function getSystemUsersCount() {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM users WHERE roleID IN (1,3,4,5,6,7)";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
} 