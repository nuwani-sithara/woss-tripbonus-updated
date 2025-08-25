<?php
require_once(__DIR__ . '/../config/dbConnect.php');
session_start();

// Ensure supervisor is logged in
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

$userID = $_SESSION['userID'];

function getJobDetails($conn, $jobID) {
    $job = $conn->query("SELECT * FROM jobs WHERE jobID = $jobID")->fetch_assoc();
    if (!$job) return null;

    // Boat
    $boat = null;
    $ba = $conn->query("SELECT boatID FROM boatassignments WHERE jobID = $jobID")->fetch_assoc();
    if ($ba) {
        $boat = $conn->query("SELECT * FROM boats WHERE boatID = {$ba['boatID']}")->fetch_assoc();
    }

    // Employees
    $employees = [];
    $empRes = $conn->query("SELECT ja.empID, ja.tripID, t.trip_date FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE t.jobID = $jobID");
    while ($empRow = $empRes->fetch_assoc()) {
        $emp = $conn->query("SELECT * FROM employees WHERE empID = {$empRow['empID']}")->fetch_assoc();
        if ($emp) {
            $user = $conn->query("SELECT userID, fname, lname FROM users WHERE userID = {$emp['userID']}")->fetch_assoc();
            $employees[] = [
                'empID' => $emp['empID'],
                'userID' => $user['userID'],
                'fname' => $user['fname'],
                'lname' => $user['lname'],
            ];
        }
    }

    // Port
    $port = null;
    $pa = $conn->query("SELECT portID FROM portassignments WHERE jobID = $jobID")->fetch_assoc();
    if ($pa) {
        $port = $conn->query("SELECT * FROM ports WHERE portID = {$pa['portID']}")->fetch_assoc();
    }

    // Job type
    $job_type = null;
    if (isset($job['jobtypeID'])) {
        $jt = $conn->query("SELECT * FROM jobtype WHERE jobtypeID = {$job['jobtypeID']}")->fetch_assoc();
        if ($jt) {
            $job_type = $jt;
        }
    }

    // Special projects - fetch all details
    $special_projects = [];
    $spRes = $conn->query("
        SELECT sp.spProjectID, sp.name, sp.vesselID, sp.date, sp.evidence, v.vessel_name 
        FROM specialproject sp
        JOIN jobspecialprojects jsp ON sp.spProjectID = jsp.spProjectID
        LEFT JOIN vessels v ON sp.vesselID = v.vesselID
        WHERE jsp.jobID = $jobID
    ");

    while ($sp = $spRes->fetch_assoc()) {
        $special_projects[] = [
            'spProjectID' => $sp['spProjectID'],
            'name' => $sp['name'],
            'vessel' => $sp['vessel_name'],
            'date' => $sp['date'],
            'evidence' => $sp['evidence']
        ];
    }

    // Vessel
    $vessel = null;
    if (isset($job['vesselID'])) {
        $vessel = $conn->query("SELECT * FROM vessels WHERE vesselID = {$job['vesselID']}")->fetch_assoc();
    }

    return [
        'job' => $job,
        'boat' => $boat,
        'employees' => $employees,
        'port' => $port,
        'job_type' => $job_type,
        'special_projects' => $special_projects,
        'vessel' => $vessel,
    ];
}

function getEditableJobsForSupervisor($conn, $userID) {
    // Get empID for the logged-in supervisor
    $empRes = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    if (!$empRes || $empRes->num_rows == 0) return [];
    $empRow = $empRes->fetch_assoc();
    $empID = $empRow['empID'];

    // Get all jobIDs for this supervisor (jobs where supervisor is assigned)
    $jobIDs = [];
    $jobAssignRes = $conn->query("SELECT DISTINCT t.jobID FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE ja.empID = $empID");
    while ($row = $jobAssignRes->fetch_assoc()) {
        $jobIDs[] = $row['jobID'];
    }
    if (empty($jobIDs)) return [];
    $jobIDsStr = implode(',', $jobIDs);

    // Get jobIDs that are already approved with approval_stage = 'job_approval'
    // These jobs are considered "locked" and not editable unless they don't have an end_date
    $approvedJobIDs = [];
    $approvedRes = $conn->query("SELECT jobID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_stage = 'job_approval'");
    while ($row = $approvedRes->fetch_assoc()) {
        $approvedJobIDs[] = $row['jobID'];
    }

    // Filter jobs based on editability criteria:
    // A job is editable if:
    // 1. It's not in approvals table with approval_stage = 'job_approval', OR
    // 2. The end_date is NULL or empty (incomplete jobs can still be edited even if approved)
    $editableJobIDs = [];
    $jobsRes = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID IN ($jobIDsStr) AND jobCreatedBy = $userID");
    while ($job = $jobsRes->fetch_assoc()) {
        $jobID = $job['jobID'];
        $endDate = $job['end_date'];
        
        // Job is editable if:
        // 1. It's not in approvals table with approval_stage = 'job_approval', OR
        // 2. The end_date is NULL or empty
        if (!in_array($jobID, $approvedJobIDs) || empty($endDate)) {
            $editableJobIDs[] = $jobID;
        }
    }
    
    if (empty($editableJobIDs)) return [];
    $editableJobIDsStr = implode(',', $editableJobIDs);

    // Fetch job details for editable jobs
    $jobs = [];
    $jobsRes = $conn->query("SELECT * FROM jobs WHERE jobID IN ($editableJobIDsStr) AND jobCreatedBy = $userID");
    while ($job = $jobsRes->fetch_assoc()) {
        $jobs[] = getJobDetails($conn, $job['jobID']);
    }
    return $jobs;
}

function getReadOnlyJobsForSupervisor($conn, $userID) {
    $empRes = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    if (!$empRes || $empRes->num_rows == 0) return [];
    $empRow = $empRes->fetch_assoc();
    $empID = $empRow['empID'];
    
    $jobIDs = [];
    $jobAssignRes = $conn->query("SELECT DISTINCT t.jobID FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE ja.empID = $empID");
    while ($row = $jobAssignRes->fetch_assoc()) {
        $jobIDs[] = $row['jobID'];
    }
    if (empty($jobIDs)) return [];
    $jobIDsStr = implode(',', $jobIDs);
    
    $approvedJobIDs = [];
    $approvedRes = $conn->query("SELECT jobID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_status = 1 && approval_stage = 'job_approval'");
    while ($row = $approvedRes->fetch_assoc()) {
        $approvedJobIDs[] = $row['jobID'];
    }
    if (empty($approvedJobIDs)) return [];
    $approvedJobIDsStr = implode(',', $approvedJobIDs);
    
    $jobs = [];
    $jobsRes = $conn->query("SELECT * FROM jobs WHERE jobID IN ($approvedJobIDsStr) AND jobCreatedBy = $userID");
    while ($job = $jobsRes->fetch_assoc()) {
        $jobs[] = getJobDetails($conn, $job['jobID']);
    }
    return $jobs;
}

// Fetch jobs with clarifications for this supervisor
function getClarificationJobsForSupervisor($conn, $userID) {
    $empRes = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    if (!$empRes || $empRes->num_rows == 0) return [];
    $empRow = $empRes->fetch_assoc();
    $empID = $empRow['empID'];

    $jobIDs = [];
    $jobAssignRes = $conn->query("SELECT DISTINCT t.jobID FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE ja.empID = $empID");
    while ($row = $jobAssignRes->fetch_assoc()) {
        $jobIDs[] = $row['jobID'];
    }
    if (empty($jobIDs)) return [];
    $jobIDsStr = implode(',', $jobIDs);

    // Find jobs with approval_status=2 (clarification)
    // Only show clarifications where supervisor-in-charge (role_id = 13) is the requester
    $clarificationJobIDs = [];
    $clarificationRes = $conn->query("SELECT jobID, approvalID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_status = 2 AND approval_stage = 'supervisor_in_charge_approval'");
    $approvalMap = [];
    while ($row = $clarificationRes->fetch_assoc()) {
        $clarificationJobIDs[] = $row['jobID'];
        $approvalMap[$row['jobID']] = $row['approvalID'];
    }
    if (empty($clarificationJobIDs)) return [];
    $clarificationJobIDsStr = implode(',', $clarificationJobIDs);

    // Fetch job details for clarification jobs
    $jobs = [];
    $jobsRes = $conn->query("SELECT * FROM jobs WHERE jobID IN ($clarificationJobIDsStr) AND jobCreatedBy = $userID");
    while ($job = $jobsRes->fetch_assoc()) {
        $jobDetail = getJobDetails($conn, $job['jobID']);
        $jobDetail['approvalID'] = $approvalMap[$job['jobID']];
        $jobs[] = $jobDetail;
    }
    return $jobs;
}

// Fetch clarification details for a jobID (returns all clarification rows)
function getClarificationDetails($conn, $jobID) {
    // Only show clarifications where supervisor-in-charge (role_id = 13) is the requester
    $clarRes = $conn->query("SELECT c.* FROM clarifications c 
                             JOIN users u ON c.clarification_requesterID = u.userID 
                             WHERE c.jobID = $jobID 
                             AND u.roleID = 13 
                             ORDER BY c.clarification_id DESC");
    if ($clarRes && $clarRes->num_rows > 0) {
        $clarifications = [];
        while ($clar = $clarRes->fetch_assoc()) {
            $clarifications[] = $clar;
        }
        return $clarifications;
    }
    return [];
}

// Fetch jobs with clarifications resolved by supervisor but pending approval (clarification_status = 2)
function getPendingApprovalClarificationJobsForSupervisor($conn, $userID) {
    $empRes = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    if (!$empRes || $empRes->num_rows == 0) return [];
    $empRow = $empRes->fetch_assoc();
    $empID = $empRow['empID'];

    $jobIDs = [];
    $jobAssignRes = $conn->query("SELECT DISTINCT t.jobID FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE ja.empID = $empID");
    while ($row = $jobAssignRes->fetch_assoc()) {
        $jobIDs[] = $row['jobID'];
    }
    if (empty($jobIDs)) return [];
    $jobIDsStr = implode(',', $jobIDs);

    // Find jobs with clarification_status=1 (resolved, waiting for supervisor-in-charge approval)
    // Only show clarifications where supervisor-in-charge (role_id = 13) is the requester
    $pendingApprovalJobIDs = [];
    $pendingApprovalRes = $conn->query("SELECT DISTINCT c.jobID FROM clarifications c 
                                       JOIN users u ON c.clarification_requesterID = u.userID 
                                       WHERE c.jobID IN ($jobIDsStr) 
                                       AND c.clarification_status = 1 
                                       AND u.roleID = 13");
    while ($row = $pendingApprovalRes->fetch_assoc()) {
        $pendingApprovalJobIDs[] = $row['jobID'];
    }
    if (empty($pendingApprovalJobIDs)) return [];
    $pendingApprovalJobIDsStr = implode(',', $pendingApprovalJobIDs);

    // Fetch job details for pending approval clarification jobs
    $jobs = [];
    $jobsRes = $conn->query("SELECT * FROM jobs WHERE jobID IN ($pendingApprovalJobIDsStr) AND jobCreatedBy = $userID");
    while ($job = $jobsRes->fetch_assoc()) {
        $jobDetail = getJobDetails($conn, $job['jobID']);
        $jobs[] = $jobDetail;
    }
    return $jobs;
}

// Handle clarification resolve POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_clarification'])) {
    $jobID = intval($_POST['jobID']);
    $clarificationID = intval($_POST['clarification_id']);
    $resolvedComment = $conn->real_escape_string($_POST['clarification_resolved_comment']);
    $resolverID = $userID;

    // Update the clarification
    $update = $conn->query("UPDATE clarifications SET 
        clarification_resolverID = $resolverID,
        clarification_resolved_comment = '$resolvedComment',
        clarification_status = 1
        WHERE clarification_id = $clarificationID AND jobID = $jobID");

    if ($update) {
        $_SESSION['success'] = 'Clarification resolved successfully!';
    } else {
        $_SESSION['error'] = 'Failed to resolve clarification.';
    }

    header('Location: supervisoreditjobs.php');
    exit();
}

// Handle clarification resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_clarification'], $_POST['clarification_resolved_comment'])) {
    $jobID = intval($_POST['jobID']);
    $clarificationID = intval($_POST['clarification_id']);
    $resolutionComment = $_POST['clarification_resolved_comment'];
    $userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
    
    if (!$userID) {
        header("Location: ../index.php?error=access_denied");
        exit;
    }
    
    // Verify this is a valid clarification for this supervisor
    $verifyClarification = $conn->prepare("SELECT c.* FROM clarifications c 
                                          JOIN users u ON c.clarification_requesterID = u.userID 
                                          WHERE c.clarification_id = ? 
                                          AND c.jobID = ? 
                                          AND u.roleID = 13 
                                          AND c.clarification_status = 0");
    $verifyClarification->bind_param("ii", $clarificationID, $jobID);
    $verifyClarification->execute();
    $clarificationResult = $verifyClarification->get_result();
    
    if ($clarificationResult->num_rows > 0) {
        // Update the clarification with resolution - status becomes 1 (resolved, waiting for supervisor-in-charge approval)
        $stmt = $conn->prepare("UPDATE clarifications SET clarification_resolved_comment = ?, clarification_status = 1 WHERE clarification_id = ?");
        $stmt->bind_param("si", $resolutionComment, $clarificationID);
        $stmt->execute();
        
        $_SESSION['success'] = "Clarification resolved successfully. Waiting for supervisor-in-charge approval.";
    } else {
        $_SESSION['error'] = "Invalid clarification or you don't have permission to resolve it.";
    }
    
    header("Location: ../views/supervisoreditjobs.php");
    exit;
}

// Handle form submission for editing jobs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $jobID = $_POST['jobID'];
    
    // Validate job belongs to this supervisor
    $empRes = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    $empRow = $empRes->fetch_assoc();
    $empID = $empRow['empID'];
    
    // Check if job exists and belongs to this supervisor
    $jobCheck = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID = $jobID AND jobCreatedBy = $userID");
    if ($jobCheck->num_rows == 0) {
        $_SESSION['error'] = "Job not found or you don't have permission to edit it.";
        header("Location: supervisoreditjobs.php");
        exit();
    }
    
    $jobData = $jobCheck->fetch_assoc();
    $endDate = $jobData['end_date'];
    
    // Check if supervisor is assigned to this job
    $isValidJob = $conn->query("SELECT 1 FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE t.jobID = $jobID AND ja.empID = $empID")->num_rows > 0;
    
    // Check if job is approved with approval_stage = 'job_approval'
    $isApproved = $conn->query("SELECT 1 FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval'")->num_rows > 0;
    
    // Job is editable if:
    // 1. Supervisor is assigned to the job, AND
    // 2. (Job is not approved with job_approval stage OR end_date is empty)
    // This allows editing of incomplete jobs even if they are approved
    if (!$isValidJob || ($isApproved && !empty($endDate))) {
        $_SESSION['error'] = "You cannot edit this job.";
        header("Location: supervisoreditjobs.php");
        exit();
    }
    
    // Process updates
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $comment = $conn->real_escape_string($_POST['comment']);
    
    // Update basic job info - only update end_date if it's provided
    $updateQuery = "UPDATE jobs SET 
        start_date = '$start_date',
        comment = '$comment'";
    
    // Only add end_date to the update if it's not empty
    if (!empty($end_date)) {
        $updateQuery .= ", end_date = '$end_date'";
    }
    
    $updateQuery .= " WHERE jobID = $jobID";
    $conn->query($updateQuery);
    
    // Update vessel if changed
    if (isset($_POST['vesselID'])) {
        $vesselID = $_POST['vesselID'];
        $conn->query("UPDATE jobs SET vesselID = $vesselID WHERE jobID = $jobID");
    }
    
    // Update job type if changed
    if (isset($_POST['jobtypeID'])) {
        $jobtypeID = $_POST['jobtypeID'];
        $conn->query("UPDATE jobs SET jobtypeID = $jobtypeID WHERE jobID = $jobID");
    }
    
    // Update port if changed
    if (isset($_POST['portID'])) {
        $portID = $_POST['portID'];
        // Delete existing port assignment
        $conn->query("DELETE FROM portassignments WHERE jobID = $jobID");
        // Add new one if port is selected
        if ($portID > 0) {
            $conn->query("INSERT INTO portassignments (jobID, portID) VALUES ($jobID, $portID)");
        }
    }
    
    // Update boat if changed
    if (isset($_POST['boatID'])) {
        $boatID = $_POST['boatID'];
        // Delete existing boat assignment
        $conn->query("DELETE FROM boatassignments WHERE jobID = $jobID");
        // Add new one if boat is selected
        if ($boatID > 0) {
            $conn->query("INSERT INTO boatassignments (jobID, boatID) VALUES ($jobID, $boatID)");
        }
    }
    
    // Update employees for each trip
    if (isset($_POST['trip_assignments'])) {
        foreach ($_POST['trip_assignments'] as $tripID => $assignment) {
            $divers = $assignment['divers'] ?? [];
            $otherDivers = $assignment['otherDivers'] ?? [];
            
            // Delete existing assignments for this trip
            $conn->query("DELETE FROM jobassignments WHERE tripID = $tripID");
            
            // Process standby divers
            foreach ($divers as $userID) {
                $userID = (int)$userID;
                if ($userID <= 0) continue;

                // Get empID
                $getEmpID = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
                $getEmpID->bind_param("i", $userID);
                $getEmpID->execute();
                $empResult = $getEmpID->get_result();
                
                if ($empResult->num_rows > 0) {
                    $empRow = $empResult->fetch_assoc();
                    $empID = $empRow['empID'];
                    
                    // Assign to trip
                    $assign = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                    $assign->bind_param("ii", $tripID, $empID);
                    $assign->execute();
                    $assign->close();
                }
                $getEmpID->close();
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
                
                if ($empResult->num_rows > 0) {
                    $empRow = $empResult->fetch_assoc();
                    $empID = $empRow['empID'];
                    
                    // Assign to trip
                    $assign = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                    $assign->bind_param("ii", $tripID, $empID);
                    $assign->execute();
                    $assign->close();
                }
                $getEmpID->close();
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
            
            // Create or update job attendance record for this trip
            $standby_attendanceID = isset($_POST['standby_attendanceID']) ? (int)$_POST['standby_attendanceID'] : null;
            
            $tripQuery = "SELECT trip_date FROM trips WHERE tripID = $tripID";
            $tripResult = $conn->query($tripQuery);
            $trip = $tripResult->fetch_assoc();
            $tripDate = $trip['trip_date'];

            $checkAttendance = $conn->prepare("SELECT job_attendanceID FROM job_attendance WHERE tripID = ?");
            $checkAttendance->bind_param("i", $tripID);
            $checkAttendance->execute();
            $checkAttendance->store_result();

            // Check if any 'other divers' are assigned (not in standby)
            $hasOtherDivers = !empty($otherDivers);
            
            // Debug logging
            error_log("Trip $tripID - Other divers: " . json_encode($otherDivers) . ", Has other divers: " . ($hasOtherDivers ? 'true' : 'false'));
            
            if ($checkAttendance->num_rows > 0) {
                // Update existing
                if ($hasOtherDivers) {
                    $attendance_status = 0;
                    $approved_status = 0;
                } else {
                    $attendance_status = 1;
                    $approved_status = 1;
                }
                error_log("Trip $tripID - Updating existing attendance: status=$attendance_status, approved=$approved_status");
                $updateAttendance = $conn->prepare("UPDATE job_attendance SET date = ?, standby_attendanceID = ?, attendance_status = ?, approved_attendance_status = ? WHERE tripID = ?");
                $updateAttendance->bind_param("siiii", $tripDate, $standby_attendanceID, $attendance_status, $approved_status, $tripID);
                $updateAttendance->execute();
                $updateAttendance->close();
            } else {
                // Insert new
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
            }
            
            // If any 'other divers' are assigned, update standby_attendance row as well
            if ($hasOtherDivers && $standby_attendanceID) {
                error_log("Trip $tripID - Updating standby attendance: standby_attendanceID=$standby_attendanceID");
                $updateStandby = $conn->prepare("UPDATE standby_attendance SET attendance_status = 0, approved_attendance_status = 0 WHERE standby_attendanceID = ?");
                $updateStandby->bind_param("i", $standby_attendanceID);
                $updateStandby->execute();
                $updateStandby->close();
            }
            $checkAttendance->close();
        }
    }
    
    // Update special projects if changed
    // In your form submission handler:
    if (isset($_POST['special_projects'])) {
        $conn->query("DELETE FROM jobspecialprojects WHERE jobID = $jobID");
        
        foreach ($_POST['special_projects'] as $project) {
            if (!empty($project['spProjectID'])) {
                $spProjectID = $project['spProjectID'];
                $vesselName = $conn->real_escape_string($project['vessel'] ?? '');
                $date = $conn->real_escape_string($project['date'] ?? '');
                $evidence = $conn->real_escape_string($project['evidence'] ?? '');
                
                // Convert vessel name to vesselID
                $vesselID = 0;
                if (!empty($vesselName)) {
                    $vesselRes = $conn->query("SELECT vesselID FROM vessels WHERE vessel_name = '$vesselName'");
                    if ($vesselRes && $vesselRes->num_rows > 0) {
                        $vesselRow = $vesselRes->fetch_assoc();
                        $vesselID = $vesselRow['vesselID'];
                    }
                }
                
                // Update special project details
                $conn->query("UPDATE specialproject SET 
                    vesselID = $vesselID,
                    date = '$date',
                    evidence = '$evidence'
                    WHERE spProjectID = $spProjectID");
                
                // Reassign to job
                $conn->query("INSERT INTO jobspecialprojects (jobID, spProjectID) VALUES ($jobID, $spProjectID)");
            }
        }
    }
    $_SESSION['success'] = "Job updated successfully!";
    header("Location: supervisoreditjobs.php");
    exit();
}

// Handle job deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    $jobID = intval($_POST['jobID']);
    
    // Validate job belongs to this supervisor and is editable
    $empRes = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    $empRow = $empRes->fetch_assoc();
    $empID = $empRow['empID'];
    
    // Check if job exists and belongs to this supervisor
    $jobCheck = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID = $jobID AND jobCreatedBy = $userID");
    if ($jobCheck->num_rows == 0) {
        $_SESSION['error'] = "Job not found or you don't have permission to delete it.";
        header("Location: supervisoreditjobs.php");
        exit();
    }
    
    $jobData = $jobCheck->fetch_assoc();
    $endDate = $jobData['end_date'];
    
    // Check if supervisor is assigned to this job
    $isValidJob = $conn->query("SELECT 1 FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE t.jobID = $jobID AND ja.empID = $empID")->num_rows > 0;
    
    // Check if job is approved with approval_stage = 'job_approval'
    $isApproved = $conn->query("SELECT 1 FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval'")->num_rows > 0;
    
    // Job can be deleted if:
    // 1. Supervisor is assigned to the job, AND
    // 2. (Job is not approved with job_approval stage OR end_date is empty)
    // This prevents deletion of completed and approved jobs
    if (!$isValidJob || ($isApproved && !empty($endDate))) {
        $_SESSION['error'] = "Cannot delete jobs that have been approved or completed.";
        header("Location: supervisoreditjobs.php");
        exit();
    }
    
    // Delete all related data for this job
    deleteJobAndRelatedData($conn, $jobID);
    
    $_SESSION['success'] = "Job deleted successfully!";
    header("Location: supervisoreditjobs.php");
    exit();
}

// Function to delete job and all related data
function deleteJobAndRelatedData($conn, $jobID) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get all trip IDs for this job
        $tripIDs = [];
        $tripsRes = $conn->query("SELECT tripID FROM trips WHERE jobID = $jobID");
        while ($trip = $tripsRes->fetch_assoc()) {
            $tripIDs[] = $trip['tripID'];
        }
        
        if (!empty($tripIDs)) {
            $tripIDsStr = implode(',', $tripIDs);
            
            // Delete job assignments for all trips
            $conn->query("DELETE FROM jobassignments WHERE tripID IN ($tripIDsStr)");
            
            // Delete job attendance for all trips
            $conn->query("DELETE FROM job_attendance WHERE tripID IN ($tripIDsStr)");
            
            // Delete trips
            $conn->query("DELETE FROM trips WHERE jobID = $jobID");
        }
        
        // Delete special project assignments
        $conn->query("DELETE FROM jobspecialprojects WHERE jobID = $jobID");
        
        // Delete port assignments
        $conn->query("DELETE FROM portassignments WHERE jobID = $jobID");
        
        // Delete boat assignments
        $conn->query("DELETE FROM boatassignments WHERE jobID = $jobID");
        
        // Delete the job itself
        $conn->query("DELETE FROM jobs WHERE jobID = $jobID");
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
}

// Get dropdown options for edit modal
function getDropdownOptions($conn) {
    $options = [];
    
    // Get vessels
    $options['vessels'] = [];
    $vesselsRes = $conn->query("SELECT vesselID, vessel_name FROM vessels ORDER BY vessel_name");
    while ($row = $vesselsRes->fetch_assoc()) {
        $options['vessels'][] = $row;
    }
    
    // Get job types
    $options['job_types'] = [];
    $typesRes = $conn->query("SELECT jobtypeID, type_name FROM jobtype ORDER BY type_name");
    while ($row = $typesRes->fetch_assoc()) {
        $options['job_types'][] = $row;
    }
    
    // Get ports
    $options['ports'] = [];
    $portsRes = $conn->query("SELECT portID, portname FROM ports ORDER BY portname");
    while ($row = $portsRes->fetch_assoc()) {
        $options['ports'][] = $row;
    }
    
    // Get boats
    $options['boats'] = [];
    $boatsRes = $conn->query("SELECT boatID, boat_name FROM boats ORDER BY boat_name");
    while ($row = $boatsRes->fetch_assoc()) {
        $options['boats'][] = $row;
    }
    
    // Get employees
    $options['employees'] = [];
    $empsRes = $conn->query("SELECT e.empID, u.fname, u.lname 
                            FROM employees e 
                            JOIN users u ON e.userID = u.userID 
                            ORDER BY u.lname, u.fname");
    while ($row = $empsRes->fetch_assoc()) {
        $options['employees'][] = $row;
    }
    
    // Get special projects
    $options['special_projects'] = [];
    $projectsRes = $conn->query("SELECT spProjectID, name FROM specialproject ORDER BY name");
    while ($row = $projectsRes->fetch_assoc()) {
        $options['special_projects'][] = $row;
    }
    
    return $options;
}

function getRejectedJobsForSupervisor($conn, $userID) {
    $empRes = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    if (!$empRes || $empRes->num_rows == 0) return [];
    $empRow = $empRes->fetch_assoc();
    $empID = $empRow['empID'];
    
    $jobIDs = [];
    $jobAssignRes = $conn->query("SELECT DISTINCT t.jobID FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE ja.empID = $empID");
    while ($row = $jobAssignRes->fetch_assoc()) {
        $jobIDs[] = $row['jobID'];
    }
    if (empty($jobIDs)) return [];
    $jobIDsStr = implode(',', $jobIDs);
    
    $rejectedJobIDs = [];
    $rejectedRes = $conn->query("SELECT jobID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_status = 3 && approval_stage = 'job_approval'");
    while ($row = $rejectedRes->fetch_assoc()) {
        $rejectedJobIDs[] = $row['jobID'];
    }
    if (empty($rejectedJobIDs)) return [];
    $rejectedJobIDsStr = implode(',', $rejectedJobIDs);
    
    $jobs = [];
    $jobsRes = $conn->query("SELECT * FROM jobs WHERE jobID IN ($rejectedJobIDsStr) AND jobCreatedBy = $userID");
    while ($job = $jobsRes->fetch_assoc()) {
        $jobs[] = getJobDetails($conn, $job['jobID']);
    }
    return $jobs;
}

$dropdownOptions = getDropdownOptions($conn);
$editableJobs = getEditableJobsForSupervisor($conn, $userID);
$readOnlyJobs = getReadOnlyJobsForSupervisor($conn, $userID);
$clarificationJobs = getClarificationJobsForSupervisor($conn, $userID);
$pendingApprovalClarificationJobs = getPendingApprovalClarificationJobsForSupervisor($conn, $userID);
$rejectedJobs = getRejectedJobsForSupervisor($conn, $userID);
$clarificationDetails = [];
foreach ($clarificationJobs as $cj) {
    $clarificationDetails[$cj['job']['jobID']] = getClarificationDetails($conn, $cj['job']['jobID']);
}
foreach ($pendingApprovalClarificationJobs as $pj) {
    $clarificationDetails[$pj['job']['jobID']] = getClarificationDetails($conn, $pj['job']['jobID']);
}

// Filtering helpers
function filterJobsByMonth($jobs, $month) {
    if (!$month) return $jobs;
    return array_filter($jobs, function($item) use ($month) {
        $jobMonth = date('Y-m', strtotime($item['job']['start_date']));
        return $jobMonth === $month;
    });
}

function filterJobsByJobType($jobs, $jobtypeID) {
    if (!$jobtypeID) return $jobs;
    return array_filter($jobs, function($item) use ($jobtypeID) {
        return isset($item['job']['jobtypeID']) && $item['job']['jobtypeID'] == $jobtypeID;
    });
}

if (isset($_GET['filter_month_editable']) && $_GET['filter_month_editable']) {
    $editableJobs = filterJobsByMonth($editableJobs, $_GET['filter_month_editable']);
}
if (isset($_GET['filter_jobtype_editable']) && $_GET['filter_jobtype_editable']) {
    $editableJobs = filterJobsByJobType($editableJobs, $_GET['filter_jobtype_editable']);
}
if (isset($_GET['filter_month_approved']) && $_GET['filter_month_approved']) {
    $readOnlyJobs = filterJobsByMonth($readOnlyJobs, $_GET['filter_month_approved']);
}
if (isset($_GET['filter_jobtype_approved']) && $_GET['filter_jobtype_approved']) {
    $readOnlyJobs = filterJobsByJobType($readOnlyJobs, $_GET['filter_jobtype_approved']);
}
if (isset($_GET['filter_month_rejected']) && $_GET['filter_month_rejected']) {
    $rejectedJobs = filterJobsByMonth($rejectedJobs, $_GET['filter_month_rejected']);
}
if (isset($_GET['filter_jobtype_rejected']) && $_GET['filter_jobtype_rejected']) {
    $rejectedJobs = filterJobsByJobType($rejectedJobs, $_GET['filter_jobtype_rejected']);
}
if (isset($_GET['filter_month_clarification']) && $_GET['filter_month_clarification']) {
    $clarificationJobs = filterJobsByMonth($clarificationJobs, $_GET['filter_month_clarification']);
}
if (isset($_GET['filter_jobtype_clarification']) && $_GET['filter_jobtype_clarification']) {
    $clarificationJobs = filterJobsByJobType($clarificationJobs, $_GET['filter_jobtype_clarification']);
}
if (isset($_GET['filter_month_pending_clarification']) && $_GET['filter_month_pending_clarification']) {
    $pendingApprovalClarificationJobs = filterJobsByMonth($pendingApprovalClarificationJobs, $_GET['filter_month_pending_clarification']);
}
if (isset($_GET['filter_jobtype_pending_clarification']) && $_GET['filter_jobtype_pending_clarification']) {
    $pendingApprovalClarificationJobs = filterJobsByJobType($pendingApprovalClarificationJobs, $_GET['filter_jobtype_pending_clarification']);
}