<?php
session_start();
require_once(__DIR__ . '/../config/dbConnect.php');

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

function getJobsForSupervisorInCharge($conn, $search = '') {
    $where = '';
    if ($search !== '') {
        $where = "WHERE j.jobID LIKE '%$search%' 
                  OR v.vessel_name LIKE '%$search%' 
                  OR p.portname LIKE '%$search%' 
                  OR jt.type_name LIKE '%$search%' 
                  OR u.fname LIKE '%$search%' 
                  OR u.lname LIKE '%$search%'";
    }

    $jobs = [];
    $jobsRes = $conn->query("SELECT j.* FROM jobs j $where ORDER BY j.start_date DESC");
    
    while ($job = $jobsRes->fetch_assoc()) {
        $jobID = $job['jobID'];

        // Status logic for Supervisor-in-Charge view
        $status = 'Ongoing';
        $statusClass = 'primary';
        $statusIcon = 'fas fa-play-circle';

        if (!empty($job['end_date'])) {
            // latest SIC approval
            $sicRes = $conn->query("SELECT approval_status FROM approvals WHERE jobID = $jobID AND approval_stage = 'supervisor_in_charge_approval' ORDER BY approvalID DESC LIMIT 1");
            $sicStatus = null;
            if ($sicRes && $sicRow = $sicRes->fetch_assoc()) {
                $sicStatus = (int)$sicRow['approval_status'];
            }

            // latest OM approval
            $omRes = $conn->query("SELECT approval_status FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval' ORDER BY approvalID DESC LIMIT 1");
            $omStatus = null;
            if ($omRes && $omRow = $omRes->fetch_assoc()) {
                $omStatus = (int)$omRow['approval_status'];
            }

            if ($sicStatus === null || $sicStatus === 0) {
                $status = 'Pending (SIC)';
                $statusClass = 'warning';
                $statusIcon = 'fas fa-clock';
            } elseif ($sicStatus === 2) {
                $status = 'Goes to Clarification';
                $statusClass = 'warning';
                $statusIcon = 'fas fa-question-circle';
            } elseif ($sicStatus === 3) {
                $status = 'Rejected by SIC';
                $statusClass = 'danger';
                $statusIcon = 'fas fa-times-circle';
            } elseif ($sicStatus === 1) {
                // Approved by SIC → look at OM state
                if ($omStatus === null) {
                    $status = 'Awaiting OM Approval';
                    $statusClass = 'info';
                    $statusIcon = 'fas fa-paper-plane';
                } else {
                    switch ($omStatus) {
                        case 0:
                            $status = 'Awaiting OM Approval';
                            $statusClass = 'info';
                            $statusIcon = 'fas fa-paper-plane';
                            break;
                        case 1:
                            $status = 'Completed';
                            $statusClass = 'success';
                            $statusIcon = 'fas fa-check-circle';
                            break;
                        case 2:
                            $status = 'OM Clarification';
                            $statusClass = 'warning';
                            $statusIcon = 'fas fa-question-circle';
                            break;
                        case 3:
                            $status = 'Rejected by OM';
                            $statusClass = 'danger';
                            $statusIcon = 'fas fa-times-circle';
                            break;
                        default:
                            $status = 'Awaiting OM Approval';
                            $statusClass = 'info';
                            $statusIcon = 'fas fa-paper-plane';
                    }
                }
            }
        }

        // Job creator
        $creator_fname = '';
        $creator_lname = '';
        if (isset($job['jobCreatedBy'])) {
            $creatorID = intval($job['jobCreatedBy']);
            $creator = $conn->query("SELECT fname, lname FROM users WHERE userID = $creatorID")->fetch_assoc();
            if ($creator) {
                $creator_fname = $creator['fname'];
                $creator_lname = $creator['lname'];
            }
        }

        // Boat
        $boat_name = null;
        $ba = $conn->query("SELECT boatID FROM boatassignments WHERE jobID = $jobID")->fetch_assoc();
        if ($ba) {
            $boat = $conn->query("SELECT boat_name FROM boats WHERE boatID = {$ba['boatID']}")->fetch_assoc();
            $boat_name = $boat['boat_name'] ?? 'N/A';
        }

        // Port
        $portname = null;
        $pa = $conn->query("SELECT portID FROM portassignments WHERE jobID = $jobID")->fetch_assoc();
        if ($pa) {
            $port = $conn->query("SELECT portname FROM ports WHERE portID = {$pa['portID']}")->fetch_assoc();
            $portname = $port['portname'] ?? 'N/A';
        }

        // Vessel
        $vessel_name = null;
        if (isset($job['vesselID'])) {
            $vessel = $conn->query("SELECT vessel_name FROM vessels WHERE vesselID = {$job['vesselID']}")->fetch_assoc();
            $vessel_name = $vessel['vessel_name'] ?? 'N/A';
        }

        // Job type
        $job_type = null;
        if (isset($job['jobtypeID'])) {
            $jt = $conn->query("SELECT type_name FROM jobtype WHERE jobtypeID = {$job['jobtypeID']}")->fetch_assoc();
            $job_type = $jt['type_name'] ?? 'N/A';
        }

        // Special projects
        $special_project = null;
        $spRes = $conn->query("SELECT spProjectID FROM jobspecialprojects WHERE jobID = $jobID");
        if ($spRes && $spRow = $spRes->fetch_assoc()) {
            $sp = $conn->query("SELECT sp.*, v.vessel_name FROM specialproject sp LEFT JOIN vessels v ON sp.vesselID = v.vesselID WHERE sp.spProjectID = {$spRow['spProjectID']}")->fetch_assoc();
            if ($sp) {
                $special_project = $sp;
            }
        }

        // Employees (from all trips in this job)
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
                $employees[] = $emp['fname'] . ' ' . $emp['lname'];
            }
        }

        // Trips
        $trips = [];
        $tripRes = $conn->query("SELECT tripID, trip_date FROM trips WHERE jobID = $jobID");
        while ($trip = $tripRes->fetch_assoc()) {
            $tripID = $trip['tripID'];
            // Get employees for this specific trip
            $tripEmployees = [];
            $tripEmpRes = $conn->query("SELECT DISTINCT e.empID, u.fname, u.lname
                FROM jobassignments ja
                JOIN employees e ON ja.empID = e.empID
                JOIN users u ON e.userID = u.userID
                WHERE ja.tripID = $tripID");
            while ($te = $tripEmpRes->fetch_assoc()) {
                $tripEmployees[] = $te['fname'] . ' ' . $te['lname'];
            }
            $trip['employees'] = $tripEmployees;
            $trips[] = $trip;
        }

        $jobs[] = [
            'jobID' => $job['jobID'],
            'job_type' => $job_type,
            'boat_name' => $boat_name,
            'portname' => $portname,
            'vessel_name' => $vessel_name,
            'special_project' => $special_project,
            'creator_fname' => $creator_fname,
            'creator_lname' => $creator_lname,
            'employees' => $employees,
            'trips' => $trips,
            'status' => $status,
            'statusClass' => $statusClass,
            'statusIcon' => $statusIcon
        ];
    }
    
    return $jobs;
}

$jobs = getJobsForSupervisorInCharge($conn, $search);

require_once(__DIR__ . '/../views/supervisorinchargejobs.php');
?>


