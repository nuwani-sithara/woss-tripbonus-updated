<?php
include '../config/dbConnect.php';
session_start();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

try {
    // Get tripID from URL or POST body
    $tripID = (int)($_GET['tripID'] ?? $_POST['tripID'] ?? 0);
    $standby_attendanceID = (int)($_POST['standby_attendanceID'] ?? 0);
    $divers = $_POST['divers'] ?? [];
    $otherDivers = $_POST['otherDivers'] ?? [];

    if (!$tripID) {
        throw new Exception("tripID is required.");
    }

    // Also allow JSON raw body
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    if ($data) {
        $standby_attendanceID = $data['standby_attendanceID'] ?? $standby_attendanceID;
        $divers = $data['divers'] ?? $divers;
        $otherDivers = $data['otherDivers'] ?? $otherDivers;
        $providedDate = $data['date'] ?? null;
    } else {
        $providedDate = $_POST['date'] ?? null;
    }

    $conn->begin_transaction();

    // Clear existing assignments
    $stmt = $conn->prepare("DELETE FROM jobassignments WHERE tripID = ?");
    $stmt->bind_param("i", $tripID);
    $stmt->execute();
    $stmt->close();

    // Function to assign divers
    function assignDivers($conn, $divers, $tripID) {
        foreach ($divers as $userID) {
            $userID = (int)$userID;
            if ($userID <= 0) continue;

            $stmt = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $stmt->close();
                throw new Exception("No employee record found for userID $userID");
            }
            $empID = $result->fetch_assoc()['empID'];
            $stmt->close();

            $assign = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
            $assign->bind_param("ii", $tripID, $empID);
            $assign->execute();
            $assign->close();
        }
    }

    assignDivers($conn, $divers, $tripID);
    assignDivers($conn, $otherDivers, $tripID);

    // Assign job creator (supervisor)
    $creatorID = $_SESSION['userID'] ?? (int)($_GET['userID'] ?? $_POST['userID'] ?? 0);
    if ($creatorID) {
        $stmt = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
        $stmt->bind_param("i", $creatorID);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $creatorEmpID = $res->fetch_assoc()['empID'];

            // Check if already assigned
            $check = $conn->prepare("SELECT 1 FROM jobassignments WHERE tripID = ? AND empID = ?");
            $check->bind_param("ii", $tripID, $creatorEmpID);
            $check->execute();
            $check->store_result();
            if ($check->num_rows == 0) {
                $assignCreator = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                $assignCreator->bind_param("ii", $tripID, $creatorEmpID);
                $assignCreator->execute();
                $assignCreator->close();
            }
            $check->close();
        }
        $stmt->close();
    }

    // ✅ Fix: Get job date
    $tripDate = $providedDate;
    if (!$tripDate) {
        $res = $conn->query("SELECT trip_date FROM trips WHERE tripID = $tripID");
        if ($res && $res->num_rows > 0) {
            $tripDate = $res->fetch_assoc()['trip_date'];
        }
    }
    if (!$tripDate) {
        throw new Exception("No valid date provided or found for tripID $tripID");
    }

    // Job attendance
    $stmt = $conn->prepare("SELECT job_attendanceID FROM job_attendance WHERE tripID = ?");
    $stmt->bind_param("i", $tripID);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $update = $conn->prepare("UPDATE job_attendance SET date = ?, standby_attendanceID = ? WHERE tripID = ?");
        $update->bind_param("sii", $tripDate, $standby_attendanceID, $tripID);
        $update->execute();
        $update->close();
    } else {
        $hasOtherDivers = !empty($otherDivers);
        $attendance_status = $hasOtherDivers ? 0 : 1;
        $approved_status = $hasOtherDivers ? 0 : 1;
        $approval_date = date('Y-m-d H:i:s');

        $insert = $conn->prepare("INSERT INTO job_attendance (tripID, date, attendance_status, approved_attendance_status, standby_attendanceID, approved_date) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param("isiiis", $tripID, $tripDate, $attendance_status, $approved_status, $standby_attendanceID, $approval_date);
        $insert->execute();
        $insert->close();

        if ($hasOtherDivers && $standby_attendanceID) {
            $updateStandby = $conn->prepare("UPDATE standby_attendance SET attendance_status = 0, approved_attendance_status = 0 WHERE standby_attendanceID = ?");
            $updateStandby->bind_param("i", $standby_attendanceID);
            $updateStandby->execute();
            $updateStandby->close();
        }
    }
    $stmt->close();

    $conn->commit();

    $response['success'] = true;
    $response['message'] = "Job assignments and attendance updated successfully.";
    $response['data'] = ['tripID' => $tripID, 'date' => $tripDate];

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
$conn->close();
