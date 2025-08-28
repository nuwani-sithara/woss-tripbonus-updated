<?php
require_once '../config/dbConnect.php';
session_start(); // Ensure session is started

// Fetch payments for a given month/year (verified by accountant)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = $_GET['month'] ?? '';
    $year = $_GET['year'] ?? '';

    // Check if Director has already verified this month/year
    $directorCheckSql = "SELECT * FROM directorverify WHERE month = ? AND year = ?";
    $directorCheckStmt = $conn->prepare($directorCheckSql);
    $directorCheckStmt->bind_param("si", $month, $year);
    $directorCheckStmt->execute();
    $directorResult = $directorCheckStmt->get_result();
    $directorVerified = $directorResult->num_rows > 0;
    $directorData = $directorResult->fetch_assoc();

    // Get payments verified by accountant for this month/year
    $sql = "SELECT p.*, e.userID, u.fname, u.lname, u.eno FROM payments p
            INNER JOIN paymentverify pv ON p.month = pv.month AND p.year = pv.year
            LEFT JOIN employees e ON p.empID = e.empID
            LEFT JOIN users u ON e.userID = u.userID
            WHERE p.month = ? AND p.year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);

    // --- Fetch job counts and standby counts for all employees in this month/year ---
    $empIDs = array_column($payments, 'empID');
    $empIDs = array_unique($empIDs);
    $empIDsStr = implode(',', array_map('intval', $empIDs));
    $monthNum = date('n', strtotime($month . ' 1'));
    
    // Job count per empID
    $jobCounts = [];
    if ($empIDsStr) {
        $jobCountSql = "SELECT ja.empID, COUNT(DISTINCT j.jobID) as job_count
            FROM approvals a
            JOIN jobs j ON a.jobID = j.jobID
            JOIN trips t ON j.jobID = t.jobID
            JOIN jobassignments ja ON ja.tripID = t.tripID
            WHERE a.approval_status = 1 AND a.approval_stage = 'job_approval'
              AND MONTH(j.start_date) = ? AND YEAR(j.start_date) = ?
              AND ja.empID IN ($empIDsStr)
            GROUP BY ja.empID";
        $stmtJob = $conn->prepare($jobCountSql);
        $stmtJob->bind_param('ii', $monthNum, $year);
        $stmtJob->execute();
        $resJob = $stmtJob->get_result();
        while ($row = $resJob->fetch_assoc()) {
            $jobCounts[$row['empID']] = $row['job_count'];
        }
        $stmtJob->close();
    }
    
    // Standby count per empID - USING THE SAME LOGIC AS PAYMENT CALCULATION
    $standbyCounts = [];
    if ($empIDsStr) {
        // Get all standbyassignments joined with standby_attendance for the selected month/year
        $standbyCountSql = "SELECT sa.EAID, sa.empID, sa.standby_attendanceID, sa.status, sa.standby_count, s.date as checkInDate
                FROM standbyassignments sa
                JOIN standby_attendance s ON sa.standby_attendanceID = s.standby_attendanceID
                WHERE MONTH(s.date) = ? AND YEAR(s.date) = ?
                AND sa.empID IN ($empIDsStr)";
        
        $stmtStandby = $conn->prepare($standbyCountSql);
        $stmtStandby->bind_param('ii', $monthNum, $year);
        $stmtStandby->execute();
        $resStandby = $stmtStandby->get_result();
        
        while ($row = $resStandby->fetch_assoc()) {
            $empID = $row['empID'];
            $standbyAttendanceID = $row['standby_attendanceID'];
            $status = $row['status'];
            $standbyCount = (int)$row['standby_count'];
            $checkInDate = $row['checkInDate'];
            $count = 0;
            
            if ($status == 0) {
                // Checked out: use stored standby_count
                $count = $standbyCount;
            } else {
                // Checked in: calculate standby count as days spent in office before first job assignment
                // Get the earliest trip date for this employee and standby_attendanceID
                $firstTripSql = "SELECT MIN(DISTINCT ja.date) as first_trip_date
                                 FROM jobassignments jass
                                 JOIN job_attendance ja ON jass.tripID = ja.tripID
                                 WHERE jass.empID = ? AND ja.standby_attendanceID = ?
                                 AND ja.date IS NOT NULL";
                $firstTripStmt = $conn->prepare($firstTripSql);
                if ($firstTripStmt) {
                    $firstTripStmt->bind_param("ii", $empID, $standbyAttendanceID);
                    if ($firstTripStmt->execute()) {
                        $firstTripResult = $firstTripStmt->get_result();
                        if ($firstTripResult) {
                            $firstTripRow = $firstTripResult->fetch_assoc();
                            if ($firstTripRow && $firstTripRow['first_trip_date']) {
                                $checkInDateTime = new DateTime($checkInDate);
                                $firstTripDateTime = new DateTime($firstTripRow['first_trip_date']);
                                $interval = $checkInDateTime->diff($firstTripDateTime);
                                $count = $interval->days; // Days spent waiting in office
                            } else {
                                // No trips assigned yet, count from check-in to today
                                $checkInDateTime = new DateTime($checkInDate);
                                $today = new DateTime();
                                $interval = $checkInDateTime->diff($today);
                                $count = $interval->days;
                            }
                        }
                        $firstTripResult->free();
                    }
                    $firstTripStmt->close();
                }
            }
            
            if (!isset($standbyCounts[$empID])) {
                $standbyCounts[$empID] = 0;
            }
            $standbyCounts[$empID] += $count;
        }
        $stmtStandby->close();
    }
    
    // Add counts to each payment row
    foreach ($payments as &$payment) {
        $empID = $payment['empID'];
        $payment['jobCount'] = $jobCounts[$empID] ?? 0;
        $payment['standbyCount'] = $standbyCounts[$empID] ?? 0;
    }
    unset($payment);

    // Calculate monthly totals for allowances
    $totalsSql = "SELECT 
        SUM(jobAllowance) as totalJobAllowance,
        SUM(jobMealAllowance) as totalJobMealAllowance,
        SUM(standbyAttendanceAllowance) as totalStandbyAttendanceAllowance,
        SUM(standbyMealAllowance) as totalStandbyMealAllowance,
        SUM(reportPreparationAllowance) as totalReportPreparationAllowance,
        SUM(totalDivingAllowance) as totalDivingAllowance
        FROM payments WHERE month = ? AND year = ?";
    $totalsStmt = $conn->prepare($totalsSql);
    $totalsStmt->bind_param('si', $month, $year);
    $totalsStmt->execute();
    $totalsResult = $totalsStmt->get_result();
    $totals = $totalsResult->fetch_assoc();

    // Get previous approver details from paymentverify and users
    $approverSql = "SELECT pv.paymentVerifyBy, pv.paymentVerifyDate, u.fname, u.lname 
                    FROM paymentverify pv 
                    JOIN users u ON pv.paymentVerifyBy = u.userID 
                    WHERE pv.month = ? AND pv.year = ? LIMIT 1";
    $approverStmt = $conn->prepare($approverSql);
    $approverStmt->bind_param('si', $month, $year);
    $approverStmt->execute();
    $approverResult = $approverStmt->get_result();
    $approver = $approverResult->fetch_assoc();

    echo json_encode([
        'payments' => $payments,
        'directorVerified' => $directorVerified,
        'directorData' => $directorData,
        'monthlyTotals' => $totals,
        'approver' => $approver
    ]);
    exit;
}

// Director verifies or rejects all payments for a month/year
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $month = $data['month'];
    $year = $data['year'];
    $action = $data['action'] ?? 'verify';
    $comment = $data['comment'] ?? null;
    $directorID = isset($_SESSION['userID']) ? $_SESSION['userID'] : null; // Use logged-in user

    if (!$directorID) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }

    // Prevent duplicate verification
    $checkSql = "SELECT * FROM directorverify WHERE month = ? AND year = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $month, $year);
    $checkStmt->execute();
    $already = $checkStmt->get_result()->num_rows > 0;

    if ($already) {
        echo json_encode(['success' => false, 'message' => 'Already processed']);
        exit;
    }

    // Set approval status based on action
    $approval_status = ($action === 'reject') ? 3 : 1;

    // Insert into directorverify
    $sql = "INSERT INTO directorverify (directorVerifyBy, month, year, directorVerifyDate, approval_status, comment) VALUES (?, ?, ?, CURDATE(), ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isiis", $directorID, $month, $year, $approval_status, $comment);
    $stmt->execute();
    $directorVerifyID = $conn->insert_id;

    // Insert into approvals
    $approval_stage = 'director_verification';
    $approval_by = $directorID;
    $approval_date = date('Y-m-d H:i:s');
    $sql2 = "INSERT INTO approvals (directorVerifyID, approval_status, approval_stage, approval_by, approval_date) VALUES (?, ?, ?, ?, ?)";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("iisis", $directorVerifyID, $approval_status, $approval_stage, $approval_by, $approval_date);
    $stmt2->execute();
    $approvalID = $conn->insert_id;

    // Insert into directorapprover
    $sql3 = "INSERT INTO directorapprover (approvalID) VALUES (?)";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("i", $approvalID);
    $stmt3->execute();

    // Update published status
    $publishedStatus = ($action === 'reject') ? 3 : 1;
    $updatePublishedSql = "UPDATE published SET published_status = ? WHERE month = ? AND year = ?";
    $updatePublishedStmt = $conn->prepare($updatePublishedSql);
    $updatePublishedStmt->bind_param('isi', $publishedStatus, $month, $year);
    $updatePublishedStmt->execute();

    // Fetch publishedID for this month/year
    $publishedID = null;
    $pubStmt = $conn->prepare("SELECT publishedID FROM published WHERE month = ? AND year = ?");
    $pubStmt->bind_param('si', $month, $year);
    $pubStmt->execute();
    $pubStmt->bind_result($publishedID);
    $pubStmt->fetch();
    $pubStmt->close();

    // Trigger email notification
    if ($approval_status === 1) {
        // Director verified
        $notifyUrl = 'https://subseaops.worldsubsea.lk/controllers/sendDirectorVerifyNotificationController.php';
    } else {
        // Director rejected
        $notifyUrl = 'https://subseaops.worldsubsea.lk/controllers/sendDirectorRejectNotificationController.php';
    }
    $notifyData = [
        'directorVerifyID' => $directorVerifyID,
        'publishedID' => $publishedID
    ];
    $ch = curl_init($notifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $notifyResponse = curl_exec($ch);
    curl_close($ch);

    echo json_encode(['success' => true]);
    exit;
}
?>