<?php
session_start();
header("Content-Type: application/json");
include '../config/dbConnect.php';

$loggedUserID = $_SESSION['userID'] ?? null;
if (!$loggedUserID && isset($_GET['userID'])) {
    $loggedUserID = (int)$_GET['userID'];
}
if (!$loggedUserID && isset($_POST['userID'])) {
    $loggedUserID = (int)$_POST['userID'];
}

if (!$loggedUserID) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit();
}

$clarificationJobs = [];
$sql = "SELECT 
          j.jobID,
          j.start_date,
          j.end_date,
          j.comment,
          jt.type_name as job_type,
          v.vessel_name,
          c.clarification_id,
          c.clarification_request_comment,
          c.clarification_status,
          c.clarification_requesterID,
          requester.fname as requester_fname,
          requester.lname as requester_lname
        FROM jobs j
        LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
        LEFT JOIN vessels v ON j.vesselID = v.vesselID
        JOIN clarifications c ON j.jobID = c.jobID
        LEFT JOIN users requester ON c.clarification_requesterID = requester.userID
        WHERE j.jobCreatedBy = $loggedUserID
        AND c.clarification_status = 0
        ORDER BY j.start_date DESC";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $clarificationJobs[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "data" => $clarificationJobs,
    "count" => count($clarificationJobs)
]);
