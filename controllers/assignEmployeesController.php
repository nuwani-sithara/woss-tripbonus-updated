<?php
include '../config/dbConnect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $tripID = (int)$_POST['tripID'];
        $standby_attendanceID = (int)$_POST['standby_attendanceID'];
        $divers = $_POST['divers'] ?? [];
        $otherDivers = $_POST['otherDivers'] ?? [];

        // Start transaction
        $conn->begin_transaction();

        // Clear existing assignments for this trip
        $clearAssignments = $conn->prepare("DELETE FROM jobassignments WHERE tripID = ?");
        $clearAssignments->bind_param("i", $tripID);
        $clearAssignments->execute();
        $clearAssignments->close();

        // Process standby divers
        foreach ($divers as $userID) {
            $userID = (int)$userID;
            if ($userID <= 0) continue;

            // Get empID
            $getEmpID = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
            $getEmpID->bind_param("i", $userID);
            $getEmpID->execute();
            $empResult = $getEmpID->get_result();
            
            if ($empResult->num_rows === 0) {
                $getEmpID->close();
                throw new Exception("No employee record found for userID $userID");
            }
            
            $empRow = $empResult->fetch_assoc();
            $empID = $empRow['empID'];
            $getEmpID->close();

            // Assign to trip
            $assign = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
            $assign->bind_param("ii", $tripID, $empID);
            $assign->execute();
            $assign->close();
        }

        // Process other divers
        foreach ($otherDivers as $userID) {
            $userID = (int)$userID;
            if ($userID <= 0) continue;

            // Get empID
            $getEmpID = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
            $getEmpID->bind_param("i", $userID);
            $getEmpID->execute();
            $empResult = $getEmpID->get_result();
            
            if ($empResult->num_rows === 0) {
                $getEmpID->close();
                throw new Exception("No employee record found for userID $userID");
            }
            
            $empRow = $empResult->fetch_assoc();
            $empID = $empRow['empID'];
            $getEmpID->close();

            // Assign to trip
            $assign = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
            $assign->bind_param("ii", $tripID, $empID);
            $assign->execute();
            $assign->close();
        }

        // After assigning all divers, also assign the job creator (supervisor) to the trip
        $creatorEmpRes = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
        $creatorEmpRes->bind_param("i", $_SESSION['userID']);
        $creatorEmpRes->execute();
        $creatorEmpResult = $creatorEmpRes->get_result();
        if ($creatorEmpResult->num_rows > 0) {
            $creatorEmpRow = $creatorEmpResult->fetch_assoc();
            $creatorEmpID = $creatorEmpRow['empID'];
            // Check if already assigned
            $checkAssign = $conn->prepare("SELECT 1 FROM jobassignments WHERE tripID = ? AND empID = ?");
            $checkAssign->bind_param("ii", $tripID, $creatorEmpID);
            $checkAssign->execute();
            $checkAssign->store_result();
            if ($checkAssign->num_rows == 0) {
                $assignCreator = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                $assignCreator->bind_param("ii", $tripID, $creatorEmpID);
                $assignCreator->execute();
                $assignCreator->close();
            }
            $checkAssign->close();
        }
        $creatorEmpRes->close();

        // Create or update job attendance record
        $tripQuery = "SELECT trip_date FROM trips WHERE tripID = $tripID";
        $tripResult = $conn->query($tripQuery);
        $trip = $tripResult->fetch_assoc();
        $tripDate = $trip['trip_date'];

        $checkAttendance = $conn->prepare("SELECT job_attendanceID FROM job_attendance WHERE tripID = ?");
        $checkAttendance->bind_param("i", $tripID);
        $checkAttendance->execute();
        $checkAttendance->store_result();

        if ($checkAttendance->num_rows > 0) {
            // Update existing
            $updateAttendance = $conn->prepare("UPDATE job_attendance SET date = ?, standby_attendanceID = ? WHERE tripID = ?");
            $updateAttendance->bind_param("sii", $tripDate, $standby_attendanceID, $tripID);
            $updateAttendance->execute();
            $updateAttendance->close();
        } else {
            // Insert new
            // Check if any 'other divers' are assigned (not in standby)
            $hasOtherDivers = !empty($otherDivers);
            if ($hasOtherDivers) {
                $attendance_status = 0;
                $approved_status = 0;
            } else {
                $attendance_status = 1;
                $approved_status = 1;
            }
            $approval_date = date('Y-m-d H:i:s');
            $insertAttendance = $conn->prepare("INSERT INTO job_attendance (tripID, date, attendance_status, approved_attendance_status, standby_attendanceID, approved_date) VALUES (?, ?, ?, ?, ?, ?)");
            $insertAttendance->bind_param("isiiis", $tripID, $tripDate, $attendance_status, $approved_status, $standby_attendanceID, $approval_date);
            $insertAttendance->execute();
            $insertAttendance->close();
            // If any 'other divers' are assigned, update standby_attendance row as well
            if ($hasOtherDivers && $standby_attendanceID) {
                $updateStandby = $conn->prepare("UPDATE standby_attendance SET attendance_status = 0, approved_attendance_status = 0 WHERE standby_attendanceID = ?");
                $updateStandby->bind_param("i", $standby_attendanceID);
                $updateStandby->execute();
                $updateStandby->close();
            }
        }
        $checkAttendance->close();

        // Commit transaction
        $conn->commit();

        // Redirect back to manage job days
        $jobQuery = "SELECT jobID FROM trips WHERE tripID = $tripID";
        $jobResult = $conn->query($jobQuery);
        $job = $jobResult->fetch_assoc();
        $jobID = $job['jobID'];

        header("Location: ../views/managejobdays.php?jobID=$jobID");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}