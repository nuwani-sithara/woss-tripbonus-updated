<?php
require_once(__DIR__ . '/../config/dbConnect.php');
/**
 * Get editable jobs for a supervisor based on userID
 */
function getEditableJobsForSupervisor(mysqli $conn, int $userID): array {
    // ✅ Get supervisor empID
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return [];

    // ✅ Get all jobs assigned to this supervisor (helper function you already have)
    $jobIDs = getAssignedJobIDsForSupervisor($conn, $empID);
    if (empty($jobIDs)) return [];

    $jobIDsStr = implode(',', array_map('intval', $jobIDs));

    // ✅ Find jobs already approved at "job_approval" stage
    $approvedJobIDs = [];
    $approvedRes = $conn->query("
        SELECT jobID 
        FROM approvals 
        WHERE jobID IN ($jobIDsStr) AND approval_stage = 'job_approval'
    ");
    if ($approvedRes) {
        while ($row = $approvedRes->fetch_assoc()) {
            $approvedJobIDs[] = (int)$row['jobID'];
        }
    }

    // ✅ Now filter jobs created by this user
    $editableJobIDs = [];
    $jobsRes = $conn->query("
        SELECT jobID, end_date 
        FROM jobs 
        WHERE jobID IN ($jobIDsStr) AND createdBy = $userID
    ");
    if ($jobsRes) {
        while ($j = $jobsRes->fetch_assoc()) {
            $jid = (int)$j['jobID'];
            $endDate = $j['end_date'];

            // Only editable if:
            // 1. Not approved yet OR
            // 2. End date is empty (job not finished)
            if (!in_array($jid, $approvedJobIDs, true) || empty($endDate)) {
                $editableJobIDs[] = $jid;
            }
        }
    }

    if (empty($editableJobIDs)) return [];

    // ✅ Fetch job details for editable jobs
    $editableJobs = [];
    foreach ($editableJobIDs as $jid) {
        $details = getJobDetails($conn, $jid);
        if ($details) {
            $editableJobs[] = $details;
        }
    }

    return $editableJobs;
}

?>
