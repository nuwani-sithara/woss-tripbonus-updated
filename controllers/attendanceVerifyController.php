<?php
require_once("../config/dbConnect.php");
session_start();

$response = [
    'jobsToVerify' => [],
    'error' => null
];

try {
    // Fetch trips where job_attendance needs verification
    $jobsToVerifyQuery = "
        SELECT 
            ja.job_attendanceID, t.jobID, ja.tripID, ja.standby_attendanceID, ja.date AS job_date, ja.attendance_status AS job_attendance_status, 
            ja.approved_attendance_status AS job_approved_status, ja.approved_date AS job_approved_date,
            sa.date AS standby_date, sa.attendance_status AS standby_attendance_status, 
            sa.approved_attendance_status AS standby_approved_status, sa.approved_date AS standby_approved_date,
            j.jobtypeID, jt.type_name AS job_type_name, j.start_date AS job_created_at,
            t.trip_date
        FROM job_attendance ja
        JOIN trips t ON ja.tripID = t.tripID
        JOIN standby_attendance sa ON ja.standby_attendanceID = sa.standby_attendanceID
        JOIN jobs j ON t.jobID = j.jobID
        JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
        WHERE ja.attendance_status = 0
        ORDER BY t.jobID, t.tripID
    ";

    $jobsToVerify = [];
    $result = $conn->query($jobsToVerifyQuery);
    if ($result) {
        $jobs = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($jobs as $job) {
            $jobID = $job['jobID'];
            $tripID = $job['tripID'];
            $standby_attendanceID = $job['standby_attendanceID'];

            // Get assigned employees for this specific trip
            $assignedEmployeesQuery = "
                SELECT e.empID, u.fname, u.lname
                FROM jobassignments ja
                JOIN employees e ON ja.empID = e.empID
                JOIN users u ON e.userID = u.userID
                WHERE ja.tripID = $tripID
            ";
            $assignedEmployees = $conn->query($assignedEmployeesQuery)->fetch_all(MYSQLI_ASSOC);

            // Get standby status for each assigned employee with check-in time
            $standbyStatusQuery = "
                SELECT 
                    sa.empID, 
                    sa.status, 
                    sa.EAID,
                    sta.date AS checkInDate
                FROM standbyassignments sa
                JOIN standby_attendance sta ON sa.standby_attendanceID = sta.standby_attendanceID
                WHERE sa.EAID IN (
                    SELECT MAX(sa2.EAID)
                    FROM standbyassignments sa2
                    WHERE sa2.standby_attendanceID = $standby_attendanceID
                    GROUP BY sa2.empID
                )
                AND sa.standby_attendanceID = $standby_attendanceID
            ";

            $result = $conn->query($standbyStatusQuery);
            if (!$result) {
                error_log("Query failed: " . $conn->error);
                throw new Exception("Failed to fetch standby statuses");
            }

            $standbyStatuses = $result->fetch_all(MYSQLI_ASSOC);
            $standbyStatusMap = [];
            $checkInTimes = [];

            foreach ($standbyStatuses as $record) {
                $standbyStatusMap[$record['empID']] = $record['status'];
                $checkInTimes[$record['empID']] = $record['checkInDate']; // Store check-in date/time
            }

            // Debugging
            error_log("Standby status with check-in times:");
            error_log(print_r($standbyStatuses, true));

            // Categorize employees
            $missed_standby_employees = [];
            $checked_out_employees = [];

            foreach ($assignedEmployees as &$emp) {
                $empID = $emp['empID'];
                
                if (!isset($standbyStatusMap[$empID])) {
                    $emp['standby_status'] = null;
                    $missed_standby_employees[] = [
                        'empID' => $empID,
                        'fname' => $emp['fname'],
                        'lname' => $emp['lname'],
                        'status_text' => 'Not Marked Standby',
                        'checkInTime' => null
                    ];
                } elseif ($standbyStatusMap[$empID] == 0) {
                    $emp['standby_status'] = 0;
                    $checked_out_employees[] = [
                        'empID' => $empID,
                        'fname' => $emp['fname'],
                        'lname' => $emp['lname'],
                        'status_text' => 'Checked Out',
                        'checkInTime' => $checkInTimes[$empID] ?? null,
                        'checkOutTime' => $checkInTimes[$empID] ?? null // Assuming same as check-in for now
                    ];
                } else {
                    $emp['standby_status'] = 1;
                    $emp['checkInTime'] = $checkInTimes[$empID] ?? null;
                }
            }
            unset($emp);

            $job['assigned_employees'] = $assignedEmployees;
            $job['missed_standby_employees'] = $missed_standby_employees;
            $job['checked_out_employees'] = $checked_out_employees;
            $jobsToVerify[] = $job;
        }
        $response['jobsToVerify'] = $jobsToVerify;
    } else {
        $response['error'] = $conn->error;
    }

    // Handle POST actions (verify/reject)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $job_attendanceID = $_POST['job_attendanceID'] ?? 0;
        $standby_attendanceID = $_POST['standby_attendanceID'] ?? 0;
        $action = $_POST['action'] ?? '';
        $actionMap = [
            'verify' => 1,
            'reject' => 3
        ];
        if (empty($job_attendanceID) || empty($standby_attendanceID) || empty($action) || !isset($actionMap[$action])) {
            throw new Exception("Missing or invalid parameters");
        }
        $approval_status = $actionMap[$action];
        $approval_stage = 'Attendance Verification';
        if (!isset($_SESSION['userID'])) {
            throw new Exception("User not logged in");
        }
        $approval_by = $_SESSION['userID'];
        $approval_date = date('Y-m-d H:i:s');

        // Get jobID from job_attendance table
        $getTripID = $conn->prepare("SELECT tripID FROM job_attendance WHERE job_attendanceID = ?");
        if (!$getTripID) throw new Exception("Prepare failed: " . $conn->error);
        $getTripID->bind_param("i", $job_attendanceID);
        if (!$getTripID->execute()) throw new Exception("Execute failed: " . $getTripID->error);
        $getTripID->bind_result($tripID);
        if (!$getTripID->fetch()) throw new Exception("Job attendance record not found");
        $getTripID->close();
        
        $getJobID = $conn->prepare("SELECT jobID FROM trips WHERE tripID = ?");
        if (!$getJobID) throw new Exception("Prepare failed: " . $conn->error);
        $getJobID->bind_param("i", $tripID);
        if (!$getJobID->execute()) throw new Exception("Execute failed: " . $getJobID->error);
        $getJobID->bind_result($jobID);
        if (!$getJobID->fetch()) throw new Exception("Trip record not found");
        $getJobID->close();

        // Insert into approvals table with jobID for audit trail
        $stmt = $conn->prepare("INSERT INTO approvals (approval_status, approval_stage, approval_by, approval_date, jobID) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("isisi", $approval_status, $approval_stage, $approval_by, $approval_date, $jobID);
        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
        $approvalID = $stmt->insert_id;
        $stmt->close();

        // Insert into attendanceverifier table for verification tracking
        $stmtVerifier = $conn->prepare("INSERT INTO attendanceverifier (approvalID) VALUES (?)");
        if (!$stmtVerifier) throw new Exception("Prepare failed: " . $conn->error);
        $stmtVerifier->bind_param("i", $approvalID);
        if (!$stmtVerifier->execute()) throw new Exception("Execute failed: " . $stmtVerifier->error);
        $stmtVerifier->close();

        // Update job_attendance
        $updateJob = $conn->prepare("UPDATE job_attendance SET attendance_status=?, approved_attendance_status=?, approved_date=? WHERE job_attendanceID=?");
        if (!$updateJob) throw new Exception("Prepare failed: " . $conn->error);
        $updateJob->bind_param("iisi", $approval_status, $approval_status, $approval_date, $job_attendanceID);
        if (!$updateJob->execute()) throw new Exception("Execute failed: " . $updateJob->error);
        $updateJob->close();

        $response['success'] = true;
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;