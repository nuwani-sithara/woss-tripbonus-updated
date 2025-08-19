<?php
include_once __DIR__ . '/../config/dbConnect.php';

function getAllUsers() {
    global $conn;
    $sql = "SELECT u.userID, u.email, u.username, u.fname, u.lname, u.password, u.roleID, u.rateID, u.created_at, r.rate_name FROM users u LEFT JOIN rates r ON u.rateID = r.rateID";
    $result = $conn->query($sql);
    $users = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    return $users;
}

function getAllRoles() {
    global $conn;
    $sql = "SELECT roleID, roleName FROM roles";
    $result = $conn->query($sql);
    $roles = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
    }
    return $roles;
} 