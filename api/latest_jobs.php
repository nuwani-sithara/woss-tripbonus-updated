<?php
session_start();
header("Content-Type: application/json");
include '../config/dbConnect.php';

// ✅ Accept userID from session OR query string
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

// --- 1. Fetch latest 10 jobs ---
$latestJobs = [];
$sql = "SELECT 
          j.jobID,
          j.start_date,
          j.end_date,
          j.comment,
          jt.type_name as job_type,
          v.vessel_name,
          CASE 
            WHEN a.approval_status IS NULL THEN 'Pending'
            WHEN a.approval_status = 1 THEN 'Approved'
            WHEN a.approval_status = 3 THEN 'Rejected'
            ELSE 'Pending'
          END as status,
          CASE 
            WHEN a.approval_status IS NULL THEN 'warning'
            WHEN a.approval_status = 1 THEN 'success'
            WHEN a.approval_status = 3 THEN 'danger'
            ELSE 'warning'
          END as status_class,
          CASE 
            WHEN a.approval_status IS NULL THEN 'fas fa-clock'
            WHEN a.approval_status = 1 THEN 'fas fa-check-circle'
            WHEN a.approval_status = 3 THEN 'fas fa-times-circle'
            ELSE 'fas fa-clock'
          END as status_icon
        FROM jobs j
        LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
        LEFT JOIN vessels v ON j.vesselID = v.vesselID
        LEFT JOIN approvals a ON j.jobID = a.jobID AND a.approval_stage = 'job_approval'
        WHERE j.jobCreatedBy = $loggedUserID
        GROUP BY j.jobID
        ORDER BY j.start_date DESC
        LIMIT 10";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $latestJobs[] = $row;
    }
}

// --- 2. Fetch total jobs count ---
$totalCount = 0;
$sqlCount = "SELECT COUNT(*) as total FROM jobs WHERE jobCreatedBy = $loggedUserID";
$resCount = $conn->query($sqlCount);
if ($resCount && $row = $resCount->fetch_assoc()) {
    $totalCount = (int)$row['total'];
}

// --- Final Response ---
echo json_encode([
    "success" => true,
    "data" => $latestJobs,
    "returned_count" => count($latestJobs), // only latest 10
    "total_count" => $totalCount            // ALL jobs created by user
]);
