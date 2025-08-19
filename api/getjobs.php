<?php
session_start();
include '../config/dbConnect.php';
header('Content-Type: application/json');

// ---------------------------
// Helper functions
// ---------------------------

// Get job details
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

// Get empID by userID
function getSupervisorEmpID(mysqli $conn, int $userID): ?int {
    $stmt = $conn->prepare("SELECT empID FROM employees WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int)$row['empID'];
    }
    return null;
}

// ---------------------------
// Main logic
// ---------------------------

if (isset($_GET['jobID']) && (int)$_GET['jobID'] > 0) {
    $jobID = (int)$_GET['jobID'];
    $details = getJobDetails($conn, $jobID);
    if ($details) {
        echo json_encode([
            'status' => 'success',
            'data' => $details
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Job not found.'
        ]);
    }
} elseif (isset($_GET['userID']) && (int)$_GET['userID'] > 0) {
    $userID = (int)$_GET['userID'];
    $empID = getSupervisorEmpID($conn, $userID);
    if ($empID !== null) {
        echo json_encode([
            'status' => 'success',
            'empID' => $empID
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Employee not found'
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Provide either jobID or userID.'
    ]);
}

$conn->close();
?>
