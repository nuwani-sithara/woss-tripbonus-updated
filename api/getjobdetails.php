<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include '../config/dbConnect.php';

// Validate jobID
$jobID = isset($_GET['jobID']) ? intval($_GET['jobID']) : 0;
if ($jobID <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid jobID"]);
    exit;
}

$response = [
    "job" => null,
    "isJobEditable" => true,
    "trips" => [],
    "diver_list" => [],
    "all_divers" => [],
    "assignedDivers" => []
];

try {
    // Get job details
    $jobQuery = "SELECT j.*, v.vessel_name, jt.type_name, b.boat_name, p.portname 
                 FROM jobs j
                 LEFT JOIN vessels v ON j.vesselID = v.vesselID
                 LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
                 LEFT JOIN boatassignments ba ON j.jobID = ba.jobID
                 LEFT JOIN boats b ON ba.boatID = b.boatID
                 LEFT JOIN portassignments pa ON j.jobID = pa.jobID
                 LEFT JOIN ports p ON pa.portID = p.portID
                 WHERE j.jobID = $jobID";
    $jobResult = mysqli_query($conn, $jobQuery);
    $job = mysqli_fetch_assoc($jobResult);

    $response["job"] = $job;

    // Check approval
    $isJobEditable = true;
    $approvalCheck = mysqli_query($conn, "SELECT approval_stage FROM approvals WHERE jobID = $jobID");
    $isApproved = false;
    if ($approvalCheck && mysqli_num_rows($approvalCheck) > 0) {
        $approvalRow = mysqli_fetch_assoc($approvalCheck);
        $isApproved = ($approvalRow['approval_stage'] === 'job_approval');
    }

    // If approved and has end_date, not editable
    if ($isApproved && !empty($job['end_date'])) {
        $isJobEditable = false;
    }
    $response["isJobEditable"] = $isJobEditable;

    // Get trips
    $tripsQuery = "SELECT t.*, COUNT(ja.empID) as employee_count 
                   FROM trips t
                   LEFT JOIN jobassignments ja ON t.tripID = ja.tripID
                   WHERE t.jobID = $jobID
                   GROUP BY t.tripID
                   ORDER BY t.trip_date";
    $tripsResult = mysqli_query($conn, $tripsQuery);
    while ($row = mysqli_fetch_assoc($tripsResult)) {
        $response["trips"][] = $row;
    }

    // Get divers if there are trips
    $firstTripID = $response["trips"][0]['tripID'] ?? null;
    if ($firstTripID) {
        // Standby divers
        $diverQuery = "SELECT DISTINCT u.userID, u.fname, u.lname
                       FROM standbyassignments sa
                       JOIN employees e ON sa.empID = e.empID
                       JOIN users u ON e.userID = u.userID
                       JOIN standby_attendance sta ON sa.standby_attendanceID = sta.standby_attendanceID
                       WHERE u.roleID IN (2, 8, 9)
                       AND sa.status = 1";
        $diverResult = mysqli_query($conn, $diverQuery);
        while ($diver = mysqli_fetch_assoc($diverResult)) {
            $response["diver_list"][] = $diver;
        }

        // Latest standby attendance ID
        $standbyQuery = "SELECT standby_attendanceID 
                         FROM standby_attendance 
                         ORDER BY date DESC LIMIT 1";
        $standbyResult = mysqli_query($conn, $standbyQuery);
        $standbyRow = mysqli_fetch_assoc($standbyResult);
        $response["standby_attendanceID"] = $standbyRow['standby_attendanceID'] ?? null;

        // Other divers not in standby
        $allDiversQuery = "SELECT u.userID, u.fname, u.lname 
                           FROM users u 
                           WHERE u.roleID IN (2, 8, 9) 
                           AND u.userID NOT IN (
                               SELECT DISTINCT u.userID
                               FROM standbyassignments sa
                               JOIN employees e ON sa.empID = e.empID
                               JOIN users u ON e.userID = u.userID
                               WHERE sa.status = 1
                                 AND u.roleID IN (2, 8, 9)
                           )";
        $allDiversResult = mysqli_query($conn, $allDiversQuery);
        while ($diver = mysqli_fetch_assoc($allDiversResult)) {
            $response["all_divers"][] = $diver;
        }

        // Assigned divers for this trip
        $assignedQuery = "SELECT u.userID, u.fname, u.lname 
                          FROM jobassignments ja
                          JOIN employees e ON ja.empID = e.empID
                          JOIN users u ON e.userID = u.userID
                          WHERE ja.tripID = $firstTripID";
        $assignedResult = mysqli_query($conn, $assignedQuery);
        while ($diver = mysqli_fetch_assoc($assignedResult)) {
            $response["assignedDivers"][$diver['userID']] = true;
        }
    }

    echo json_encode(["status" => "success", "data" => $response], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

mysqli_close($conn);
