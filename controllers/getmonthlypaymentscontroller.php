<?php
// getMonthlyPaymentsController.php
include '../config/dbConnect.php';
session_start();
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) {
    http_response_code(403);
    exit('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = isset($_GET['month']) ? $_GET['month'] : date('F');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Convert month name to number
    $monthNum = date('n', strtotime($month . ' 1'));
    
    // Get job start and end dates for each employee
    $jobDatesSql = "SELECT 
        ja.empID,
        MIN(j.start_date) as job_start_date,
        MAX(j.end_date) as job_end_date
        FROM jobassignments ja
        JOIN trips t ON ja.tripID = t.tripID
        JOIN jobs j ON t.jobID = j.jobID
        WHERE MONTH(j.start_date) = ? AND YEAR(j.start_date) = ?
        GROUP BY ja.empID";
    
    $stmtDates = $conn->prepare($jobDatesSql);
    $stmtDates->bind_param('ii', $monthNum, $year);
    $stmtDates->execute();
    $datesResult = $stmtDates->get_result();
    
    $jobDates = [];
    while ($row = $datesResult->fetch_assoc()) {
        $jobDates[$row['empID']] = [
            'start_date' => $row['job_start_date'],
            'end_date' => $row['job_end_date']
        ];
    }
    $stmtDates->close();
    
    // Get standby counts using the same function as in your payment calculation
    function getStandbyCounts($conn, $month, $year) {
        $allCounts = [];
    $logFile = 'standby_log.txt';
    $logHandle = fopen($logFile, 'a');
    if (!$logHandle) die("Unable to open log file.");

    // Get all standbyassignments joined with standby_attendance for the selected month/year
    $sql = "SELECT sa.EAID, sa.empID, sa.standby_attendanceID, sa.status, sa.standby_count, s.date as checkInDate
            FROM standbyassignments sa
            JOIN standby_attendance s ON sa.standby_attendanceID = s.standby_attendanceID
            WHERE MONTH(s.date) = ? AND YEAR(s.date) = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { fwrite($logHandle, "Prepare failed: " . $conn->error . "\n"); fclose($logHandle); die(); }
    if (!$stmt->bind_param("ii", $month, $year)) { fwrite($logHandle, "Bind failed: " . $stmt->error . "\n"); fclose($logHandle); die(); }
    if (!$stmt->execute()) { fwrite($logHandle, "Execute failed: " . $stmt->error . "\n"); fclose($logHandle); die(); }
    $result = $stmt->get_result();
    if (!$result) { fwrite($logHandle, "Get result failed: " . $stmt->error . "\n"); fclose($logHandle); die(); }

    while ($row = $result->fetch_assoc()) {
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
        if (!isset($allCounts[$empID])) $allCounts[$empID] = 0;
        $allCounts[$empID] += $count;
        fwrite($logHandle, "empID: $empID | standby_attendanceID: $standbyAttendanceID | status: $status | count: $count\n");
    }
    fclose($logHandle);
    $stmt->close();
    return [ 'all' => $allCounts ];
    }
    
    $standbyCountsData = getStandbyCounts($conn, $monthNum, $year);
    $standbyCounts = $standbyCountsData['all'];
    
    // Query payment data
    $paymentsSql = "SELECT p.*, u.eno, u.fname, u.lname, e.empID 
                   FROM payments p 
                   LEFT JOIN employees e ON p.empID = e.empID 
                   LEFT JOIN users u ON e.userID = u.userID 
                   WHERE p.month = ? AND p.year = ?";
    $stmt = $conn->prepare($paymentsSql);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get job counts for each employee
    $empIDs = [];
    $paymentsRows = [];
    while ($row = $result->fetch_assoc()) {
        $empIDs[] = $row['empID'];
        $paymentsRows[] = $row;
    }
    $empIDs = array_unique($empIDs);
    
    $jobCounts = [];
    if (!empty($empIDs)) {
        $empIDsStr = implode(',', array_map('intval', $empIDs));
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
    
    // Calculate totals
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
    
    // Output the table
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover">';
    echo '<thead class="table-light">';
    echo '<tr>
            <th>Employee No</th>
            <th>Employee Name</th>
            <th>Job Count</th>
            <th>Job Start Date</th>
            <th>Job End Date</th>
            <th>Standby Attendance Count</th>
            <th>Job Allowance</th>
            <th>Job Meal Allowance</th>
            <th>Standby Attendance Allowance</th>
            <th>Standby Meal Allowance</th>
            <th>Report Preparation Allowance</th>
            <th>Total Diving Allowance</th>
            <th>Date/Time</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($paymentsRows as $row) {
        $empID = $row['empID'];
        $dates = isset($jobDates[$empID]) ? $jobDates[$empID] : ['start_date' => 'N/A', 'end_date' => 'N/A'];
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['eno']) . '</td>';
        echo '<td>' . htmlspecialchars($row['fname'] . ' ' . $row['lname']) . '</td>';
        echo '<td>' . ($jobCounts[$empID] ?? 0) . '</td>';
        echo '<td>' . ($dates['start_date'] != 'N/A' ? date('Y-m-d', strtotime($dates['start_date'])) : 'N/A') . '</td>';
        echo '<td>' . ($dates['end_date'] != 'N/A' ? date('Y-m-d', strtotime($dates['end_date'])) : 'N/A') . '</td>';
        echo '<td>' . ($standbyCounts[$empID] ?? 0) . '</td>';
        echo '<td>' . number_format($row['jobAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['jobMealAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['standbyAttendanceAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['standbyMealAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['reportPreparationAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['totalDivingAllowance'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($row['date_time']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '<tfoot class="table-group-divider">';
    echo '<tr style="font-weight:bold;background:#e9ecef;">';
    echo '<td colspan="5">Monthly Total</td>';
    echo '<td>' . number_format($totals['totalJobAllowance'], 2) . '</td>';
    echo '<td>' . number_format($totals['totalJobMealAllowance'], 2) . '</td>';
    echo '<td>' . number_format($totals['totalStandbyAttendanceAllowance'], 2) . '</td>';
    echo '<td>' . number_format($totals['totalStandbyMealAllowance'], 2) . '</td>';
    echo '<td>' . number_format($totals['totalReportPreparationAllowance'], 2) . '</td>';
    echo '<td>' . number_format($totals['totalDivingAllowance'], 2) . '</td>';
    echo '<td></td>';
    echo '</tr>';
    echo '</tfoot>';
    echo '</table>';
    echo '</div>';
    
    $stmt->close();
    $totalsStmt->close();
    exit();
}
?>