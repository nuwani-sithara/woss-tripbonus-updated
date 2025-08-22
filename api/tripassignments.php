<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include '../config/dbConnect.php';
session_start();

// ✅ Only supervisor (roleID = 1)
// if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 1) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Access denied']);
//     exit();
// }

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':   // 🔹 Get trips & divers
            $jobID = isset($_GET['jobID']) ? (int)$_GET['jobID'] : 0;
            if ($jobID <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid Job ID']);
                exit();
            }

            // Standby divers
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

            // Latest standby attendance
            $standbyQuery = "SELECT standby_attendanceID FROM standby_attendance ORDER BY date DESC LIMIT 1";
            $standbyResult = $conn->query($standbyQuery);
            $standbyRow = $standbyResult->fetch_assoc();
            $standby_attendanceID = $standbyRow['standby_attendanceID'] ?? null;

            // Other divers
            $other_divers = [];
            $allDiversQuery = "
                SELECT u.userID, u.fname, u.lname 
                FROM users u 
                WHERE u.roleID IN (2, 8, 9) 
                  AND u.userID NOT IN (
                      SELECT DISTINCT u.userID
                      FROM standbyassignments sa
                      JOIN employees e ON sa.empID = e.empID
                      JOIN users u ON e.userID = u.userID
                      WHERE sa.status = 1
                        AND u.roleID IN (2, 8, 9)
                  )
            ";
            $allDiversResult = $conn->query($allDiversQuery);
            while ($diver = $allDiversResult->fetch_assoc()) {
                $other_divers[] = $diver;
            }

            // Trips & assigned divers
            $trips = [];
            $tripsQuery = "
                SELECT t.tripID, t.trip_date, t.jobID
                FROM trips t
                WHERE t.jobID = ?
                ORDER BY t.trip_date
            ";
            $stmt = $conn->prepare($tripsQuery);
            $stmt->bind_param("i", $jobID);
            $stmt->execute();
            $tripsResult = $stmt->get_result();

            while ($trip = $tripsResult->fetch_assoc()) {
                $tripID = $trip['tripID'];

                $assignedQuery = "
                    SELECT u.userID, u.fname, u.lname 
                    FROM jobassignments ja
                    JOIN employees e ON ja.empID = e.empID
                    JOIN users u ON e.userID = u.userID
                    WHERE ja.tripID = ?
                ";
                $stmt2 = $conn->prepare($assignedQuery);
                $stmt2->bind_param("i", $tripID);
                $stmt2->execute();
                $assignedResult = $stmt2->get_result();

                $assigned_employees = [];
                while ($emp = $assignedResult->fetch_assoc()) {
                    $assigned_employees[] = $emp;
                }

                $trip['assigned_employees'] = $assigned_employees;
                $trips[] = $trip;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'jobID' => $jobID,
                'standby_attendanceID' => $standby_attendanceID,
                'standby_divers' => $standby_divers,
                'other_divers' => $other_divers,
                'trips' => $trips
            ]);
            break;

        case 'POST':  // 🔹 Assign diver(s) to trip
            $data = json_decode(file_get_contents("php://input"), true);
            $tripID = $data['tripID'] ?? 0;
            $userIDs = $data['userIDs'] ?? [];

            if ($tripID <= 0 || empty($userIDs)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'tripID and userIDs required']);
                exit();
            }

            foreach ($userIDs as $uid) {
                $empStmt = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
                $empStmt->bind_param("i", $uid);
                $empStmt->execute();
                $empResult = $empStmt->get_result();
                $empRow = $empResult->fetch_assoc();
                $empID = $empRow['empID'] ?? null;

                if ($empID) {
                    $assignStmt = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                    $assignStmt->bind_param("ii", $tripID, $empID);
                    $assignStmt->execute();
                }
            }

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Divers assigned successfully']);
            break;

        case 'DELETE': // 🔹 Remove diver from trip
            $data = json_decode(file_get_contents("php://input"), true);
            $tripID = $data['tripID'] ?? 0;
            $userID = $data['userID'] ?? 0;

            if ($tripID <= 0 || $userID <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'tripID and userID required']);
                exit();
            }

            $empStmt = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
            $empStmt->bind_param("i", $userID);
            $empStmt->execute();
            $empResult = $empStmt->get_result();
            $empRow = $empResult->fetch_assoc();
            $empID = $empRow['empID'] ?? null;

            if ($empID) {
                $delStmt = $conn->prepare("DELETE FROM jobassignments WHERE tripID = ? AND empID = ?");
                $delStmt->bind_param("ii", $tripID, $empID);
                $delStmt->execute();

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Diver removed from trip']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Error in tripAssignments API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
