<?php
session_start();
require_once(__DIR__ . '/../config/dbConnect.php');

function getJobsForApproval($conn) {
    // Get jobs that need operations manager approval
    // Exclude jobs with pending clarifications (status = 2)
    $sql = "SELECT j.*, a.approval_status, a.approval_stage
            FROM jobs j
            JOIN approvals a ON j.jobID = a.jobID 
            WHERE a.approval_stage = 'job_approval' 
            AND a.approval_status = 0
            AND NOT EXISTS (
                SELECT 1 FROM clarifications c 
                JOIN approvals a2 ON c.approvalID = a2.approvalID 
                WHERE c.jobID = j.jobID 
                AND a2.approval_stage = 'job_approval'
                AND c.clarification_status IN (0, 1)
            )
            ORDER BY j.start_date DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobID = $row['jobID'];
            // Get all trips for this job
            $trips = [];
            $tripsRes = $conn->query("SELECT * FROM trips WHERE jobID = $jobID ORDER BY trip_date");
            while ($trip = $tripsRes->fetch_assoc()) {
                $tripID = $trip['tripID'];
                // Get attendance for this trip
                $attendance = $conn->query("SELECT * FROM job_attendance WHERE tripID = $tripID")->fetch_assoc();
                // Get employees for this trip
                $employees = [];
                $empRes = $conn->query("SELECT e.empID, u.fname, u.lname FROM jobassignments ja JOIN employees e ON ja.empID = e.empID JOIN users u ON e.userID = u.userID WHERE ja.tripID = $tripID");
                while ($emp = $empRes->fetch_assoc()) {
                    $employees[] = $emp;
                }
                $trips[] = [
                    'trip' => $trip,
                    'attendance' => $attendance,
                    'employees' => $employees,
                ];
            }
            // Job creator (fname, lname)
            $job_creator = null;
            if (isset($row['jobCreatedBy'])) {
                $creatorID = intval($row['jobCreatedBy']);
                $creator = $conn->query("SELECT fname, lname FROM users WHERE userID = $creatorID")->fetch_assoc();
                if ($creator) {
                    $job_creator = [
                        'fname' => $creator['fname'],
                        'lname' => $creator['lname'],
                    ];
                }
            }
            // Boat
            $boat = null;
            $ba = $conn->query("SELECT boatID FROM boatassignments WHERE jobID = $jobID")->fetch_assoc();
            if ($ba) {
                $boat = $conn->query("SELECT * FROM boats WHERE boatID = {$ba['boatID']}")->fetch_assoc();
            }
            // Port
            $port = null;
            $pa = $conn->query("SELECT portID FROM portassignments WHERE jobID = $jobID")->fetch_assoc();
            if ($pa) {
                $port = $conn->query("SELECT * FROM ports WHERE portID = {$pa['portID']}")->fetch_assoc();
            }
            // Special projects
            $special_projects = [];
            $spRes = $conn->query("SELECT spProjectID FROM jobspecialprojects WHERE jobID = $jobID");
            while ($spRow = $spRes->fetch_assoc()) {
                $sp = $conn->query("SELECT sp.*, v.vessel_name FROM specialproject sp LEFT JOIN vessels v ON sp.vesselID = sp.vesselID WHERE sp.spProjectID = {$spRow['spProjectID']}")->fetch_assoc();
                if ($sp) {
                    $special_projects[] = $sp;
                }
            }
            // Vessel name
            $vessel_name = null;
            if (isset($row['vesselID'])) {
                $vessel = $conn->query("SELECT vessel_name FROM vessels WHERE vesselID = {$row['vesselID']}")->fetch_assoc();
                if ($vessel) {
                    $vessel_name = $vessel['vessel_name'];
                }
            }
            // Job type
            $job_type = null;
            if (isset($row['jobtypeID'])) {
                $jt = $conn->query("SELECT type_name FROM jobtype WHERE jobtypeID = {$row['jobtypeID']}")->fetch_assoc();
                if ($jt) {
                    $job_type = $jt['type_name'];
                }
            }
            $jobs[] = [
                'job' => $row,
                'trips' => $trips,
                'boat' => $boat,
                'port' => $port,
                'special_projects' => $special_projects,
                'vessel_name' => $vessel_name,
                'job_type' => $job_type,
                'job_creator' => $job_creator,
            ];
        }
    }
    return $jobs;
}

function getJobsWithClarifications($conn) {
    // Get jobs that have clarifications waiting for supervisor-in-charge resolution
    $sql = "SELECT c.*, j.*, a.approval_status, a.approval_stage
            FROM clarifications c
            JOIN jobs j ON c.jobID = j.jobID
            JOIN approvals a ON c.approvalID = a.approvalID
            WHERE c.clarification_status = 0
            AND a.approval_stage = 'job_approval'
            ORDER BY c.clarification_id DESC";
    
    $result = $conn->query($sql);
    $jobsByJobID = [];
    while ($row = $result->fetch_assoc()) {
        $jobID = $row['jobID'];
        
        // Initialize job data if not exists
        if (!isset($jobsByJobID[$jobID])) {
            // Job details
            $job = $conn->query("SELECT * FROM jobs WHERE jobID = $jobID")->fetch_assoc();
            // Job creator (fname, lname)
            $job_creator = null;
            if (isset($job['jobCreatedBy'])) {
                $creatorID = intval($job['jobCreatedBy']);
                $creator = $conn->query("SELECT fname, lname FROM users WHERE userID = $creatorID")->fetch_assoc();
                if ($creator) {
                    $job_creator = [
                        'fname' => $creator['fname'],
                        'lname' => $creator['lname'],
                    ];
                }
            }
            // Boat
            $boat = null;
            $ba = $conn->query("SELECT boatID FROM boatassignments WHERE jobID = $jobID")->fetch_assoc();
            if ($ba) {
                $boat = $conn->query("SELECT * FROM boats WHERE boatID = {$ba['boatID']}")->fetch_assoc();
            }
            // Employees
            $employees = [];
            $tripIDs = [];
            $tripsRes = $conn->query("SELECT tripID FROM trips WHERE jobID = $jobID");
            while ($tripRow = $tripsRes->fetch_assoc()) {
                $tripIDs[] = $tripRow['tripID'];
            }
            if (!empty($tripIDs)) {
                $tripIDsStr = implode(',', $tripIDs);
                $empRes = $conn->query("SELECT DISTINCT e.empID, u.fname, u.lname
                    FROM jobassignments ja
                    JOIN employees e ON ja.empID = e.empID
                    JOIN users u ON e.userID = u.userID
                    WHERE ja.tripID IN ($tripIDsStr)");
                while ($emp = $empRes->fetch_assoc()) {
                    $employees[] = [
                        'fname' => $emp['fname'],
                        'lname' => $emp['lname'],
                    ];
                }
            }
            // Port
            $port = null;
            $pa = $conn->query("SELECT portID FROM portassignments WHERE jobID = $jobID")->fetch_assoc();
            if ($pa) {
                $port = $conn->query("SELECT * FROM ports WHERE portID = {$pa['portID']}")->fetch_assoc();
            }
            // Special projects
            $special_projects = [];
            $spRes = $conn->query("SELECT spProjectID FROM jobspecialprojects WHERE jobID = $jobID");
            while ($spRow = $spRes->fetch_assoc()) {
                $sp = $conn->query("SELECT sp.*, v.vessel_name FROM specialproject sp LEFT JOIN vessels v ON sp.vesselID = v.vesselID WHERE sp.spProjectID = {$spRow['spProjectID']}")->fetch_assoc();
                if ($sp) {
                    $special_projects[] = $sp;
                }
            }
            // Vessel name
            $vessel_name = null;
            if (isset($row['vesselID'])) {
                $vessel = $conn->query("SELECT vessel_name FROM vessels WHERE vesselID = {$row['vesselID']}")->fetch_assoc();
                if ($vessel) {
                    $vessel_name = $vessel['vessel_name'];
                }
            }
            // Job type
            $job_type = null;
            if (isset($row['jobtypeID'])) {
                $jt = $conn->query("SELECT type_name FROM jobtype WHERE jobtypeID = {$row['jobtypeID']}")->fetch_assoc();
                if ($jt) {
                    $job_type = $jt['type_name'];
                }
            }
            
            $jobsByJobID[$jobID] = [
                'clarifications' => [], // Array to hold multiple clarifications
                'boat' => $boat,
                'employees' => $employees,
                'port' => $port,
                'special_projects' => $special_projects,
                'vessel_name' => $vessel_name,
                'job_type' => $job_type,
            ];
        }
        
        // Add this clarification to the job's clarifications array
        $jobsByJobID[$jobID]['clarifications'][] = $row;
    }
    
    // Convert to array format expected by the view
    $result = [];
    foreach ($jobsByJobID as $jobID => $jobData) {
        foreach ($jobData['clarifications'] as $clarification) {
            $result[] = [
                'clarification' => $clarification,
                'boat' => $jobData['boat'],
                'employees' => $jobData['employees'],
                'port' => $jobData['port'],
                'special_projects' => $jobData['special_projects'],
                'vessel_name' => $jobData['vessel_name'],
                'job_type' => $jobData['job_type'],
            ];
        }
    }
    
    return $result;
}

function getJobsWithPendingClarificationApproval($conn, $userID) {
    // Get clarifications that have been resolved by supervisor-in-charge and need OM approval
    $sql = "SELECT c.*, j.*, a.approval_status, a.approval_stage
            FROM clarifications c
            JOIN jobs j ON c.jobID = j.jobID
            JOIN approvals a ON c.approvalID = a.approvalID
            WHERE c.clarification_requesterID = ? 
            AND c.clarification_status = 1
            AND a.approval_stage = 'job_approval'
            ORDER BY c.clarification_id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $jobsByJobID = [];
    while ($row = $result->fetch_assoc()) {
        $jobID = $row['jobID'];
        
        // Initialize job data if not exists
        if (!isset($jobsByJobID[$jobID])) {
            // Job details
            $job = $conn->query("SELECT * FROM jobs WHERE jobID = $jobID")->fetch_assoc();
            // Job creator (fname, lname)
            $job_creator = null;
            if (isset($job['jobCreatedBy'])) {
                $creatorID = intval($job['jobCreatedBy']);
                $creator = $conn->query("SELECT fname, lname FROM users WHERE userID = $creatorID")->fetch_assoc();
                if ($creator) {
                    $job_creator = [
                        'fname' => $creator['fname'],
                        'lname' => $creator['lname'],
                    ];
                }
            }
            // Boat
            $boat = null;
            $ba = $conn->query("SELECT boatID FROM boatassignments WHERE jobID = $jobID")->fetch_assoc();
            if ($ba) {
                $boat = $conn->query("SELECT * FROM boats WHERE boatID = {$ba['boatID']}")->fetch_assoc();
            }
            // Employees
            $employees = [];
            $tripIDs = [];
            $tripsRes = $conn->query("SELECT tripID FROM trips WHERE jobID = $jobID");
            while ($tripRow = $tripsRes->fetch_assoc()) {
                $tripIDs[] = $tripRow['tripID'];
            }
            if (!empty($tripIDs)) {
                $tripIDsStr = implode(',', $tripIDs);
                $empRes = $conn->query("SELECT DISTINCT e.empID, u.fname, u.lname
                    FROM jobassignments ja
                    JOIN employees e ON ja.empID = e.empID
                    JOIN users u ON e.userID = u.userID
                    WHERE ja.tripID IN ($tripIDsStr)");
                while ($emp = $empRes->fetch_assoc()) {
                    $employees[] = [
                        'fname' => $emp['fname'],
                        'lname' => $emp['lname'],
                    ];
                }
            }
            // Port
            $port = null;
            $pa = $conn->query("SELECT portID FROM portassignments WHERE jobID = $jobID")->fetch_assoc();
            if ($pa) {
                $port = $conn->query("SELECT * FROM ports WHERE portID = {$pa['portID']}")->fetch_assoc();
            }
            // Special projects
            $special_projects = [];
            $spRes = $conn->query("SELECT spProjectID FROM jobspecialprojects WHERE jobID = $jobID");
            while ($spRow = $spRes->fetch_assoc()) {
                $sp = $conn->query("SELECT sp.*, v.vessel_name FROM specialproject sp LEFT JOIN vessels v ON sp.vesselID = v.vesselID WHERE sp.spProjectID = {$spRow['spProjectID']}")->fetch_assoc();
                if ($sp) {
                    $special_projects[] = $sp;
                }
            }
            // Vessel name
            $vessel_name = null;
            if (isset($row['vesselID'])) {
                $vessel = $conn->query("SELECT vessel_name FROM vessels WHERE vesselID = {$row['vesselID']}")->fetch_assoc();
                if ($vessel) {
                    $vessel_name = $vessel['vessel_name'];
                }
            }
            // Job type
            $job_type = null;
            if (isset($row['jobtypeID'])) {
                $jt = $conn->query("SELECT type_name FROM jobtype WHERE jobtypeID = {$row['jobtypeID']}")->fetch_assoc();
                if ($jt) {
                    $job_type = $jt['type_name'];
                }
            }
            
            $jobsByJobID[$jobID] = [
                'clarifications' => [], // Array to hold multiple clarifications
                'boat' => $boat,
                'employees' => $employees,
                'port' => $port,
                'special_projects' => $special_projects,
                'vessel_name' => $vessel_name,
                'job_type' => $job_type,
            ];
        }
        
        // Add this clarification to the job's clarifications array
        $jobsByJobID[$jobID]['clarifications'][] = $row;
    }
    
    // Convert to array format expected by the view
    $result = [];
    foreach ($jobsByJobID as $jobID => $jobData) {
        foreach ($jobData['clarifications'] as $clarification) {
            $result[] = [
                'clarification' => $clarification,
                'boat' => $jobData['boat'],
                'employees' => $jobData['employees'],
                'port' => $jobData['port'],
                'special_projects' => $jobData['special_projects'],
                'vessel_name' => $jobData['vessel_name'],
                'job_type' => $jobData['job_type'],
            ];
        }
    }
    
    return $result;
}

function getAttendanceVerifiedJobsForApproval($conn) {
    // 1. Get all approvalIDs from attendanceverifier
    $sql = "SELECT av.approvalID, a.jobID
            FROM attendanceverifier av
            JOIN approvals a ON av.approvalID = a.approvalID
            WHERE a.approval_status = 1 AND a.approval_stage = 'Attendance Verification'";
    $result = $conn->query($sql);
    $jobs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobID = $row['jobID'];
            // Check if this job already has a job_approval (approved or rejected)
            $approvalCheck = $conn->query("SELECT 1 FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval' AND (approval_status = 1 OR approval_status = 3) LIMIT 1");
            if ($approvalCheck && $approvalCheck->num_rows > 0) {
                continue; // Already approved/rejected by job approver
            }
            

            // Job attendance row (for structure compatibility)
            $attendance = $conn->query("SELECT ja.* FROM job_attendance ja JOIN trips t ON ja.tripID = t.tripID WHERE t.jobID = $jobID ORDER BY ja.job_attendanceID DESC LIMIT 1")->fetch_assoc();
            if (!$attendance) continue;
            // Job details
            $job = $conn->query("SELECT * FROM jobs WHERE jobID = $jobID")->fetch_assoc();
            // Job creator (fname, lname)
            $job_creator = null;
            if (isset($job['jobCreatedBy'])) {
                $creatorID = intval($job['jobCreatedBy']);
                $creator = $conn->query("SELECT fname, lname FROM users WHERE userID = $creatorID")->fetch_assoc();
                if ($creator) {
                    $job_creator = [
                        'fname' => $creator['fname'],
                        'lname' => $creator['lname'],
                    ];
                }
            }
            // Boat
            $boat = null;
            $ba = $conn->query("SELECT boatID FROM boatassignments WHERE jobID = $jobID")->fetch_assoc();
            if ($ba) {
                $boat = $conn->query("SELECT * FROM boats WHERE boatID = {$ba['boatID']}")->fetch_assoc();
            }
            // Employees
            $employees = [];
            $empRes = $conn->query("SELECT ja.empID FROM jobassignments ja JOIN trips t ON ja.tripID = t.tripID WHERE t.jobID = $jobID");
            while ($empRow = $empRes->fetch_assoc()) {
                $emp = $conn->query("SELECT * FROM employees WHERE empID = {$empRow['empID']}")->fetch_assoc();
                if ($emp) {
                    $user = $conn->query("SELECT fname, lname FROM users WHERE userID = {$emp['userID']}")->fetch_assoc();
                    $employees[] = [
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
            // Special projects
            $special_projects = [];
            $spRes = $conn->query("SELECT spProjectID FROM jobspecialprojects WHERE jobID = $jobID");
            while ($spRow = $spRes->fetch_assoc()) {
                $sp = $conn->query("SELECT sp.*, v.vessel_name FROM specialproject sp LEFT JOIN vessels v ON sp.vesselID = v.vesselID WHERE sp.spProjectID = {$spRow['spProjectID']}")->fetch_assoc();
                if ($sp) {
                    $special_projects[] = $sp;
                }
            }
            // Vessel name
            $vessel_name = null;
            if (isset($job['vesselID'])) {
                $vessel = $conn->query("SELECT vessel_name FROM vessels WHERE vesselID = {$job['vesselID']}")->fetch_assoc();
                if ($vessel) {
                    $vessel_name = $vessel['vessel_name'];
                }
            }
            // Job type
            $job_type = null;
            if (isset($job['jobtypeID'])) {
                $jt = $conn->query("SELECT type_name FROM jobtype WHERE jobtypeID = {$job['jobtypeID']}")->fetch_assoc();
                if ($jt) {
                    $job_type = $jt['type_name'];
                }
            }
            $jobs[] = [
                'attendance' => $attendance,
                'job' => $job,
                'boat' => $boat,
                'employees' => $employees,
                'port' => $port,
                'special_projects' => $special_projects,
                'vessel_name' => $vessel_name,
                'job_type' => $job_type,
                'job_creator' => $job_creator,
            ];
        }
    }
    return $jobs;
}

// Handle POST actions for approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_attendanceID'], $_POST['action'])) {
    $id = intval($_POST['job_attendanceID']);
    $action = intval($_POST['action']); // 1=verify, 2=clarify, 3=reject
    $stage = 'job_approval';
    $userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
    if (!$userID) {
        header("Location: ../index.php?error=access_denied");
        exit;
    }
    
    error_log('Submitted job_attendanceID: ' . $id);
    // Get tripID from job_attendance table
    $getTripID = $conn->prepare("SELECT tripID FROM job_attendance WHERE job_attendanceID = ?");
    if (!$getTripID) throw new Exception("Prepare failed: " . $conn->error);
    $getTripID->bind_param("i", $id);
    if (!$getTripID->execute()) throw new Exception("Execute failed: " . $getTripID->error);
    $getTripID->bind_result($tripID);
    if (!$getTripID->fetch()) throw new Exception("Job attendance record not found");
    $getTripID->close();
    error_log('Fetched tripID: ' . $tripID);
    
    // Get jobID from trips table
    $getJobID = $conn->prepare("SELECT jobID FROM trips WHERE tripID = ?");
    if (!$getJobID) throw new Exception("Prepare failed: " . $conn->error);
    $getJobID->bind_param("i", $tripID);
    if (!$getJobID->execute()) throw new Exception("Execute failed: " . $getJobID->error);
    $getJobID->bind_result($jobID);
    if (!$getJobID->fetch()) throw new Exception("Trip record not found");
    $getJobID->close();
    
    // Insert into approvals table with status 2 for clarification
    $stmt = $conn->prepare("INSERT INTO approvals (approval_status, approval_stage, approval_by, approval_date, jobID) VALUES (?, ?, ?, NOW(), ?)");
    $status = ($action == 2) ? 2 : $action; // Set status to 2 for clarification
    $stmt->bind_param("isii", $status, $stage, $userID, $jobID);
    $stmt->execute();

    $approvalID = $conn->insert_id; // Get the last inserted approvalID

    // Insert into jobApprover table
    $stmtApprover = $conn->prepare("INSERT INTO jobapprover (approvalID) VALUES (?)");
    if (!$stmtApprover) throw new Exception("Prepare failed: " . $conn->error);
    $stmtApprover->bind_param("i", $approvalID);
    if (!$stmtApprover->execute()) throw new Exception("Execute failed: " . $stmtApprover->error);
    $stmtApprover->close();

    // If this is a clarification request, insert into clarification table
    if ($action == 2 && isset($_POST['clarification_comment'])) {
        $comment = $_POST['clarification_comment'];
        
        // Get the supervisor-in-charge who approved this job (they will resolve the clarification)
        $supervisorInChargeQuery = $conn->prepare("SELECT approval_by FROM approvals WHERE jobID = ? AND approval_stage = 'supervisor_in_charge_approval' AND approval_status = 1 ORDER BY approval_date DESC LIMIT 1");
        $supervisorInChargeQuery->bind_param("i", $jobID);
        $supervisorInChargeQuery->execute();
        $supervisorInChargeResult = $supervisorInChargeQuery->get_result();
        $supervisorInChargeRow = $supervisorInChargeResult->fetch_assoc();
        $resolverID = $supervisorInChargeRow['approval_by']; // Supervisor-in-Charge will resolve
        
        $stmtClarify = $conn->prepare("INSERT INTO clarifications (
            jobID, 
            approvalID, 
            clarification_requesterID, 
            clarification_request_comment, 
            clarification_resolverID,
            clarification_status
        ) VALUES (?, ?, ?, ?, ?, 0)");
        
        if (!$stmtClarify) throw new Exception("Prepare failed: " . $conn->error);
        $stmtClarify->bind_param("iiisi", $jobID, $approvalID, $userID, $comment, $resolverID);
        if (!$stmtClarify->execute()) throw new Exception("Execute failed: " . $stmtClarify->error);
        $stmtClarify->close();
    }

    header("Location: ../views/approvejobs.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clarification_pending_id'], $_POST['clarification_action'])) {
    // This handles approval/rejection of clarification_status = 2
    $clarification_id = intval($_POST['clarification_pending_id']);
    $action = intval($_POST['clarification_action']); // 1=approve, 3=reject
    $userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
    if (!$userID) {
        header("Location: ../index.php");
        exit;
    }
    // Get jobID and approvalID from clarifications table
    $getClar = $conn->prepare("SELECT jobID, approvalID FROM clarifications WHERE clarification_id = ? AND clarification_requesterID = ? AND clarification_status = 1");
    if (!$getClar) throw new Exception("Prepare failed: " . $conn->error);
    $getClar->bind_param("ii", $clarification_id, $userID);
    if (!$getClar->execute()) throw new Exception("Execute failed: " . $getClar->error);
    $getClar->bind_result($jobID, $approvalID);
    if (!$getClar->fetch()) throw new Exception("Clarification record not found");
    $getClar->close();
    // Update clarifications table: set clarification_status = 1 (resolved)
    $stmtClar = $conn->prepare("UPDATE clarifications SET clarification_status = 2 WHERE clarification_id = ?");
    if (!$stmtClar) throw new Exception("Prepare failed: " . $conn->error);
    $stmtClar->bind_param("i", $clarification_id);
    if (!$stmtClar->execute()) throw new Exception("Execute failed: " . $stmtClar->error);
    $stmtClar->close();
    // Update approvals table: set approval_status = 1 (approved) or 3 (rejected)
    $stmtApp = $conn->prepare("UPDATE approvals SET approval_status = ? WHERE approvalID = ?");
    if (!$stmtApp) throw new Exception("Prepare failed: " . $conn->error);
    $stmtApp->bind_param("ii", $action, $approvalID);
    if (!$stmtApp->execute()) throw new Exception("Execute failed: " . $stmtApp->error);
    $stmtApp->close();
    header("Location: ../views/approvejobs.php");
    exit;
}

$jobs = getJobsForApproval($conn);
// Merge in jobs that have been verified by attendance but not yet approved by job approver
$attendanceVerifiedJobs = getAttendanceVerifiedJobsForApproval($conn);
// Avoid duplicates (by jobID)
$existingJobIDs = array_map(function($item) { return $item['job']['jobID']; }, $jobs);
foreach ($attendanceVerifiedJobs as $avJob) {
    if (!in_array($avJob['job']['jobID'], $existingJobIDs)) {
        $jobs[] = $avJob;
    }
}
$jobsWithClarifications = getJobsWithClarifications($conn);

// Get userID from session for the pending clarification approval function
$userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
$jobsWithPendingClarificationApproval = getJobsWithPendingClarificationApproval($conn, $userID);
