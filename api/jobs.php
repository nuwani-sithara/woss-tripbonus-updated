<?php
/**
 * supervisorJobsApi.php
 *
 * REST API for Supervisor Job Management
 * --------------------------------------
 * Auth: uses existing session (roleID = 1). Replace with JWT if desired.
 * I/O:  JSON in, JSON out. No redirects, no HTML.
 *
 * ROUTES (examples)
 *  GET    /api/jobs/editable?filter_month=2025-08&jobtype=3
 *  GET    /api/jobs/read-only
 *  GET    /api/jobs/rejected
 *  GET    /api/jobs/clarification
 *  GET    /api/jobs/pending-clarification
 *  GET    /api/jobs/{jobID}
 *  GET    /api/dropdowns
 *  GET    /api/jobs/{jobID}/clarifications
 *  PUT    /api/jobs/{jobID}
 *  DELETE /api/jobs/{jobID}
 *  POST   /api/clarifications/{clarification_id}/resolve   (body: { jobID, clarification_resolved_comment })
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
// Allow CORS if you are calling from frontends; tighten in production.
// header('Access-Control-Allow-Origin: https://your-frontend');
// header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once(__DIR__ . '/../config/dbConnect.php');
// session_start();

// Get auth from query params instead of session
$userID = isset($_GET['userID']) ? intval($_GET['userID']) : 0;
$roleID = isset($_GET['roleID']) ? $_GET['roleID'] : '';

/** ---------------------------
 *  Utilities
 * --------------------------- */
function json_out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
function require_supervisor(int $userID, $roleID): array {
    if (empty($userID) || (int)$roleID !== 1) {
        json_out(['error' => 'Access denied'], 403);
    }
    return [
        'userID' => (int)$userID
    ];
}
function get_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
/** Small helper to safely implode ints */
function implode_ints(array $vals): string {
    return implode(',', array_map(fn($v) => (string)(int)$v, $vals));
}

/** ---------------------------
 *  Domain Helpers (ported/adapted)
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

    // Employees (unique by userID)
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

function getSupervisorEmpID(mysqli $conn, int $userID): ?int {
    $res = $conn->query("SELECT empID FROM employees WHERE userID = $userID");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int)$row['empID'];
    }
    return null;
}
function getAssignedJobIDsForSupervisor(mysqli $conn, int $empID): array {
    $ids = [];
    $res = $conn->query("
        SELECT DISTINCT t.jobID
        FROM jobassignments ja
        JOIN trips t ON ja.tripID = t.tripID
        WHERE ja.empID = $empID
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) $ids[] = (int)$r['jobID'];
    }
    return $ids;
}

function getEditableJobsForSupervisor(mysqli $conn, int $userID): array {
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return [];

    $jobIDs = getAssignedJobIDsForSupervisor($conn, $empID);
    if (!$jobIDs) return [];

    $jobIDsStr = implode_ints($jobIDs);

    // Approved with stage=job_approval
    $approvedJobIDs = [];
    $approvedRes = $conn->query("SELECT jobID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_stage = 'job_approval'");
    if ($approvedRes) {
        while ($row = $approvedRes->fetch_assoc()) $approvedJobIDs[] = (int)$row['jobID'];
    }

    $editable = [];
    $jobsRes = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID IN ($jobIDsStr) AND jobCreatedBy = $userID");
    if ($jobsRes) {
        while ($j = $jobsRes->fetch_assoc()) {
            $jid = (int)$j['jobID'];
            $end = $j['end_date'];
            if (!in_array($jid, $approvedJobIDs, true) || empty($end)) {
                $editable[] = $jid;
            }
        }
    }
    if (!$editable) return [];
    $editableStr = implode_ints($editable);

    $jobs = [];
    $res = $conn->query("SELECT jobID FROM jobs WHERE jobID IN ($editableStr) AND jobCreatedBy = $userID");
    if ($res) {
        while ($j = $res->fetch_assoc()) {
            $det = getJobDetails($conn, (int)$j['jobID']);
            if ($det) $jobs[] = $det;
        }
    }
    return $jobs;
}

function getReadOnlyJobsForSupervisor(mysqli $conn, int $userID): array {
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return [];
    $jobIDs = getAssignedJobIDsForSupervisor($conn, $empID);
    if (!$jobIDs) return [];
    $jobIDsStr = implode_ints($jobIDs);

    $approved = [];
    $res = $conn->query("SELECT jobID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_status = 1 AND approval_stage = 'job_approval'");
    if ($res) while ($r = $res->fetch_assoc()) $approved[] = (int)$r['jobID'];
    if (!$approved) return [];
    $approvedStr = implode_ints($approved);

    $out = [];
    $jr = $conn->query("SELECT jobID FROM jobs WHERE jobID IN ($approvedStr) AND jobCreatedBy = $userID");
    if ($jr) while ($j = $jr->fetch_assoc()) { $d = getJobDetails($conn, (int)$j['jobID']); if ($d) $out[] = $d; }
    return $out;
}

function getClarificationJobsForSupervisor(mysqli $conn, int $userID): array {
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return [];
    $jobIDs = getAssignedJobIDsForSupervisor($conn, $empID);
    if (!$jobIDs) return [];
    $jobIDsStr = implode_ints($jobIDs);

    $clarIDs = [];
    $approvalMap = [];
    $res = $conn->query("SELECT jobID, approvalID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_status = 2");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $clarIDs[] = (int)$r['jobID'];
            $approvalMap[(int)$r['jobID']] = (int)$r['approvalID'];
        }
    }
    if (!$clarIDs) return [];
    $clarStr = implode_ints($clarIDs);

    $out = [];
    $jr = $conn->query("SELECT jobID FROM jobs WHERE jobID IN ($clarStr) AND jobCreatedBy = $userID");
    if ($jr) {
        while ($j = $jr->fetch_assoc()) {
            $det = getJobDetails($conn, (int)$j['jobID']);
            if ($det) {
                $det['approvalID'] = $approvalMap[(int)$j['jobID']] ?? null;
                $out[] = $det;
            }
        }
    }
    return $out;
}
function getClarificationDetails(mysqli $conn, int $jobID): array {
    $rows = [];
    $res = $conn->query("SELECT * FROM clarifications WHERE jobID = $jobID ORDER BY clarification_id DESC");
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function getPendingApprovalClarificationJobsForSupervisor(mysqli $conn, int $userID): array {
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return [];
    $jobIDs = getAssignedJobIDsForSupervisor($conn, $empID);
    if (!$jobIDs) return [];
    $jobIDsStr = implode_ints($jobIDs);

    $pending = [];
    $approvalMap = [];
    $res = $conn->query("SELECT jobID, approvalID FROM clarifications WHERE jobID IN ($jobIDsStr) AND clarification_status = 2");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $pending[] = (int)$r['jobID'];
            $approvalMap[(int)$r['jobID']] = (int)$r['approvalID'];
        }
    }
    if (!$pending) return [];
    $pendStr = implode_ints($pending);

    $out = [];
    $jr = $conn->query("SELECT jobID FROM jobs WHERE jobID IN ($pendStr) AND jobCreatedBy = $userID");
    if ($jr) {
        while ($j = $jr->fetch_assoc()) {
            $det = getJobDetails($conn, (int)$j['jobID']);
            if ($det) {
                $det['approvalID'] = $approvalMap[(int)$j['jobID']] ?? null;
                $out[] = $det;
            }
        }
    }
    return $out;
}

function getRejectedJobsForSupervisor(mysqli $conn, int $userID): array {
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return [];
    $jobIDs = getAssignedJobIDsForSupervisor($conn, $empID);
    if (!$jobIDs) return [];
    $jobIDsStr = implode_ints($jobIDs);

    $rejected = [];
    $res = $conn->query("SELECT jobID FROM approvals WHERE jobID IN ($jobIDsStr) AND approval_status = 3 AND approval_stage = 'job_approval'");
    if ($res) while ($r = $res->fetch_assoc()) $rejected[] = (int)$r['jobID'];
    if (!$rejected) return [];
    $rejStr = implode_ints($rejected);

    $out = [];
    $jr = $conn->query("SELECT jobID FROM jobs WHERE jobID IN ($rejStr) AND jobCreatedBy = $userID");
    if ($jr) while ($j = $jr->fetch_assoc()) { $d = getJobDetails($conn, (int)$j['jobID']); if ($d) $out[] = $d; }
    return $out;
}

function getDropdownOptions(mysqli $conn): array {
    $opt = ['vessels'=>[], 'job_types'=>[], 'ports'=>[], 'boats'=>[], 'employees'=>[], 'special_projects'=>[]];

    $v = $conn->query("SELECT vesselID, vessel_name FROM vessels ORDER BY vessel_name");
    if ($v) while ($r = $v->fetch_assoc()) $opt['vessels'][] = $r;

    $jt = $conn->query("SELECT jobtypeID, type_name FROM jobtype ORDER BY type_name");
    if ($jt) while ($r = $jt->fetch_assoc()) $opt['job_types'][] = $r;

    $p = $conn->query("SELECT portID, portname FROM ports ORDER BY portname");
    if ($p) while ($r = $p->fetch_assoc()) $opt['ports'][] = $r;

    $b = $conn->query("SELECT boatID, boat_name FROM boats ORDER BY boat_name");
    if ($b) while ($r = $b->fetch_assoc()) $opt['boats'][] = $r;

    $e = $conn->query("
        SELECT e.empID, u.fname, u.lname
        FROM employees e
        JOIN users u ON e.userID = u.userID
        ORDER BY u.lname, u.fname
    ");
    if ($e) while ($r = $e->fetch_assoc()) $opt['employees'][] = $r;

    $sp = $conn->query("SELECT spProjectID, name FROM specialproject ORDER BY name");
    if ($sp) while ($r = $sp->fetch_assoc()) $opt['special_projects'][] = $r;

    return $opt;
}

/** Filters (server-side for GET collections) */
function filterJobsByMonth(array $jobs, ?string $month): array {
    if (!$month) return $jobs;
    return array_values(array_filter($jobs, function($item) use ($month) {
        $jobMonth = date('Y-m', strtotime($item['job']['start_date'] ?? '1970-01-01'));
        return $jobMonth === $month;
    }));
}
function filterJobsByJobType(array $jobs, ?int $jobtypeID): array {
    if (!$jobtypeID) return $jobs;
    return array_values(array_filter($jobs, function($item) use ($jobtypeID) {
        return isset($item['job']['jobtypeID']) && (int)$item['job']['jobtypeID'] === $jobtypeID;
    }));
}

/** ---------------------------
 *  Mutations
 * --------------------------- */
function updateJob(mysqli $conn, int $jobID, int $userID, array $data): array {
    // Validate ownership + assignment + approval rules
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return ['success'=>false, 'error'=>'Supervisor profile not found'];

    $jobCheck = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID = $jobID AND jobCreatedBy = $userID");
    if (!$jobCheck || $jobCheck->num_rows === 0) {
        return ['success'=>false, 'error'=>"Job not found or you don't have permission to edit it."];
    }
    $jobData = $jobCheck->fetch_assoc();
    $endDate = $jobData['end_date'];

    $validJob = $conn->query("
        SELECT 1 FROM jobassignments ja
        JOIN trips t ON ja.tripID = t.tripID
        WHERE t.jobID = $jobID AND ja.empID = $empID
    ")->num_rows > 0;

    $isApproved = $conn->query("SELECT 1 FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval'")->num_rows > 0;

    if (!$validJob || ($isApproved && !empty($endDate))) {
        return ['success'=>false, 'error'=>'You cannot edit this job.'];
    }

    // Fields
    $start_date = $conn->real_escape_string($data['start_date'] ?? '');
    $end_date   = $conn->real_escape_string($data['end_date'] ?? '');
    $comment    = $conn->real_escape_string($data['comment'] ?? '');

    // Update job basics (conditionally end_date)
    $updateQuery = "UPDATE jobs SET start_date = '$start_date', comment = '$comment'";
    if ($end_date !== '') {
        $updateQuery .= ", end_date = '$end_date'";
    }
    $updateQuery .= " WHERE jobID = $jobID";
    $conn->query($updateQuery);

    // Optional updates
    if (isset($data['vesselID'])) {
        $vesselID = (int)$data['vesselID'];
        $conn->query("UPDATE jobs SET vesselID = $vesselID WHERE jobID = $jobID");
    }
    if (isset($data['jobtypeID'])) {
        $jobtypeID = (int)$data['jobtypeID'];
        $conn->query("UPDATE jobs SET jobtypeID = $jobtypeID WHERE jobID = $jobID");
    }
    if (isset($data['portID'])) {
        $portID = (int)$data['portID'];
        $conn->query("DELETE FROM portassignments WHERE jobID = $jobID");
        if ($portID > 0) $conn->query("INSERT INTO portassignments (jobID, portID) VALUES ($jobID, $portID)");
    }
    if (isset($data['boatID'])) {
        $boatID = (int)$data['boatID'];
        $conn->query("DELETE FROM boatassignments WHERE jobID = $jobID");
        if ($boatID > 0) $conn->query("INSERT INTO boatassignments (jobID, boatID) VALUES ($jobID, $boatID)");
    }

    // Trip assignments:
    if (isset($data['trip_assignments']) && is_array($data['trip_assignments'])) {
        foreach ($data['trip_assignments'] as $tripID => $assignment) {
            $tripID = (int)$tripID;
            $divers = $assignment['divers'] ?? [];
            $otherDivers = $assignment['otherDivers'] ?? [];

            // reset assignments for trip
            $conn->query("DELETE FROM jobassignments WHERE tripID = $tripID");

            // helper to assign by userID -> empID
            $assignUserIDs = function(array $userIDs) use ($conn, $tripID) {
                foreach ($userIDs as $uid) {
                    $uid = (int)$uid;
                    if ($uid <= 0) continue;
                    $getEmpID = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
                    $getEmpID->bind_param("i", $uid);
                    $getEmpID->execute();
                    $r = $getEmpID->get_result();
                    if ($r && $r->num_rows > 0) {
                        $empRow = $r->fetch_assoc();
                        $empID2 = (int)$empRow['empID'];
                        $assign = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                        $assign->bind_param("ii", $tripID, $empID2);
                        $assign->execute();
                        $assign->close();
                    }
                    $getEmpID->close();
                }
            };
            $assignUserIDs($divers);
            $assignUserIDs($otherDivers);

            // also ensure creator is assigned
            $creatorEmp = getSupervisorEmpID($conn, (int)$userID);
            if ($creatorEmp) {
                $checkAssign = $conn->prepare("SELECT 1 FROM jobassignments WHERE tripID = ? AND empID = ?");
                $checkAssign->bind_param("ii", $tripID, $creatorEmp);
                $checkAssign->execute();
                $checkAssign->store_result();
                if ($checkAssign->num_rows === 0) {
                    $assignCreator = $conn->prepare("INSERT INTO jobassignments (tripID, empID) VALUES (?, ?)");
                    $assignCreator->bind_param("ii", $tripID, $creatorEmp);
                    $assignCreator->execute();
                    $assignCreator->close();
                }
                $checkAssign->close();
            }

            // Attendance
            $standby_attendanceID = isset($data['standby_attendanceID']) ? (int)$data['standby_attendanceID'] : null;
            $tripRow = $conn->query("SELECT trip_date FROM trips WHERE tripID = $tripID")->fetch_assoc();
            $tripDate = $tripRow['trip_date'] ?? date('Y-m-d');

            $checkAttendance = $conn->prepare("SELECT job_attendanceID FROM job_attendance WHERE tripID = ?");
            $checkAttendance->bind_param("i", $tripID);
            $checkAttendance->execute();
            $checkAttendance->store_result();

            $hasOtherDivers = !empty($otherDivers);
            $attendance_status = $hasOtherDivers ? 0 : 1;
            $approved_status = $hasOtherDivers ? 0 : 1;

            if ($checkAttendance->num_rows > 0) {
                $updateAttendance = $conn->prepare("
                    UPDATE job_attendance
                    SET date = ?, standby_attendanceID = ?, attendance_status = ?, approved_attendance_status = ?
                    WHERE tripID = ?
                ");
                $updateAttendance->bind_param("siiii", $tripDate, $standby_attendanceID, $attendance_status, $approved_status, $tripID);
                $updateAttendance->execute();
                $updateAttendance->close();
            } else {
                $approval_date = date('Y-m-d H:i:s');
                $insertAttendance = $conn->prepare("
                    INSERT INTO job_attendance (tripID, date, attendance_status, approved_attendance_status, standby_attendanceID, approved_date)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertAttendance->bind_param("isiiis", $tripID, $tripDate, $attendance_status, $approved_status, $standby_attendanceID, $approval_date);
                $insertAttendance->execute();
                $insertAttendance->close();
            }
            $checkAttendance->close();

            if ($hasOtherDivers && $standby_attendanceID) {
                $updateStandby = $conn->prepare("UPDATE standby_attendance SET attendance_status = 0, approved_attendance_status = 0 WHERE standby_attendanceID = ?");
                $updateStandby->bind_param("i", $standby_attendanceID);
                $updateStandby->execute();
                $updateStandby->close();
            }
        }
    }

    // Special projects
    if (isset($data['special_projects']) && is_array($data['special_projects'])) {
        $conn->query("DELETE FROM jobspecialprojects WHERE jobID = $jobID");
        foreach ($data['special_projects'] as $proj) {
            if (!empty($proj['spProjectID'])) {
                $spProjectID = (int)$proj['spProjectID'];
                $vesselName = $conn->real_escape_string($proj['vessel'] ?? '');
                $date       = $conn->real_escape_string($proj['date'] ?? '');
                $evidence   = $conn->real_escape_string($proj['evidence'] ?? '');

                $vesselID = 0;
                if ($vesselName !== '') {
                    $vRes = $conn->query("SELECT vesselID FROM vessels WHERE vessel_name = '$vesselName'");
                    if ($vRes && $vRes->num_rows > 0) {
                        $vRow = $vRes->fetch_assoc();
                        $vesselID = (int)$vRow['vesselID'];
                    }
                }
                $conn->query("
                    UPDATE specialproject
                    SET vesselID = $vesselID, date = '$date', evidence = '$evidence'
                    WHERE spProjectID = $spProjectID
                ");
                $conn->query("INSERT INTO jobspecialprojects (jobID, spProjectID) VALUES ($jobID, $spProjectID)");
            }
        }
    }

    return ['success'=>true, 'message'=>'Job updated successfully!'];
}

function deleteJobAndRelatedData(mysqli $conn, int $jobID): void {
    $conn->begin_transaction();
    try {
        $tripIDs = [];
        $tr = $conn->query("SELECT tripID FROM trips WHERE jobID = $jobID");
        if ($tr) while ($t = $tr->fetch_assoc()) $tripIDs[] = (int)$t['tripID'];

        if ($tripIDs) {
            $str = implode_ints($tripIDs);
            $conn->query("DELETE FROM jobassignments WHERE tripID IN ($str)");
            $conn->query("DELETE FROM job_attendance WHERE tripID IN ($str)");
            $conn->query("DELETE FROM trips WHERE jobID = $jobID");
        }

        $conn->query("DELETE FROM jobspecialprojects WHERE jobID = $jobID");
        $conn->query("DELETE FROM portassignments WHERE jobID = $jobID");
        $conn->query("DELETE FROM boatassignments WHERE jobID = $jobID");
        $conn->query("DELETE FROM jobs WHERE jobID = $jobID");

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteJob(mysqli $conn, int $jobID, int $userID): array {
    $empID = getSupervisorEmpID($conn, $userID);
    if (!$empID) return ['success'=>false, 'error'=>'Supervisor profile not found'];

    $jobCheck = $conn->query("SELECT jobID, end_date FROM jobs WHERE jobID = $jobID AND jobCreatedBy = $userID");
    if (!$jobCheck || $jobCheck->num_rows === 0) {
        return ['success'=>false, 'error'=>"Job not found or you don't have permission to delete it."];
    }
    $jobData = $jobCheck->fetch_assoc();
    $endDate = $jobData['end_date'];

    $validJob = $conn->query("
        SELECT 1 FROM jobassignments ja
        JOIN trips t ON ja.tripID = t.tripID
        WHERE t.jobID = $jobID AND ja.empID = $empID
    ")->num_rows > 0;

    $isApproved = $conn->query("SELECT 1 FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval'")->num_rows > 0;

    if (!$validJob || ($isApproved && !empty($endDate))) {
        return ['success'=>false, 'error'=>'Cannot delete jobs that have been approved or completed.'];
    }

    deleteJobAndRelatedData($conn, $jobID);
    return ['success'=>true, 'message'=>'Job deleted successfully!'];
}

function resolveClarification(mysqli $conn, int $clarificationID, int $userID, array $data): array {
    $jobID = (int)($data['jobID'] ?? 0);
    if ($jobID <= 0) return ['success'=>false, 'error'=>'jobID required'];

    $resolvedComment = $conn->real_escape_string($data['clarification_resolved_comment'] ?? '');
    $resolverID = $userID;

    $update = $conn->query("
        UPDATE clarifications
        SET clarification_resolverID = $resolverID,
            clarification_resolved_comment = '$resolvedComment',
            clarification_status = 2
        WHERE clarification_id = $clarificationID AND jobID = $jobID
    ");
    if ($update) return ['success'=>true, 'message'=>'Clarification resolved successfully!'];
    return ['success'=>false, 'error'=>'Failed to resolve clarification.'];
}

/** ---------------------------
 *  Router
 * --------------------------- */
$auth = require_supervisor($userID, $roleID);
$userID = $auth['userID'];

// Compute path after your API base. Adjust $apiBase if the script is not at webroot.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$apiBase = ''; // e.g., set to '/api' if this file is mounted under /api
$path = $apiBase ? preg_replace('#^'.preg_quote($apiBase, '#').'#', '', $uri) : $uri;
$parts = array_values(array_filter(explode('/', trim($path, '/')))); // e.g. ['jobs','editable']

$method = $_SERVER['REQUEST_METHOD'];

/** GET query filters */
$filterMonth  = $_GET['filter_month'] ?? ($_GET['month'] ?? null);
$filterTypeId = isset($_GET['jobtype']) ? (int)$_GET['jobtype'] : (isset($_GET['jobtypeID']) ? (int)$_GET['jobtypeID'] : null);

try {
    if (!isset($parts[0])) {
        json_out(['error'=>'Endpoint not found'], 404);
    }

    // /jobs/...
    if ($parts[0] === 'jobs') {
        // GET /jobs/{id}
        if ($method === 'GET' && isset($parts[1]) && is_numeric($parts[1])) {
            $jobID = (int)$parts[1];
            $details = getJobDetails($conn, $jobID);
            if (!$details) json_out(['error'=>'Job not found'], 404);
            json_out($details);
        }

        // GET /jobs/editable|read-only|rejected|clarification|pending-clarification
        if ($method === 'GET' && isset($parts[1])) {
            switch ($parts[1]) {
                case 'editable':
                    $list = getEditableJobsForSupervisor($conn, $userID);
                    $list = filterJobsByJobType(filterJobsByMonth($list, $filterMonth), $filterTypeId);
                    json_out($list);
                case 'read-only':
                    $list = getReadOnlyJobsForSupervisor($conn, $userID);
                    $list = filterJobsByJobType(filterJobsByMonth($list, $filterMonth), $filterTypeId);
                    json_out($list);
                case 'rejected':
                    $list = getRejectedJobsForSupervisor($conn, $userID);
                    $list = filterJobsByJobType(filterJobsByMonth($list, $filterMonth), $filterTypeId);
                    json_out($list);
                case 'clarification':
                    $list = getClarificationJobsForSupervisor($conn, $userID);
                    $list = filterJobsByJobType(filterJobsByMonth($list, $filterMonth), $filterTypeId);
                    json_out($list);
                case 'pending-clarification':
                    $list = getPendingApprovalClarificationJobsForSupervisor($conn, $userID);
                    $list = filterJobsByJobType(filterJobsByMonth($list, $filterMonth), $filterTypeId);
                    json_out($list);
                case 'dropdowns':
                    json_out(getDropdownOptions($conn));
                default:
                    // GET /jobs/{jobID}/clarifications
                    if ($method === 'GET' && isset($parts[2]) && $parts[2] === 'clarifications' && is_numeric($parts[1])) {
                        $jobID = (int)$parts[1];
                        json_out(getClarificationDetails($conn, $jobID));
                    }
                    json_out(['error'=>'Invalid job endpoint'], 404);
            }
        }

        // PUT /jobs/{id}
        if ($method === 'PUT' && isset($parts[1]) && is_numeric($parts[1])) {
            $data = get_json_body();
            $result = updateJob($conn, (int)$parts[1], $userID, $data);
            if ($result['success'] ?? false) json_out($result);
            json_out($result, 400);
        }

        // DELETE /jobs/{id}
        if ($method === 'DELETE' && isset($parts[1]) && is_numeric($parts[1])) {
            $result = deleteJob($conn, (int)$parts[1], $userID);
            if ($result['success'] ?? false) json_out($result);
            json_out($result, 400);
        }

        json_out(['error'=>'Invalid method for /jobs'], 405);
    }

    // /clarifications/{clarification_id}/resolve
    if ($parts[0] === 'clarifications') {
        if ($method === 'POST' && isset($parts[1], $parts[2]) && is_numeric($parts[1]) && $parts[2] === 'resolve') {
            $data = get_json_body();
            $result = resolveClarification($conn, (int)$parts[1], $userID, $data);
            if ($result['success'] ?? false) json_out($result);
            json_out($result, 400);
        }
        json_out(['error'=>'Invalid clarifications endpoint'], 404);
    }

    // /dropdowns (alias)
    if ($parts[0] === 'dropdowns' && $method === 'GET') {
        json_out(getDropdownOptions($conn));
    }

    json_out(['error'=>'Endpoint not found'], 404);

} catch (Throwable $e) {
    // In production, log $e instead of exposing it
    json_out(['error'=>'Server error', 'details'=>$e->getMessage()], 500);
}
