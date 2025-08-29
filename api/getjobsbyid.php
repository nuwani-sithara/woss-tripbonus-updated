<?php
require_once(__DIR__ . '/../config/dbConnect.php');
header('Content-Type: application/json');

/** ---------------------------
 *  Utilities
 * --------------------------- */
function json_out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function implode_ints(array $ids): string {
    return implode(',', array_map('intval', $ids));
}

/** ---------------------------
 *  Job Helpers
 * --------------------------- */
function getJobDetails(mysqli $conn, int $jobID): ?array {
    $jobRes = $conn->query("SELECT * FROM jobs WHERE jobID = $jobID");
    if (!$jobRes || $jobRes->num_rows === 0) return null;
    $job = $jobRes->fetch_assoc();

    // Boat
    $boat = null;
    $ba = $conn->query("SELECT boatID FROM boatassignments WHERE jobID = $jobID")->fetch_assoc();
    if ($ba && isset($ba['boatID'])) {
        $boatRes = $conn->query("SELECT * FROM boats WHERE boatID = " . (int)$ba['boatID']);
        if ($boatRes && $boatRes->num_rows) $boat = $boatRes->fetch_assoc();
    }

    // Employees
    $employees = [];
    $empRes = $conn->query("
        SELECT DISTINCT ja.empID, ja.tripID, t.trip_date
        FROM jobassignments ja
        JOIN trips t ON ja.tripID = t.tripID
        WHERE t.jobID = $jobID
    ");
    if ($empRes) {
        while ($empRow = $empRes->fetch_assoc()) {
            $empID = (int)$empRow['empID'];
            $emp = $conn->query("SELECT * FROM employees WHERE empID = $empID")->fetch_assoc();
            if ($emp) {
                $u = $conn->query("SELECT userID, fname, lname FROM users WHERE userID = " . (int)$emp['userID'])->fetch_assoc();
                if ($u) {
                    $employees[] = [
                        'empID' => (int)$emp['empID'],
                        'userID' => (int)$u['userID'],
                        'fname'  => $u['fname'],
                        'lname'  => $u['lname'],
                    ];
                }
            }
        }
    }

    // Port
    $port = null;
    $pa = $conn->query("SELECT portID FROM portassignments WHERE jobID = $jobID")->fetch_assoc();
    if ($pa && isset($pa['portID'])) {
        $portRes = $conn->query("SELECT * FROM ports WHERE portID = " . (int)$pa['portID']);
        if ($portRes && $portRes->num_rows) $port = $portRes->fetch_assoc();
    }

    // Job type
    $job_type = null;
    if (isset($job['jobtypeID']) && (int)$job['jobtypeID'] > 0) {
        $jt = $conn->query("SELECT * FROM jobtype WHERE jobtypeID = " . (int)$job['jobtypeID'])->fetch_assoc();
        if ($jt) $job_type = $jt;
    }

    // Special projects
    $special_projects = [];
    $spRes = $conn->query("
        SELECT sp.spProjectID, sp.name, sp.vesselID, sp.date, sp.evidence, v.vessel_name
        FROM specialproject sp
        JOIN jobspecialprojects jsp ON sp.spProjectID = jsp.spProjectID
        LEFT JOIN vessels v ON sp.vesselID = v.vesselID
        WHERE jsp.jobID = $jobID
    ");
    if ($spRes) {
        while ($sp = $spRes->fetch_assoc()) {
            $special_projects[] = [
                'spProjectID' => (int)$sp['spProjectID'],
                'name'        => $sp['name'],
                'vessel'      => $sp['vessel_name'],
                'date'        => $sp['date'],
                'evidence'    => $sp['evidence'],
            ];
        }
    }

    // Vessel
    $vessel = null;
    if (isset($job['vesselID']) && (int)$job['vesselID'] > 0) {
        $vesselRes = $conn->query("SELECT * FROM vessels WHERE vesselID = " . (int)$job['vesselID']);
        if ($vesselRes && $vesselRes->num_rows) $vessel = $vesselRes->fetch_assoc();
    }

    return [
        'job'               => $job,
        'boat'              => $boat,
        'employees'         => $employees,
        'port'              => $port,
        'job_type'          => $job_type,
        'special_projects'  => $special_projects,
        'vessel'            => $vessel,
    ];
}

/** ---------------------------
 *  Clarification Helpers
 * --------------------------- */
function getClarificationDetails(mysqli $conn, int $jobID): array {
    $rows = [];
    $res = $conn->query("SELECT * FROM clarifications WHERE jobID = $jobID ORDER BY clarification_id DESC");
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

/** ---------------------------
 *  Status Helpers
 * --------------------------- */
function getJobStatus(mysqli $conn, int $jobID, int $userID): array {
    // 🔹 Check clarification (needs more info)
    $clarRes = $conn->query("
        SELECT approvalID 
        FROM approvals 
        WHERE jobID = $jobID 
          AND approval_status = 2
        LIMIT 1
    ");
    if ($clarRes && $clarRes->num_rows > 0) {
        $row = $clarRes->fetch_assoc();
        return [
            "status" => "clarification",
            "approvalID" => (int)$row['approvalID'],
            "clarifications" => getClarificationDetails($conn, $jobID)
        ];
    }

    // 🔹 Check pending clarification approval
    $pendingRes = $conn->query("
        SELECT approvalID 
        FROM clarifications 
        WHERE jobID = $jobID 
          AND clarification_status = 1
        LIMIT 1
    ");
    if ($pendingRes && $pendingRes->num_rows > 0) {
        $row = $pendingRes->fetch_assoc();
        return [
            "status" => "pending_clarification",
            "approvalID" => (int)$row['approvalID'],
            "clarifications" => getClarificationDetails($conn, $jobID)
        ];
    }

    // 🔹 Check rejected
    $rejRes = $conn->query("
        SELECT approvalID, approval_stage
        FROM approvals 
        WHERE jobID = $jobID 
        AND approval_status = 3
        AND approval_stage IN ('job_approval', 'supervisor_in_charge_approval')
        LIMIT 1
    ");

    if ($rejRes && $rejRes->num_rows > 0) {
        $row = $rejRes->fetch_assoc();

        // Map rejection stage to message
        $status = ($row['approval_stage'] === 'job_approval')
            ? "rejected by OM"
            : "rejected by SIC";

        return [
            "status" => $status,
            "approvalID" => (int)$row['approvalID']
        ];
    }


    // 🔹 Check approved → readonly
    $res = $conn->query("
        SELECT 1 
        FROM approvals 
        WHERE jobID = $jobID 
          AND approval_status = 1 
          AND approval_stage IN ('job_approval', 'supervisor_in_charge_approval')
        LIMIT 1
    ");
    if ($res && $res->num_rows > 0) {
        return ["status" => "approved"];
    }

    // 🔹 Default editable
    return ["status" => "editable"];
}

/** ---------------------------
 *  API Logic
 * --------------------------- */
$userID = isset($_GET['userID']) ? intval($_GET['userID']) : null;
$jobID  = isset($_GET['jobID']) ? intval($_GET['jobID']) : null;

if ($jobID) {
    $details = getJobDetails($conn, $jobID);
    if ($details) {
        $details['status_info'] = getJobStatus($conn, $jobID, $details['job']['jobCreatedBy']);
        json_out([
            "status" => "success",
            "job" => $details
        ]);
    } else {
        json_out(["status" => "error", "message" => "Job not found"], 404);
    }
}

if ($userID) {
    $stmt = $conn->prepare("SELECT jobID FROM jobs WHERE jobCreatedBy = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobID = (int)$row['jobID'];
        $details = getJobDetails($conn, $jobID);
        if ($details) {
            $details['status_info'] = getJobStatus($conn, $jobID, $userID);
            $jobs[] = $details;
        }
    }

    json_out([
        "status" => "success",
        "jobs"   => $jobs
    ]);
}

json_out([
    "status" => "error",
    "message" => "Invalid or missing parameters."
], 400);
