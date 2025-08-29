<?php
function updateJob(mysqli $conn, int $jobID, int $userID, array $data): array {
    // 🔹 Your full updateJob code goes here (no change needed)
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return ['success'=>false, 'error'=>'Supervisor profile not found'];

    $jobCheck = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID = $jobID AND jobCreatedBy = $userID");
    if (!$jobCheck || $jobCheck->num_rows === 0) {
        return ['success'=>false, 'error'=>"Job not found or you don't have permission to edit it."];
    }
    $jobData = $jobCheck->fetch_assoc();
    $endDate = $jobData['end_date'];

    $validJob = $conn->query("
        SELECT 1 FROM jobassignments ja
        JOIN trips t ON ja.tripID = t.tripID
        WHERE t.jobID = $jobID AND ja.empID = $empID
    ")->num_rows > 0;

    $isApproved = $conn->query("SELECT 1 FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval'")->num_rows > 0;

    if (!$validJob || ($isApproved && !empty($endDate))) {
        return ['success'=>false, 'error'=>'You cannot edit this job.'];
    }

    // Fields
    $start_date = $conn->real_escape_string($data['start_date'] ?? '');
    $end_date   = $conn->real_escape_string($data['end_date'] ?? '');
    $comment    = $conn->real_escape_string($data['comment'] ?? '');

    // Update job basics (conditionally end_date)
    $updateQuery = "UPDATE jobs SET start_date = '$start_date', comment = '$comment'";
    if ($end_date !== '') {
        $updateQuery .= ", end_date = '$end_date'";
    }
    $updateQuery .= " WHERE jobID = $jobID";
    $conn->query($updateQuery);

    // Optional updates
    if (isset($data['vesselID'])) {
        $vesselID = (int)$data['vesselID'];
        $conn->query("UPDATE jobs SET vesselID = $vesselID WHERE jobID = $jobID");
    }
    if (isset($data['jobtypeID'])) {
        $jobtypeID = (int)$data['jobtypeID'];
        $conn->query("UPDATE jobs SET jobtypeID = $jobtypeID WHERE jobID = $jobID");
    }
    if (isset($data['portID'])) {
        $portID = (int)$data['portID'];
        $conn->query("DELETE FROM portassignments WHERE jobID = $jobID");
        if ($portID > 0) $conn->query("INSERT INTO portassignments (jobID, portID) VALUES ($jobID, $portID)");
    }
    if (isset($data['boatID'])) {
        $boatID = (int)$data['boatID'];
        $conn->query("DELETE FROM boatassignments WHERE jobID = $jobID");
        if ($boatID > 0) $conn->query("INSERT INTO boatassignments (jobID, boatID) VALUES ($jobID, $boatID)");
    }

    // Trip assignments:
    if (isset($data['trip_assignments']) && is_array($data['trip_assignments'])) {
        foreach ($data['trip_assignments'] as $tripID => $assignment) {
            $tripID = (int)$tripID;
            $divers = $assignment['divers'] ?? [];
            $otherDivers = $assignment['otherDivers'] ?? [];

            // reset assignments for trip
            $conn->query("DELETE FROM jobassignments WHERE tripID = $tripID");

            // helper to assign by userID -> empID
            $assignUserIDs = function(array $userIDs) use ($conn, $tripID) {
                foreach ($userIDs as $uid) {
                    $uid = (int)$uid;
                    if ($uid <= 0) continue;
                    $getEmpID = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
                    $getEmpID->bind_param("i", $uid);
                    $getEmpID->execute();
                    $r = $getEmpID->get_result();
                    if ($r && $r->num_rows > 0) {
                        $empRow = $r->fetch_assoc();
                        $empID2 = (int)$empRow['empID'];
                        $assign = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                        $assign->bind_param("ii", $tripID, $empID2);
                        $assign->execute();
                        $assign->close();
                    }
                    $getEmpID->close();
                }
            };
            $assignUserIDs($divers);
            $assignUserIDs($otherDivers);

            // also ensure creator is assigned
            $creatorEmp = getSupervisorEmpID($conn, (int)$_SESSION['userID']);
            if ($creatorEmp) {
                $checkAssign = $conn->prepare("SELECT 1 FROM jobassignments WHERE tripID = ? AND empID = ?");
                $checkAssign->bind_param("ii", $tripID, $creatorEmp);
                $checkAssign->execute();
                $checkAssign->store_result();
                if ($checkAssign->num_rows === 0) {
                    $assignCreator = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                    $assignCreator->bind_param("ii", $tripID, $creatorEmp);
                    $assignCreator->execute();
                    $assignCreator->close();
                }
                $checkAssign->close();
            }

            // Attendance
            $standby_attendanceID = isset($data['standby_attendanceID']) ? (int)$data['standby_attendanceID'] : null;
            $tripRow = $conn->query("SELECT trip_date FROM trips WHERE tripID = $tripID")->fetch_assoc();
            $tripDate = $tripRow['trip_date'] ?? date('Y-m-d');

            $checkAttendance = $conn->prepare("SELECT job_attendanceID FROM job_attendance WHERE tripID = ?");
            $checkAttendance->bind_param("i", $tripID);
            $checkAttendance->execute();
            $checkAttendance->store_result();

            $hasOtherDivers = !empty($otherDivers);
            $attendance_status = $hasOtherDivers ? 0 : 1;
            $approved_status = $hasOtherDivers ? 0 : 1;

            if ($checkAttendance->num_rows > 0) {
                $updateAttendance = $conn->prepare("
                    UPDATE job_attendance
                    SET date = ?, standby_attendanceID = ?, attendance_status = ?, approved_attendance_status = ?
                    WHERE tripID = ?
                ");
                $updateAttendance->bind_param("siiii", $tripDate, $standby_attendanceID, $attendance_status, $approved_status, $tripID);
                $updateAttendance->execute();
                $updateAttendance->close();
            } else {
                $approval_date = date('Y-m-d H:i:s');
                $insertAttendance = $conn->prepare("
                    INSERT INTO job_attendance (tripID, date, attendance_status, approved_attendance_status, standby_attendanceID, approved_date)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertAttendance->bind_param("isiiis", $tripID, $tripDate, $attendance_status, $approved_status, $standby_attendanceID, $approval_date);
                $insertAttendance->execute();
                $insertAttendance->close();
            }
            $checkAttendance->close();

            if ($hasOtherDivers && $standby_attendanceID) {
                $updateStandby = $conn->prepare("UPDATE standby_attendance SET attendance_status = 0, approved_attendance_status = 0 WHERE standby_attendanceID = ?");
                $updateStandby->bind_param("i", $standby_attendanceID);
                $updateStandby->execute();
                $updateStandby->close();
            }
        }
    }

    // Special projects
    if (isset($data['special_projects']) && is_array($data['special_projects'])) {
        $conn->query("DELETE FROM jobspecialprojects WHERE jobID = $jobID");
        foreach ($data['special_projects'] as $proj) {
            if (!empty($proj['spProjectID'])) {
                $spProjectID = (int)$proj['spProjectID'];
                $vesselName = $conn->real_escape_string($proj['vessel'] ?? '');
                $date       = $conn->real_escape_string($proj['date'] ?? '');
                $evidence   = $conn->real_escape_string($proj['evidence'] ?? '');

                $vesselID = 0;
                if ($vesselName !== '') {
                    $vRes = $conn->query("SELECT vesselID FROM vessels WHERE vessel_name = '$vesselName'");
                    if ($vRes && $vRes->num_rows > 0) {
                        $vRow = $vRes->fetch_assoc();
                        $vesselID = (int)$vRow['vesselID'];
                    }
                }
                $conn->query("
                    UPDATE specialproject
                    SET vesselID = $vesselID, date = '$date', evidence = '$evidence'
                    WHERE spProjectID = $spProjectID
                ");
                $conn->query("INSERT INTO jobspecialprojects (jobID, spProjectID) VALUES ($jobID, $spProjectID)");
            }
        }
    }

    return ['success'=>true, 'message'=>'Job updated successfully!'];
}

function deleteTrip(mysqli $conn, int $tripID, int $userID): array {
    // Verify trip belongs to a job created by this user
    $q = $conn->query("
        SELECT t.tripID, t.jobID 
        FROM trips t 
        JOIN jobs j ON t.jobID = j.jobID 
        WHERE t.tripID = $tripID AND j.jobCreatedBy = $userID
    ");
    if (!$q || $q->num_rows === 0) {
        return ['success' => false, 'error' => "Trip not found or you don't have permission to delete it."];
    }
    $row = $q->fetch_assoc();
    $jobID = (int)$row['jobID'];

    $conn->begin_transaction();
    try {
        // Delete related data
        $conn->query("DELETE FROM jobassignments WHERE tripID = $tripID");
        $conn->query("DELETE FROM job_attendance WHERE tripID = $tripID");
        $conn->query("DELETE FROM trips WHERE tripID = $tripID");

        $conn->commit();
        return ['success' => true, 'message' => "Trip deleted successfully", 'jobID' => $jobID];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteJobAndRelatedData(mysqli $conn, int $jobID): void {
    $conn->begin_transaction();
    try {
        $tripIDs = [];
        $tr = $conn->query("SELECT tripID FROM trips WHERE jobID = $jobID");
        if ($tr) while ($t = $tr->fetch_assoc()) $tripIDs[] = (int)$t['tripID'];

        if ($tripIDs) {
            $str = implode(',', $tripIDs);
            $conn->query("DELETE FROM jobassignments WHERE tripID IN ($str)");
            $conn->query("DELETE FROM job_attendance WHERE tripID IN ($str)");
            $conn->query("DELETE FROM trips WHERE jobID = $jobID");
        }

        $conn->query("DELETE FROM jobspecialprojects WHERE jobID = $jobID");
        $conn->query("DELETE FROM portassignments WHERE jobID = $jobID");
        $conn->query("DELETE FROM boatassignments WHERE jobID = $jobID");
        $conn->query("DELETE FROM jobs WHERE jobID = $jobID");

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteJob(mysqli $conn, int $jobID, int $userID): array {
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return ['success'=>false, 'error'=>'Supervisor profile not found'];

    // Check ownership
    $jobCheck = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID = $jobID AND jobCreatedBy = $userID");
    if (!$jobCheck || $jobCheck->num_rows === 0) {
        return ['success'=>false, 'error'=>"Job not found or you don't have permission to delete it."];
    }
    $jobData = $jobCheck->fetch_assoc();
    $endDate = $jobData['end_date'];

    // Verify supervisor is assigned to this job
    $validJob = $conn->query("
        SELECT 1 
        FROM jobassignments ja
        JOIN trips t ON ja.tripID = t.tripID
        WHERE t.jobID = $jobID AND ja.empID = $empID
    ")->num_rows > 0;

    // Check if approved
    $isApproved = $conn->query("SELECT 1 FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval'")->num_rows > 0;

    if (!$validJob || ($isApproved && !empty($endDate))) {
        return ['success'=>false, 'error'=>'Cannot delete jobs that have been approved or completed.'];
    }

    // Safe to delete
    deleteJobAndRelatedData($conn, $jobID);
    return ['success'=>true, 'message'=>'Job deleted successfully!'];
}

function getSupervisorEmpID(mysqli $conn, int $userID): ?int {
    $res = $conn->query("SELECT empID FROM employees WHERE userID = $userID LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int)$row['empID'];
    }
    return null;
}

function resolveClarification(mysqli $conn, int $clarificationID, int $userID, array $data): array {
    $jobID = (int)($data['jobID'] ?? 0);
    if ($jobID <= 0) return ['success'=>false, 'error'=>'jobID required'];

    $resolvedComment = $conn->real_escape_string($data['clarification_resolved_comment'] ?? '');
    $resolverID = $userID;

     $update = $conn->query("UPDATE clarifications SET 
        clarification_resolverID = $resolverID,
        clarification_resolved_comment = '$resolvedComment',
        clarification_status = 1
        WHERE clarification_id = $clarificationID AND jobID = $jobID");
        
    if ($update) return ['success'=>true, 'message'=>'Clarification resolved successfully!'];
    return ['success'=>false, 'error'=>'Failed to resolve clarification.'];
}


