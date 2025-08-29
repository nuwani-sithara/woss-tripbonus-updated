<?php
session_start();
require_once(__DIR__ . '/../config/dbConnect.php');

function getJobsForSupervisorInChargeApproval($conn) {
    // Get jobs that need supervisor-in-charge approval
    // These are jobs that have been created by supervisors but not yet approved by supervisor-in-charge
    // Exclude jobs that have pending clarifications (status = 2)
    $sql = "SELECT j.*, a.approval_status, a.approval_stage
            FROM jobs j
            LEFT JOIN approvals a ON j.jobID = a.jobID AND a.approval_stage = 'supervisor_in_charge_approval'
            WHERE j.jobCreatedBy IS NOT NULL
            AND (a.approvalID IS NULL OR a.approval_status = 0)
            AND NOT EXISTS (
                SELECT 1 FROM clarifications c 
                JOIN approvals a2 ON c.approvalID = a2.approvalID 
                WHERE c.jobID = j.jobID 
                AND a2.approval_stage = 'supervisor_in_charge_approval'
                AND c.clarification_status IN (0, 1)
            )
            ORDER BY j.start_date DESC";
    
    $result = $conn->query($sql);
    $jobs = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobID = $row['jobID'];
            
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
            
            // Get trips for this job
            $trips = [];
            $tripsRes = $conn->query("SELECT * FROM trips WHERE jobID = $jobID ORDER BY trip_date");
            while ($trip = $tripsRes->fetch_assoc()) {
                $tripID = $trip['tripID'];
                
                // Get employees for this trip
                $employees = [];
                $empRes = $conn->query("SELECT e.empID, u.fname, u.lname FROM jobassignments ja JOIN employees e ON ja.empID = e.empID JOIN users u ON e.userID = u.userID WHERE ja.tripID = $tripID");
                while ($emp = $empRes->fetch_assoc()) {
                    $employees[] = $emp;
                }
                
                $trips[] = [
                    'trip' => $trip,
                    'employees' => $employees,
                ];
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
                'approval_status' => $row['approval_status'] ?? 0,
            ];
        }
    }
    
    return $jobs;
}

function getJobsRejectedByOperationsManager($conn) {
    // Get jobs that were rejected by operations manager and need supervisor review
    $sql = "SELECT j.*, a.approval_status, a.approval_stage, a.approval_date
            FROM jobs j
            JOIN approvals a ON j.jobID = a.jobID 
            WHERE a.approval_stage = 'job_approval' 
            AND a.approval_status = 3
            ORDER BY a.approval_date DESC";
    
    $result = $conn->query($sql);
    $jobs = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobID = $row['jobID'];
            
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
            
            // Get trips for this job
            $trips = [];
            $tripsRes = $conn->query("SELECT * FROM trips WHERE jobID = $jobID ORDER BY trip_date");
            while ($trip = $tripsRes->fetch_assoc()) {
                $tripID = $trip['tripID'];
                
                // Get employees for this trip
                $employees = [];
                $empRes = $conn->query("SELECT e.empID, u.fname, u.lname FROM jobassignments ja JOIN employees e ON ja.empID = e.empID JOIN users u ON e.userID = u.userID WHERE ja.tripID = $tripID");
                while ($emp = $empRes->fetch_assoc()) {
                    $employees[] = $emp;
                }
                
                $trips[] = [
                    'trip' => $trip,
                    'employees' => $employees,
                ];
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
                'approval_status' => $row['approval_status'],
                'approval_date' => $row['approval_date'],
            ];
        }
    }
    
    return $jobs;
}

function getClarificationsToResolve($conn, $userID) {
    // Get clarifications that this supervisor-in-charge needs to resolve
    // These are clarifications requested by Operations Manager for jobs this user approved
    // Only show clarifications where Operations Manager (role_id = 4) is the requester
    $sql = "SELECT c.*, j.*, a.approval_status, a.approval_stage
            FROM clarifications c
            JOIN jobs j ON c.jobID = j.jobID
            JOIN approvals a ON c.approvalID = a.approvalID
            WHERE c.clarification_resolverID = ? 
            AND c.clarification_status = 0
            AND a.approval_stage = 'job_approval'
            AND c.clarification_requesterID IN (
                SELECT userID FROM users WHERE roleID = 4
            )
            ORDER BY c.clarification_id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clarifications = [];
    while ($row = $result->fetch_assoc()) {
        $clarifications[] = $row;
    }
    
    return $clarifications;
}

function getPendingClarificationResponses($conn, $userID) {
    // Get clarifications requested by this supervisor-in-charge that are waiting for response
    // These are clarifications this user requested from supervisors
    // Only show clarifications where supervisor-in-charge (role_id = 13) is the requester
    $sql = "SELECT c.*, j.*, a.approval_status, a.approval_stage
            FROM clarifications c
            JOIN jobs j ON c.jobID = j.jobID
            JOIN approvals a ON c.approvalID = a.approvalID
            WHERE c.clarification_requesterID = ? 
            AND c.clarification_status = 0
            AND a.approval_stage = 'supervisor_in_charge_approval'
            AND c.clarification_requesterID IN (
                SELECT userID FROM users WHERE roleID = 13
            )
            ORDER BY c.clarification_id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clarifications = [];
    while ($row = $result->fetch_assoc()) {
        $clarifications[] = $row;
    }
    
    return $clarifications;
}

function getResolvedClarificationsForApproval($conn, $userID) {
    // Get clarifications that have been resolved by supervisors and need Supervisor-in-Charge approval
    // These are clarifications this user requested that are now resolved and waiting for approval
    // Only show clarifications where supervisor-in-charge (role_id = 13) is the requester
    $sql = "SELECT c.*, j.*, a.approval_status, a.approval_stage
            FROM clarifications c
            JOIN jobs j ON c.jobID = j.jobID
            JOIN approvals a ON c.approvalID = a.approvalID
            WHERE c.clarification_requesterID = ? 
            AND c.clarification_status = 1
            AND a.approval_stage = 'supervisor_in_charge_approval'
            AND c.clarification_requesterID IN (
                SELECT userID FROM users WHERE roleID = 13
            )
            ORDER BY c.clarification_id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clarifications = [];
    while ($row = $result->fetch_assoc()) {
        $clarifications[] = $row;
    }
    
    return $clarifications;
}

function getJobsWithPendingClarifications($conn, $userID) {
    // Get jobs that have pending clarifications (status = 0) that need to be resolved by supervisors
    // Only show clarifications where supervisor-in-charge (role_id = 13) is the requester
    $sql = "SELECT j.*, c.*, a.approval_status, a.approval_stage
            FROM jobs j
            JOIN clarifications c ON j.jobID = c.jobID
            JOIN approvals a ON c.approvalID = a.approvalID
            WHERE c.clarification_requesterID = ? 
            AND c.clarification_status = 0
            AND a.approval_stage = 'supervisor_in_charge_approval'
            AND c.clarification_requesterID IN (
                SELECT userID FROM users WHERE roleID = 13
            )
            ORDER BY c.clarification_id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobID = $row['jobID'];
        
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
        
        // Get trips for this job
        $trips = [];
        $tripsRes = $conn->query("SELECT * FROM trips WHERE jobID = $jobID ORDER BY trip_date");
        while ($trip = $tripsRes->fetch_assoc()) {
            $tripID = $trip['tripID'];
            
            // Get employees for this trip
            $employees = [];
            $empRes = $conn->query("SELECT e.empID, u.fname, u.lname FROM jobassignments ja JOIN employees e ON ja.empID = e.empID JOIN users u ON e.userID = u.userID WHERE ja.tripID = $tripID");
            while ($emp = $empRes->fetch_assoc()) {
                $employees[] = $emp;
            }
            
            $trips[] = [
                'trip' => $trip,
                'employees' => $employees,
            ];
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
            'approval_status' => $row['approval_status'] ?? 0,
            'clarification' => $row,
        ];
    }
    
    return $jobs;
}

// Handle POST actions for supervisor-in-charge approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jobID'], $_POST['action'])) {
    $jobID = intval($_POST['jobID']);
    $action = intval($_POST['action']); // 1=approve, 2=clarify, 3=reject
    $stage = 'supervisor_in_charge_approval';
    $userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
    
    if (!$userID) {
        header("Location: ../index.php?error=access_denied");
        exit;
    }
    
    // Check if approval already exists
    $existingApproval = $conn->prepare("SELECT approvalID FROM approvals WHERE jobID = ? AND approval_stage = ?");
    $existingApproval->bind_param("is", $jobID, $stage);
    $existingApproval->execute();
    $existingResult = $existingApproval->get_result();
    
    if ($existingResult->num_rows > 0) {
        // Update existing approval
        $approvalRow = $existingResult->fetch_assoc();
        $approvalID = $approvalRow['approvalID'];
        
        $stmt = $conn->prepare("UPDATE approvals SET approval_status = ?, approval_by = ?, approval_date = NOW() WHERE approvalID = ?");
        $stmt->bind_param("iii", $action, $userID, $approvalID);
        $stmt->execute();
    } else {
        // Insert new approval
        $stmt = $conn->prepare("INSERT INTO approvals (approval_status, approval_stage, approval_by, approval_date, jobID) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("isii", $action, $stage, $userID, $jobID);
        $stmt->execute();
        
        $approvalID = $conn->insert_id;
    }
    
    // If approved, create approval record for operations manager
    if ($action == 1) {
        $operationsManagerStage = 'job_approval';
        $operationsManagerApproval = $conn->prepare("INSERT INTO approvals (approval_status, approval_stage, approval_by, approval_date, jobID) VALUES (0, ?, NULL, NULL, ?)");
        $operationsManagerApproval->bind_param("si", $operationsManagerStage, $jobID);
        $operationsManagerApproval->execute();
    }
    
    // If clarification requested, insert into clarification table
    if ($action == 2 && isset($_POST['clarification_comment'])) {
        $comment = $_POST['clarification_comment'];
        
        // Get the job creator (supervisor) who will resolve this clarification
        $jobCreatorQuery = $conn->prepare("SELECT jobCreatedBy FROM jobs WHERE jobID = ?");
        $jobCreatorQuery->bind_param("i", $jobID);
        $jobCreatorQuery->execute();
        $jobCreatorResult = $jobCreatorQuery->get_result();
        $jobCreatorRow = $jobCreatorResult->fetch_assoc();
        $resolverID = $jobCreatorRow['jobCreatedBy']; // Supervisor will resolve
        
        $stmtClarify = $conn->prepare("INSERT INTO clarifications (
            jobID, 
            approvalID, 
            clarification_requesterID, 
            clarification_request_comment, 
            clarification_resolverID,
            clarification_status
        ) VALUES (?, ?, ?, ?, ?, 0)");
        
        $stmtClarify->bind_param("iiisi", $jobID, $approvalID, $userID, $comment, $resolverID);
        $stmtClarify->execute();
        
        // Set approval status to 2 (clarification requested) - this prevents job from proceeding
        $updateApproval = $conn->prepare("UPDATE approvals SET approval_status = 2 WHERE approvalID = ?");
        $updateApproval->bind_param("i", $approvalID);
        $updateApproval->execute();
    }
    
    header("Location: ../views/supervisorinchargeapproval.php");
    exit;
}

// Handle supervisor review of rejected jobs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_jobID'], $_POST['review_action'])) {
    $jobID = intval($_POST['review_jobID']);
    $reviewAction = $_POST['review_action']; // 'modify' or 'resubmit'
    $userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
    
    if (!$userID) {
        header("Location: ../index.php?error=access_denied");
        exit;
    }
    
    if ($reviewAction === 'modify') {
        // Redirect to job modification page
        header("Location: ../views/modifyJob.php?jobID=" . $jobID);
        exit;
    } elseif ($reviewAction === 'resubmit') {
        // Create new approval record for supervisor-in-charge
        $stage = 'supervisor_in_charge_approval';
        $stmt = $conn->prepare("INSERT INTO approvals (approval_status, approval_stage, approval_by, approval_date, jobID) VALUES (0, ?, ?, NOW(), ?)");
        $stmt->bind_param("sii", $stage, $userID, $jobID);
        $stmt->execute();
        
        header("Location: ../views/supervisorinchargeapproval.php?message=job_resubmitted");
        exit;
    }
}

// Handle clarification resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clarification_id'], $_POST['resolution_comment'])) {
    $clarificationID = intval($_POST['clarification_id']);
    $resolutionComment = $_POST['resolution_comment'];
    $userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
    
    if (!$userID) {
        header("Location: ../index.php?error=access_denied");
        exit;
    }
    
    // Update the clarification with resolution - status becomes 1 (resolved, waiting for OM approval)
    $stmt = $conn->prepare("UPDATE clarifications SET clarification_resolved_comment = ?, clarification_status = 1 WHERE clarification_id = ? AND clarification_resolverID = ?");
    $stmt->bind_param("sii", $resolutionComment, $clarificationID, $userID);
    $stmt->execute();
    
    header("Location: ../views/supervisorinchargeapproval.php?message=clarification_resolved");
    exit;
}

// Handle clarification resolution approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clarification_approval_id'], $_POST['clarification_approval_action'])) {
    $clarificationID = intval($_POST['clarification_approval_id']);
    $approvalAction = $_POST['clarification_approval_action']; // 'approve' or 'reject'
    $userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null;
    
    if (!$userID) {
        header("Location: ../index.php?error=access_denied");
        exit;
    }
    
    // Get the jobID and approvalID for this clarification
    $getClarificationInfo = $conn->prepare("SELECT jobID, approvalID FROM clarifications WHERE clarification_id = ? AND clarification_requesterID = ? AND clarification_status = 1");
    $getClarificationInfo->bind_param("ii", $clarificationID, $userID);
    $getClarificationInfo->execute();
    $clarificationInfo = $getClarificationInfo->get_result()->fetch_assoc();
    
    if ($clarificationInfo) {
        $jobID = $clarificationInfo['jobID'];
        $approvalID = $clarificationInfo['approvalID'];
        
        if ($approvalAction === 'approve') {
            // Approve the clarification resolution
            $stmt = $conn->prepare("UPDATE clarifications SET clarification_status = 2 WHERE clarification_id = ?");
            $stmt->bind_param("i", $clarificationID);
            $stmt->execute();
            
            // Update the approval status to 0 (pending) so the job can proceed
            $stmt = $conn->prepare("UPDATE approvals SET approval_status = 0 WHERE approvalID = ?");
            $stmt->bind_param("i", $approvalID);
            $stmt->execute();
            
            header("Location: ../views/supervisorinchargeapproval.php?message=clarification_approved");
        } elseif ($approvalAction === 'reject') {
            // Reject the clarification resolution - keep it open for supervisor to fix
            $stmt = $conn->prepare("UPDATE clarifications SET clarification_status = 0 WHERE clarification_id = ?");
            $stmt->bind_param("i", $clarificationID);
            $stmt->execute();
            
            header("Location: ../views/supervisorinchargeapproval.php?message=clarification_rejected");
        }
    }
    
    exit;
}

function getJobDetailsForClarification($jobID) {
    global $conn;
    
    $jobDetails = [
        'vessel_name' => null,
        'job_type' => null,
        'job_creator' => null
    ];
    
    if (!$jobID) {
        return $jobDetails;
    }
    
    // Get basic job information
    $sql = "SELECT j.*, v.vessel_name, jt.type_name as job_type, u.fname, u.lname 
            FROM jobs j 
            LEFT JOIN vessels v ON j.vesselID = v.vesselID 
            LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID 
            LEFT JOIN users u ON j.jobCreatedBy = u.userID 
            WHERE j.jobID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jobID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $jobDetails['vessel_name'] = $row['vessel_name'] ?? null;
        $jobDetails['job_type'] = $row['job_type'] ?? null;
        
        if ($row['fname'] && $row['lname']) {
            $jobDetails['job_creator'] = [
                'fname' => $row['fname'],
                'lname' => $row['lname']
            ];
        }
    }
    
    return $jobDetails;
}

$jobs = getJobsForSupervisorInChargeApproval($conn);
$rejectedJobs = getJobsRejectedByOperationsManager($conn);
$clarificationsToResolve = getClarificationsToResolve($conn, $_SESSION['userID']);
$pendingClarificationResponses = getPendingClarificationResponses($conn, $_SESSION['userID']);
$resolvedClarificationsForApproval = getResolvedClarificationsForApproval($conn, $_SESSION['userID']);
$jobsWithPendingClarifications = getJobsWithPendingClarifications($conn, $_SESSION['userID']);
