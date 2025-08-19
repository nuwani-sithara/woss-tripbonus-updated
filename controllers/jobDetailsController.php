<?php
session_start();
include '../config/dbConnect.php';

// Check if user is logged in and has appropriate role (supervisor or operation manager)
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || !in_array($_SESSION['roleID'], [1, 4])) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Check if jobID is provided
if (!isset($_GET['jobID']) || empty($_GET['jobID'])) {
    header("Location: " . ($_SESSION['roleID'] == 1 ? "supervisoreditjobs.php" : "approvejobs.php") . "?error=invalid_job");
    exit();
}

$jobID = intval($_GET['jobID']);

// Function to get comprehensive job details
function getJobDetails($conn, $jobID) {
    $jobID = intval($jobID);
    
    // Get main job details with all related information
    $sql = "SELECT 
                j.*,
                jt.type_name as job_type_name,
                v.vessel_name,
                b.boat_name,
                p.portname,
                u.fname as created_by_fname,
                u.lname as created_by_lname,
                u.email as created_by_email
            FROM jobs j
            LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
            LEFT JOIN vessels v ON j.vesselID = v.vesselID
            LEFT JOIN boatassignments ba ON j.jobID = ba.jobID
            LEFT JOIN boats b ON ba.boatID = b.boatID
            LEFT JOIN portassignments pa ON j.jobID = pa.jobID
            LEFT JOIN ports p ON pa.portID = p.portID
            LEFT JOIN users u ON j.jobCreatedBy = u.userID
            WHERE j.jobID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jobID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $job = $result->fetch_assoc();
    
    // Get trip details with employee assignments
    $tripSql = "SELECT 
                t.tripID,
                t.trip_date
            FROM trips t
            WHERE t.jobID = ?
            ORDER BY t.trip_date";

    $tripStmt = $conn->prepare($tripSql);
    $tripStmt->bind_param("i", $jobID);
    $tripStmt->execute();
    $tripResult = $tripStmt->get_result();

    $trips = [];
    $tripDates = [];
    while ($trip = $tripResult->fetch_assoc()) {
        $date = $trip['trip_date'];
        if (!isset($tripDates[$date])) {
            $tripDates[$date] = [];
        }
        $tripDates[$date][] = $trip['tripID'];
    }

    // Now, for each unique date, get all employees assigned to any trip on that date
    foreach ($tripDates as $date => $tripIDs) {
        $employeeDetails = [];
        $employeeIDs = [];
        foreach ($tripIDs as $tripID) {
            $emps = getTripEmployees($conn, $tripID);
            foreach ($emps as $emp) {
                if (!in_array($emp['empID'], $employeeIDs)) {
                    $employeeDetails[] = $emp;
                    $employeeIDs[] = $emp['empID'];
                }
            }
        }
        $trips[] = [
            'trip_date' => $date,
            'employee_count' => count($employeeDetails),
            'employee_details' => $employeeDetails
        ];
    }
    
    // Get special projects
    $spSql = "SELECT sp.* FROM specialproject sp
              JOIN jobspecialprojects jsp ON sp.spProjectID = jsp.spProjectID
              WHERE jsp.jobID = ?";
    
    $spStmt = $conn->prepare($spSql);
    $spStmt->bind_param("i", $jobID);
    $spStmt->execute();
    $spResult = $spStmt->get_result();
    
    $specialProjects = [];
    while ($sp = $spResult->fetch_assoc()) {
        $specialProjects[] = $sp;
    }
    
    // Get all clarifications for this job
    $clarSql = "SELECT 
                    c.*,
                    u1.fname as requested_by_fname,
                    u1.lname as requested_by_lname,
                    u2.fname as resolved_by_fname,
                    u2.lname as resolved_by_lname
                FROM clarifications c
                LEFT JOIN users u1 ON c.clarification_requesterID = u1.userID
                LEFT JOIN users u2 ON c.clarification_resolverID = u2.userID
                WHERE c.jobID = ?
                ORDER BY c.clarification_id DESC";
    
    $clarStmt = $conn->prepare($clarSql);
    $clarStmt->bind_param("i", $jobID);
    $clarStmt->execute();
    $clarResult = $clarStmt->get_result();
    
    $clarifications = [];
    while ($clar = $clarResult->fetch_assoc()) {
        $clarifications[] = $clar;
    }
    
    // Get approval status
    $approvalSql = "SELECT 
                        a.*,
                        u.fname as approved_by_fname,
                        u.lname as approved_by_lname
                    FROM approvals a
                    LEFT JOIN users u ON a.approval_by = u.userID
                    WHERE a.jobID = ?
                    ORDER BY a.approval_date DESC
                    LIMIT 1";
    
    $approvalStmt = $conn->prepare($approvalSql);
    $approvalStmt->bind_param("i", $jobID);
    $approvalStmt->execute();
    $approvalResult = $approvalStmt->get_result();
    
    $approval = null;
    if ($approvalResult->num_rows > 0) {
        $approval = $approvalResult->fetch_assoc();
    }
    
    // Determine current status
    $status = 'Draft';
    $statusClass = 'badge-secondary';
    
    if ($approval) {
        if ($approval['approval_status'] == 1) {
            $status = 'Approved';
            $statusClass = 'badge-success';
        } elseif ($approval['approval_status'] == 2) {
            $status = 'Rejected';
            $statusClass = 'badge-danger';
        }
    }
    
    // Check for pending clarifications
    $pendingClarifications = array_filter($clarifications, function($clar) {
        return $clar['clarification_status'] == 0;
    });
    
    if (!empty($pendingClarifications)) {
        $status = 'Clarification Needed';
        $statusClass = 'badge-warning';
    }
    
    // Check for pending clarification approval
    $pendingApprovalClarifications = array_filter($clarifications, function($clar) {
        return $clar['clarification_status'] == 1;
    });
    
    if (!empty($pendingApprovalClarifications)) {
        $status = 'Pending Approval';
        $statusClass = 'badge-info';
    }
    
    return [
        'job' => $job,
        'trips' => $trips,
        'special_projects' => $specialProjects,
        'clarifications' => $clarifications,
        'approval' => $approval,
        'status' => $status,
        'status_class' => $statusClass
    ];
}

// Get job details
$jobDetails = getJobDetails($conn, $jobID);

if (!$jobDetails) {
    header("Location: " . ($_SESSION['roleID'] == 1 ? "supervisoreditjobs.php" : "approvejobs.php") . "?error=job_not_found");
    exit();
}

// Get employee details for each trip
function getTripEmployees($conn, $tripID) {
    $sql = "SELECT 
                e.empID,
                u.fname,
                u.lname,
                u.email,
                e.empID,
                r.role_name
            FROM jobassignments ja
            JOIN employees e ON ja.empID = e.empID
            JOIN users u ON e.userID = u.userID
            JOIN roles r ON e.roleID = r.roleID
            WHERE ja.tripID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tripID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    while ($emp = $result->fetch_assoc()) {
        $employees[] = $emp;
    }
    
    return $employees;
}

// Add employee details to each trip
// foreach ($jobDetails['trips'] as &$trip) {
//     $trip['employee_details'] = getTripEmployees($conn, $trip['tripID']);
// }
?> 