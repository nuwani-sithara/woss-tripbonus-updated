<?php
include '../config/dbConnect.php';

// Add the new standby count function that matches the updated logic
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

// Helper function to get month name
function getMonthName($month) {
    $months = [
        '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
        '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
        '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
    ];
    return $months[$month] ?? 'Unknown';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'getJobs') {
        // Get jobs for selected month/year that are approved
        $month = $_POST['month'];
        $year = $_POST['year'];
        $jobs = [];
        $sql = "SELECT j.jobID, j.start_date, j.comment FROM jobs j
                INNER JOIN approvals a ON j.jobID = a.jobID
                WHERE YEAR(j.start_date) = ? AND MONTH(j.start_date) = ?
                  AND a.approval_status = 1 AND a.approval_stage = 'job_approval'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        if (count($jobs) > 0) {
            echo '<option value="">Select Job</option>';
            foreach ($jobs as $job) {
                $label = 'Job #' . $job['jobID'] . ' - ' . date('Y-m-d', strtotime($job['start_date'])) . ' - ' . htmlspecialchars($job['comment']);
                echo '<option value="' . $job['jobID'] . '">' . $label . '</option>';
            }
        } else {
            echo '<option value="">No jobs found</option>';
        }
        exit();
    }
    if ($action === 'getBreakdown') {
        $jobID = $_POST['jobID'];
        $month = $_POST['month'];
        $year = $_POST['year'];
        // 1. Check approval
        $sql = "SELECT approval_status, approval_stage FROM approvals WHERE jobID = ? ORDER BY approvalID DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $jobID);
        $stmt->execute();
        $result = $stmt->get_result();
        $approval = $result->fetch_assoc();
        if (!$approval || $approval['approval_status'] != 1 || $approval['approval_stage'] !== 'job_approval') {
            echo '<div class="alert alert-warning">This job is not approved or not at the correct approval stage.</div>';
            exit();
        }
        // 2. Get assigned employees with day counts (jobassignments) - Updated for trips structure
        $assigned = [];
        $sql = "SELECT ja.empID, COUNT(*) as day_count 
                FROM jobassignments ja 
                JOIN trips t ON ja.tripID = t.tripID 
                WHERE t.jobID = ?
                GROUP BY ja.empID";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $jobID);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assigned[$row['empID']] = $row['day_count'];
        }
        // 3. Get all unique standby_attendanceIDs for this job - Updated for trips structure
        $standby_attendanceIDs = [];
        $sql = "SELECT DISTINCT ja.standby_attendanceID 
                FROM job_attendance ja 
                JOIN trips t ON ja.tripID = t.tripID 
                WHERE t.jobID = ? AND ja.standby_attendanceID IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $jobID);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['standby_attendanceID']) {
                $standby_attendanceIDs[] = $row['standby_attendanceID'];
            }
        }
        // 4. For each unique standby_attendanceID, only process if this job is the first APPROVED job for that standby_attendanceID
        $standby_empIDs = [];
        $report_preparation_by = null;
        $debug_log = [];
        foreach ($standby_attendanceIDs as $standby_attendanceID) {
            // Find all jobs that use this standby_attendanceID, ordered by jobID
            $sql = "SELECT t.jobID FROM job_attendance ja 
                    JOIN trips t ON ja.tripID = t.tripID 
                    WHERE ja.standby_attendanceID = ? 
                    ORDER BY t.jobID ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $standby_attendanceID);
            $stmt->execute();
            $result = $stmt->get_result();
            $firstApprovedJobID = null;
            while ($row = $result->fetch_assoc()) {
                $candidateJobID = $row['jobID'];
                // Check approval for this candidate job
                $sql2 = "SELECT approval_status, approval_stage FROM approvals WHERE jobID = ? ORDER BY approvalID DESC LIMIT 1";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param('i', $candidateJobID);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $approval2 = $result2->fetch_assoc();
                if ($approval2 && $approval2['approval_status'] == 1 && $approval2['approval_stage'] === 'job_approval') {
                    $firstApprovedJobID = $candidateJobID;
                    break;
                }
            }
            if ($firstApprovedJobID && $firstApprovedJobID == $jobID) {
                $debug_log[] = "<span style='color:green'>standby_attendanceID $standby_attendanceID: processed for job $jobID (first APPROVED occurrence)</span>";
                // Only process if this job is the first APPROVED job for this standby_attendanceID
                // Standby employees
                $sql2 = "SELECT empID FROM standbyassignments WHERE standby_attendanceID = ?";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param('i', $standby_attendanceID);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                while ($row2 = $result2->fetch_assoc()) {
                    $standby_empIDs[] = $row2['empID'];
                }
                // Report preparation user (if not already set)
                if ($report_preparation_by === null) {
                    $sql3 = "SELECT report_preparation_by FROM jobreport_preparation WHERE standby_attendanceID = ? LIMIT 1";
                    $stmt3 = $conn->prepare($sql3);
                    $stmt3->bind_param('i', $standby_attendanceID);
                    $stmt3->execute();
                    $result3 = $stmt3->get_result();
                    if ($row3 = $result3->fetch_assoc()) {
                        $report_preparation_by = $row3['report_preparation_by'];
                    }
                }
            } else {
                $debug_log[] = "<span style='color:red'>standby_attendanceID $standby_attendanceID: skipped for job $jobID (already processed in approved job " . ($firstApprovedJobID ? $firstApprovedJobID : '-') . ")</span>";
            }
        }
        // Remove duplicate empIDs from standby_empIDs
        $standby_empIDs = array_unique($standby_empIDs);
        // 5. Get rates
        $rates = [];
        $sql = "SELECT rateID, rate FROM rates";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $rates[$row['rateID']] = $row['rate'];
        }
        
        // Get standby counts for all employees using the new function
        $standbyCountsData = getStandbyCounts($conn, $month, $year);
        $standbyCounts = $standbyCountsData['all'];
        // 6. Prepare employee data
        $employees = [];
        // Assigned employees: jobAllowance + jobMealAllowance (based on day count)
        foreach ($assigned as $empID => $dayCount) {
            // Get userID from employees
            $sql = "SELECT userID FROM employees WHERE empID = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $empID);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if (!$row) continue;
            $userID = $row['userID'];
            // Get user info
            $sql = "SELECT fname, lname, rateID FROM users WHERE userID = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $userID);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            if (!$user) continue;
            $fullName = $user['fname'] . ' ' . $user['lname'];
            $rateID = $user['rateID'];
            $jobAllowance = isset($rates[$rateID]) ? $rates[$rateID] * $dayCount : 0;
            $jobMealAllowance = isset($rates[3]) ? $rates[3] * $dayCount : 0;
            $employees[$userID] = [
                'name' => $fullName,
                'jobAllowance' => $jobAllowance,
                'jobMealAllowance' => $jobMealAllowance,
                'standbyAttendanceAllowance' => 0,
                'standbyMealAllowance' => 0,
                'reportPreparationAllowance' => 0
            ];
        }
        // Standby employees: standbyAttendanceAllowance + standbyMealAllowance (using calculated counts)
        foreach ($standby_empIDs as $empID) {
            $sql = "SELECT userID FROM employees WHERE empID = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $empID);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if (!$row) continue;
            $userID = $row['userID'];
            $sql = "SELECT fname, lname FROM users WHERE userID = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $userID);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            if (!$user) continue;
            $fullName = $user['fname'] . ' ' . $user['lname'];
            
            // Use the calculated standby count for this employee
            $standbyCount = $standbyCounts[$empID] ?? 0;
            $standbyAttendanceAllowance = isset($rates[1]) ? $rates[1] * $standbyCount : 0;
            $standbyMealAllowance = isset($rates[2]) ? $rates[2] * $standbyCount : 0;
            
            if (!isset($employees[$userID])) {
                $employees[$userID] = [
                    'name' => $fullName,
                    'jobAllowance' => 0,
                    'jobMealAllowance' => 0,
                    'standbyAttendanceAllowance' => $standbyAttendanceAllowance,
                    'standbyMealAllowance' => $standbyMealAllowance,
                    'reportPreparationAllowance' => 0
                ];
            } else {
                $employees[$userID]['standbyAttendanceAllowance'] = $standbyAttendanceAllowance;
                $employees[$userID]['standbyMealAllowance'] = $standbyMealAllowance;
            }
        }
        // Report preparation allowance
        if ($report_preparation_by) {
            $sql = "SELECT fname, lname FROM users WHERE userID = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $report_preparation_by);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            if ($user) {
                $fullName = $user['fname'] . ' ' . $user['lname'];
                if (!isset($employees[$report_preparation_by])) {
                    $employees[$report_preparation_by] = [
                        'name' => $fullName,
                        'jobAllowance' => 0,
                        'jobMealAllowance' => 0,
                        'standbyAttendanceAllowance' => 0,
                        'standbyMealAllowance' => 0,
                        'reportPreparationAllowance' => isset($rates[4]) ? $rates[4] : 0
                    ];
                } else {
                    $employees[$report_preparation_by]['reportPreparationAllowance'] = isset($rates[4]) ? $rates[4] : 0;
                }
            }
        }
        // Output debug log
        if (count($debug_log) > 0) {
            echo '<div class="mb-2"><strong>DEBUG LOG:</strong><br>' . implode('<br>', $debug_log) . '</div>';
        }
        // Output table
        if (count($employees) === 0) {
            echo '<div class="alert alert-info">No employees found for this job.</div>';
            exit();
        }
        echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
        echo '<thead><tr><th>Employee</th><th>Job Allowance</th><th>Job Meal Allowance</th><th>Standby Attendance Allowance</th><th>Standby Meal Allowance</th><th>Report Preparation Allowance</th><th>Total</th></tr></thead><tbody>';
        $grandTotal = 0;
        foreach ($employees as $emp) {
            $total = $emp['jobAllowance'] + $emp['jobMealAllowance'] + $emp['standbyAttendanceAllowance'] + $emp['standbyMealAllowance'] + $emp['reportPreparationAllowance'];
            $grandTotal += $total;
            echo '<tr>';
            echo '<td>' . htmlspecialchars($emp['name']) . '</td>';
            echo '<td>' . number_format($emp['jobAllowance'], 2) . '</td>';
            echo '<td>' . number_format($emp['jobMealAllowance'], 2) . '</td>';
            echo '<td>' . number_format($emp['standbyAttendanceAllowance'], 2) . '</td>';
            echo '<td>' . number_format($emp['standbyMealAllowance'], 2) . '</td>';
            echo '<td>' . number_format($emp['reportPreparationAllowance'], 2) . '</td>';
            echo '<td class="fw-bold">' . number_format($total, 2) . '</td>';
            echo '</tr>';
        }
        // Add total row
        echo '<tr class="table-info fw-bold"><td colspan="6" class="text-end">Total Allowance for Job</td><td>' . number_format($grandTotal, 2) . '</td></tr>';
        echo '</tbody></table></div>';
        exit();
    }
    if ($action === 'getMonthSummary') {
        $month = $_POST['month'];
        $year = $_POST['year'];
        
        // Get all approved jobs for the month/year
        $sql = "SELECT j.jobID, j.comment FROM jobs j INNER JOIN approvals a ON j.jobID = a.jobID 
                WHERE YEAR(j.start_date) = ? AND MONTH(j.start_date) = ? 
                AND a.approval_status = 1 AND a.approval_stage = 'job_approval'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $jobs = [];
        while ($row = $result->fetch_assoc()) {
            $jobs[$row['jobID']] = $row;
        }
        
        if (empty($jobs)) {
            echo '<div class="alert alert-info">No approved jobs found for this month/year.</div>';
            exit();
        }
        
        // Get rates
        $rates = [];
        $rateRes = $conn->query("SELECT rateID, rate FROM rates");
        while ($r = $rateRes->fetch_assoc()) {
            $rates[$r['rateID']] = $r['rate'];
        }
        
        // Get standby counts for all employees using the new function
        $standbyCountsData = getStandbyCounts($conn, $month, $year);
        $standbyCounts = $standbyCountsData['all'];
        
        // Initialize totals
        $totals = [
            'jobAllowance' => 0,
            'jobMealAllowance' => 0,
            'standbyAttendance' => 0,
            'standbyMeal' => 0,
            'reportPreparation' => 0,
            'grandTotal' => 0
        ];
        
        // Prepare table with the exact structure from the screenshot
        echo '<div class="table-responsive"><table class="table table-bordered table-striped" style="white-space: nowrap;">';
        echo '<thead><tr>';
        echo '<th>JOBID</th>';
        echo '<th>EMPLOYEE ASSIGNED</th>';
        echo '<th>JOB ALLOWANCE</th>';
        echo '<th>JOB MEAL ALLOWANCE</th>';
        echo '<th>STANDBY ATTENDANCE ALLOWANCE</th>';
        echo '<th>STANDBY MEAL ALLOWANCE</th>';
        echo '<th>REPORT PREPARATION ALLOWANCE</th>';
        echo '<th>TOTAL DIVING ALLOWANCE</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($jobs as $jobID => $job) {
            // Get all employees (assigned + standby + report prep) and combine their allowances
            $employeeAllowances = [];
            
            // Get assigned employees with day counts
            $sql = "SELECT e.empID, u.userID, u.fname, u.lname, u.rateID, COUNT(*) as day_count
                    FROM jobassignments ja 
                    INNER JOIN trips t ON ja.tripID = t.tripID
                    INNER JOIN employees e ON ja.empID = e.empID 
                    INNER JOIN users u ON e.userID = u.userID 
                    WHERE t.jobID = ?
                    GROUP BY e.empID, u.userID, u.fname, u.lname, u.rateID";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $jobID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $userID = $row['userID'];
                $dayCount = $row['day_count'];
                if (!isset($employeeAllowances[$userID])) {
                    $employeeAllowances[$userID] = [
                        'name' => $row['fname'] . ' ' . $row['lname'],
                        'jobAllowance' => isset($rates[$row['rateID']]) ? $rates[$row['rateID']] * $dayCount : 0,
                        'jobMealAllowance' => isset($rates[3]) ? $rates[3] * $dayCount : 0,
                        'standbyAttendance' => 0,
                        'standbyMeal' => 0,
                        'reportPreparation' => 0
                    ];
                } else {
                    $employeeAllowances[$userID]['jobAllowance'] = isset($rates[$row['rateID']]) ? $rates[$row['rateID']] * $dayCount : 0;
                    $employeeAllowances[$userID]['jobMealAllowance'] = isset($rates[3]) ? $rates[3] * $dayCount : 0;
                }
            }
            
            // Get standby employees
            $standby_attendanceIDs = [];
            $sql = "SELECT DISTINCT ja.standby_attendanceID 
                    FROM job_attendance ja 
                    JOIN trips t ON ja.tripID = t.tripID 
                    WHERE t.jobID = ? AND ja.standby_attendanceID IS NOT NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $jobID);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if ($row['standby_attendanceID']) {
                    $standby_attendanceIDs[] = $row['standby_attendanceID'];
                }
            }
            
            foreach ($standby_attendanceIDs as $standby_attendanceID) {
                // Only process if this job is the first APPROVED job for this standby_attendanceID
                $sql = "SELECT t.jobID FROM job_attendance ja 
                        JOIN trips t ON ja.tripID = t.tripID 
                        WHERE ja.standby_attendanceID = ? 
                        ORDER BY t.jobID ASC";
                $stmt2 = $conn->prepare($sql);
                $stmt2->bind_param('i', $standby_attendanceID);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $firstApprovedJobID = null;
                while ($row2 = $result2->fetch_assoc()) {
                    $candidateJobID = $row2['jobID'];
                    $sql3 = "SELECT approval_status, approval_stage FROM approvals WHERE jobID = ? ORDER BY approvalID DESC LIMIT 1";
                    $stmt3 = $conn->prepare($sql3);
                    $stmt3->bind_param('i', $candidateJobID);
                    $stmt3->execute();
                    $result3 = $stmt3->get_result();
                    $approval3 = $result3->fetch_assoc();
                    if ($approval3 && $approval3['approval_status'] == 1 && $approval3['approval_stage'] === 'job_approval') {
                        $firstApprovedJobID = $candidateJobID;
                        break;
                    }
                }
                
                if ($firstApprovedJobID && $firstApprovedJobID == $jobID) {
                    $sql4 = "SELECT e.empID, u.userID, u.fname, u.lname 
                            FROM standbyassignments sa 
                            INNER JOIN employees e ON sa.empID = e.empID 
                            INNER JOIN users u ON e.userID = u.userID 
                            WHERE sa.standby_attendanceID = ?";
                    $stmt4 = $conn->prepare($sql4);
                    $stmt4->bind_param('i', $standby_attendanceID);
                    $stmt4->execute();
                    $result4 = $stmt4->get_result();
                    while ($row4 = $result4->fetch_assoc()) {
                        $userID = $row4['userID'];
                        $empID = $row4['empID'];
                        
                        // Use the pre-calculated standby count for this employee
                        $standbyCount = $standbyCounts[$empID] ?? 0;
                        
                        if (!isset($employeeAllowances[$userID])) {
                            $employeeAllowances[$userID] = [
                                'name' => $row4['fname'] . ' ' . $row4['lname'],
                                'jobAllowance' => 0,
                                'jobMealAllowance' => 0,
                                'standbyAttendance' => isset($rates[1]) ? $rates[1] * $standbyCount : 0,
                                'standbyMeal' => isset($rates[2]) ? $rates[2] * $standbyCount : 0,
                                'reportPreparation' => 0
                            ];
                        } else {
                            $employeeAllowances[$userID]['standbyAttendance'] = isset($rates[1]) ? $rates[1] * $standbyCount : 0;
                            $employeeAllowances[$userID]['standbyMeal'] = isset($rates[2]) ? $rates[2] * $standbyCount : 0;
                        }
                    }
                }
            }
            
            // Get report preparation user
            foreach ($standby_attendanceIDs as $standby_attendanceID) {
                $sql = "SELECT report_preparation_by FROM jobreport_preparation WHERE standby_attendanceID = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $standby_attendanceID);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $userID = $row['report_preparation_by'];
                    $sql = "SELECT fname, lname FROM users WHERE userID = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('i', $userID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($user = $result->fetch_assoc()) {
                        if (!isset($employeeAllowances[$userID])) {
                            $employeeAllowances[$userID] = [
                                'name' => $user['fname'] . ' ' . $user['lname'],
                                'jobAllowance' => 0,
                                'jobMealAllowance' => 0,
                                'standbyAttendance' => 0,
                                'standbyMeal' => 0,
                                'reportPreparation' => isset($rates[4]) ? $rates[4] : 0
                            ];
                        } else {
                            $employeeAllowances[$userID]['reportPreparation'] = isset($rates[4]) ? $rates[4] : 0;
                        }
                    }
                }
            }
            
            // Display job ID row
            echo '<tr>';
            echo '<td rowspan="'.(count($employeeAllowances) + 1).'">' . htmlspecialchars($jobID) . '</td>';
            echo '</tr>';
            
            // Display each employee with their combined allowances
            foreach ($employeeAllowances as $employee) {
                $total = $employee['jobAllowance'] + $employee['jobMealAllowance'] + 
                         $employee['standbyAttendance'] + $employee['standbyMeal'] + 
                         $employee['reportPreparation'];
                
                // Update totals
                $totals['jobAllowance'] += $employee['jobAllowance'];
                $totals['jobMealAllowance'] += $employee['jobMealAllowance'];
                $totals['standbyAttendance'] += $employee['standbyAttendance'];
                $totals['standbyMeal'] += $employee['standbyMeal'];
                $totals['reportPreparation'] += $employee['reportPreparation'];
                $totals['grandTotal'] += $total;
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($employee['name']) . '</td>';
                echo '<td class="text-end">' . ($employee['jobAllowance'] > 0 ? number_format($employee['jobAllowance'], 2) : 'N/A') . '</td>';
                echo '<td class="text-end">' . ($employee['jobMealAllowance'] > 0 ? number_format($employee['jobMealAllowance'], 2) : 'N/A') . '</td>';
                echo '<td class="text-end">' . ($employee['standbyAttendance'] > 0 ? number_format($employee['standbyAttendance'], 2) : 'N/A') . '</td>';
                echo '<td class="text-end">' . ($employee['standbyMeal'] > 0 ? number_format($employee['standbyMeal'], 2) : 'N/A') . '</td>';
                echo '<td class="text-end">' . ($employee['reportPreparation'] > 0 ? number_format($employee['reportPreparation'], 2) : 'N/A') . '</td>';
                echo '<td class="text-end">' . number_format($total, 2) . '</td>';
                echo '</tr>';
            }
            
            // Add empty row for spacing (like in the screenshot)
            echo '<tr><td colspan="8" style="height: 10px;"></td></tr>';
        }
        
        // Add total row at the bottom
        echo '<tr class="table-info fw-bold">';
        echo '<td colspan="2">TOTAL:</td>';
        echo '<td class="text-end">' . number_format($totals['jobAllowance'], 2) . '</td>';
        echo '<td class="text-end">' . number_format($totals['jobMealAllowance'], 2) . '</td>';
        echo '<td class="text-end">' . number_format($totals['standbyAttendance'], 2) . '</td>';
        echo '<td class="text-end">' . number_format($totals['standbyMeal'], 2) . '</td>';
        echo '<td class="text-end">' . number_format($totals['reportPreparation'], 2) . '</td>';
        echo '<td class="text-end">' . number_format($totals['grandTotal'], 2) . '</td>';
        echo '</tr>';
        
        echo '</tbody></table></div>';
        exit();
    }
    if ($action === 'getDrivers') {
        // Get drivers for selected month/year that have been assigned to jobs
        $month = $_POST['month'];
        $year = $_POST['year'];
        $drivers = [];
        $sql = "SELECT DISTINCT u.userID, u.fname, u.lname 
                FROM users u 
                INNER JOIN employees e ON u.userID = e.userID 
                INNER JOIN jobassignments ja ON e.empID = ja.empID 
                INNER JOIN trips t ON ja.tripID = t.tripID 
                INNER JOIN jobs j ON t.jobID = j.jobID 
                INNER JOIN approvals a ON j.jobID = a.jobID 
                WHERE YEAR(j.start_date) = ? AND MONTH(j.start_date) = ?
                  AND a.approval_status = 1 AND a.approval_stage = 'job_approval'
                ORDER BY u.fname, u.lname";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $drivers[] = $row;
        }
        if (count($drivers) > 0) {
            echo '<option value="">Select Driver</option>';
            foreach ($drivers as $driver) {
                $label = htmlspecialchars($driver['fname'] . ' ' . $driver['lname']);
                echo '<option value="' . $driver['userID'] . '">' . $label . '</option>';
            }
        } else {
            echo '<option value="">No drivers found</option>';
        }
        exit();
    }
    if ($action === 'getDriverBreakdown') {
        $driverID = $_POST['driverID'];
        $month = $_POST['driverMonth'];
        $year = $_POST['driverYear'];
        
        // Get driver info
        $sql = "SELECT fname, lname, rateID FROM users WHERE userID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $driverID);
        $stmt->execute();
        $result = $stmt->get_result();
        $driver = $result->fetch_assoc();
        
        if (!$driver) {
            echo '<div class="alert alert-warning">Driver not found.</div>';
            exit();
        }
        
        // Get rates
        $rates = [];
        $rateRes = $conn->query("SELECT rateID, rate FROM rates");
        while ($r = $rateRes->fetch_assoc()) {
            $rates[$r['rateID']] = $r['rate'];
        }
        
        // Get all jobs for this driver in the selected month/year
        $sql = "SELECT DISTINCT j.jobID, j.start_date, j.comment, 
                       COUNT(DISTINCT ja.tripID) as trip_count,
                       COUNT(ja.empID) as day_count
                FROM jobs j 
                INNER JOIN approvals a ON j.jobID = a.jobID 
                INNER JOIN trips t ON j.jobID = t.jobID 
                INNER JOIN jobassignments ja ON t.tripID = ja.tripID 
                INNER JOIN employees e ON ja.empID = e.empID 
                WHERE e.userID = ? 
                  AND YEAR(j.start_date) = ? AND MONTH(j.start_date) = ?
                  AND a.approval_status = 1 AND a.approval_stage = 'job_approval'
                GROUP BY j.jobID, j.start_date, j.comment
                ORDER BY j.start_date";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iis', $driverID, $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $jobs = [];
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        
        // Get standby assignments for this driver
        $sql = "SELECT DISTINCT sa.standby_attendanceID, ja.tripID, j.jobID, j.start_date, j.comment
                FROM standbyassignments sa 
                INNER JOIN employees e ON sa.empID = e.empID 
                INNER JOIN job_attendance ja ON sa.standby_attendanceID = ja.standby_attendanceID 
                INNER JOIN trips t ON ja.tripID = t.tripID 
                INNER JOIN jobs j ON t.jobID = j.jobID 
                INNER JOIN approvals a ON j.jobID = a.jobID 
                WHERE e.userID = ? 
                  AND YEAR(j.start_date) = ? AND MONTH(j.start_date) = ?
                  AND a.approval_status = 1 AND a.approval_stage = 'job_approval'
                ORDER BY j.start_date";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iis', $driverID, $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $standbyJobs = [];
        while ($row = $result->fetch_assoc()) {
            $standbyJobs[] = $row;
        }
        
        // Get report preparation assignments for this driver
        $sql = "SELECT DISTINCT jrp.standby_attendanceID, ja.tripID, j.jobID, j.start_date, j.comment
                FROM jobreport_preparation jrp 
                INNER JOIN job_attendance ja ON jrp.standby_attendanceID = ja.standby_attendanceID 
                INNER JOIN trips t ON ja.tripID = t.tripID 
                INNER JOIN jobs j ON t.jobID = j.jobID 
                INNER JOIN approvals a ON j.jobID = a.jobID 
                WHERE jrp.report_preparation_by = ? 
                  AND YEAR(j.start_date) = ? AND MONTH(j.start_date) = ?
                  AND a.approval_status = 1 AND a.approval_stage = 'job_approval'
                ORDER BY j.start_date";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iis', $driverID, $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $reportJobs = [];
        while ($row = $result->fetch_assoc()) {
            $reportJobs[] = $row;
        }
        
        if (empty($jobs) && empty($standbyJobs) && empty($reportJobs)) {
            echo '<div class="alert alert-info">No jobs found for this driver in the selected month/year.</div>';
            exit();
        }
        
        // Output table
        echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
        echo '<thead><tr>';
        echo '<th>Job ID</th>';
        echo '<th>Date</th>';
        echo '<th>Job Description</th>';
        echo '<th>Role</th>';
        echo '<th>Days/Trips</th>';
        echo '<th>Job Allowance</th>';
        echo '<th>Job Meal Allowance</th>';
        echo '<th>Standby Attendance Allowance</th>';
        echo '<th>Standby Meal Allowance</th>';
        echo '<th>Report Preparation Allowance</th>';
        echo '<th>Total</th>';
        echo '</tr></thead><tbody>';
        
        $grandTotal = 0;
        $driverName = htmlspecialchars($driver['fname'] . ' ' . $driver['lname']);
        $driverRate = isset($rates[$driver['rateID']]) ? $rates[$driver['rateID']] : 0;
        
        // Process regular job assignments
        foreach ($jobs as $job) {
            $jobAllowance = $driverRate * $job['day_count'];
            $jobMealAllowance = isset($rates[3]) ? $rates[3] * $job['day_count'] : 0;
            $total = $jobAllowance + $jobMealAllowance;
            $grandTotal += $total;
            
            echo '<tr>';
            echo '<td>' . $job['jobID'] . '</td>';
            echo '<td>' . date('Y-m-d', strtotime($job['start_date'])) . '</td>';
            echo '<td>' . htmlspecialchars($job['comment']) . '</td>';
            echo '<td>Assigned Driver</td>';
            echo '<td>' . $job['day_count'] . ' days</td>';
            echo '<td>' . number_format($jobAllowance, 2) . '</td>';
            echo '<td>' . number_format($jobMealAllowance, 2) . '</td>';
            echo '<td>0.00</td>';
            echo '<td>0.00</td>';
            echo '<td>0.00</td>';
            echo '<td class="fw-bold">' . number_format($total, 2) . '</td>';
            echo '</tr>';
        }
        
        // Get standby counts for this driver using the new function
        $standbyCountsData = getStandbyCounts($conn, $month, $year);
        $standbyCounts = $standbyCountsData['all'];
        
        // Get employee ID for this driver
        $sql = "SELECT empID FROM employees WHERE userID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $driverID);
        $stmt->execute();
        $result = $stmt->get_result();
        $empRow = $result->fetch_assoc();
        $empID = $empRow ? $empRow['empID'] : null;
        
        // Process standby assignments
        if ($empID && isset($standbyCounts[$empID]) && $standbyCounts[$empID] > 0) {
            $standbyCount = $standbyCounts[$empID];
            $standbyAttendanceAllowance = isset($rates[1]) ? $rates[1] * $standbyCount : 0;
            $standbyMealAllowance = isset($rates[2]) ? $rates[2] * $standbyCount : 0;
            $total = $standbyAttendanceAllowance + $standbyMealAllowance;
            $grandTotal += $total;
            
            echo '<tr>';
            echo '<td>Standby Assignments</td>';
            echo '<td>' . getMonthName($month) . ' ' . $year . '</td>';
            echo '<td>Standby Duty</td>';
            echo '<td>Standby Driver</td>';
            echo '<td>' . $standbyCount . ' days</td>';
            echo '<td>0.00</td>';
            echo '<td>0.00</td>';
            echo '<td>' . number_format($standbyAttendanceAllowance, 2) . '</td>';
            echo '<td>' . number_format($standbyMealAllowance, 2) . '</td>';
            echo '<td>0.00</td>';
            echo '<td class="fw-bold">' . number_format($total, 2) . '</td>';
            echo '</tr>';
        }
        
        // Process report preparation assignments
        foreach ($reportJobs as $job) {
            $reportPreparationAllowance = isset($rates[4]) ? $rates[4] : 0;
            $total = $reportPreparationAllowance;
            $grandTotal += $total;
            
            echo '<tr>';
            echo '<td>' . $job['jobID'] . '</td>';
            echo '<td>' . date('Y-m-d', strtotime($job['start_date'])) . '</td>';
            echo '<td>' . htmlspecialchars($job['comment']) . '</td>';
            echo '<td>Report Preparation</td>';
            echo '<td>1 trip</td>';
            echo '<td>0.00</td>';
            echo '<td>0.00</td>';
            echo '<td>0.00</td>';
            echo '<td>0.00</td>';
            echo '<td>' . number_format($reportPreparationAllowance, 2) . '</td>';
            echo '<td class="fw-bold">' . number_format($total, 2) . '</td>';
            echo '</tr>';
        }
        
        // Add total row
        echo '<tr class="table-info fw-bold"><td colspan="10" class="text-end">Total Allowance for ' . $driverName . '</td><td>' . number_format($grandTotal, 2) . '</td></tr>';
        echo '</tbody></table></div>';
        exit();
    }
    if ($action === 'getJobkeys') {
    // Get jobkeys for selected month/year that are approved
    $month = $_POST['month'];
    $year = $_POST['year'];
    $jobkeys = [];
    $sql = "SELECT j.jobID, j.jobkey, j.start_date, j.comment FROM jobs j
            INNER JOIN approvals a ON j.jobID = a.jobID
            WHERE YEAR(j.start_date) = ? AND MONTH(j.start_date) = ?
              AND a.approval_status = 1 AND a.approval_stage = 'job_approval'
            ORDER BY j.jobkey";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobkeys[] = $row;
    }
    if (count($jobkeys) > 0) {
        echo '<option value="">Select Jobkey</option>';
        foreach ($jobkeys as $job) {
            $label = htmlspecialchars($job['jobkey']) . ' - ' . date('Y-m-d', strtotime($job['start_date'])) . ' - ' . htmlspecialchars($job['comment']);
            echo '<option value="' . htmlspecialchars($job['jobkey']) . '">' . $label . '</option>';
        }
    } else {
        echo '<option value="">No jobkeys found</option>';
    }
    exit();
}if ($action === 'getJobkeyBreakdown') {
    $jobkey = $_POST['jobkey'];
    $month = $_POST['jobkeyMonth'];
    $year = $_POST['jobkeyYear'];
    
    // Get job ID from jobkey
    $sql = "SELECT jobID FROM jobs WHERE jobkey = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $jobkey);
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->fetch_assoc();
    
    if (!$job) {
        echo '<div class="alert alert-warning">Job not found for this jobkey.</div>';
        exit();
    }
    
    $jobID = $job['jobID'];
    
    // 1. Check approval
    $sql = "SELECT approval_status, approval_stage FROM approvals WHERE jobID = ? ORDER BY approvalID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $jobID);
    $stmt->execute();
    $result = $stmt->get_result();
    $approval = $result->fetch_assoc();
    if (!$approval || $approval['approval_status'] != 1 || $approval['approval_stage'] !== 'job_approval') {
        echo '<div class="alert alert-warning">This job is not approved or not at the correct approval stage.</div>';
        exit();
    }
    
    // 2. Get job details
    $sql = "SELECT jobkey, start_date, end_date FROM jobs WHERE jobID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $jobID);
    $stmt->execute();
    $result = $stmt->get_result();
    $jobDetails = $result->fetch_assoc();
    
    // 3. Get assigned employees with day counts (jobassignments) - Updated for trips structure
    $assigned = [];
    $sql = "SELECT ja.empID, COUNT(*) as day_count 
            FROM jobassignments ja 
            JOIN trips t ON ja.tripID = t.tripID 
            WHERE t.jobID = ?
            GROUP BY ja.empID";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $jobID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assigned[$row['empID']] = $row['day_count'];
    }
    
    // 4. Get all unique standby_attendanceIDs for this job - Updated for trips structure
    $standby_attendanceIDs = [];
    $sql = "SELECT DISTINCT ja.standby_attendanceID 
            FROM job_attendance ja 
            JOIN trips t ON ja.tripID = t.tripID 
            WHERE t.jobID = ? AND ja.standby_attendanceID IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $jobID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['standby_attendanceID']) {
            $standby_attendanceIDs[] = $row['standby_attendanceID'];
        }
    }
    
    // 5. For each unique standby_attendanceID, only process if this job is the first APPROVED job for that standby_attendanceID
    $standby_empIDs = [];
    $report_preparation_by = null;
    $debug_log = [];
    foreach ($standby_attendanceIDs as $standby_attendanceID) {
        // Find all jobs that use this standby_attendanceID, ordered by jobID
        $sql = "SELECT t.jobID FROM job_attendance ja 
                JOIN trips t ON ja.tripID = t.tripID 
                WHERE ja.standby_attendanceID = ? 
                ORDER BY t.jobID ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $standby_attendanceID);
        $stmt->execute();
        $result = $stmt->get_result();
        $firstApprovedJobID = null;
        while ($row = $result->fetch_assoc()) {
            $candidateJobID = $row['jobID'];
            // Check approval for this candidate job
            $sql2 = "SELECT approval_status, approval_stage FROM approvals WHERE jobID = ? ORDER BY approvalID DESC LIMIT 1";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param('i', $candidateJobID);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $approval2 = $result2->fetch_assoc();
            if ($approval2 && $approval2['approval_status'] == 1 && $approval2['approval_stage'] === 'job_approval') {
                $firstApprovedJobID = $candidateJobID;
                break;
            }
        }
        if ($firstApprovedJobID && $firstApprovedJobID == $jobID) {
            $debug_log[] = "<span style='color:green'>standby_attendanceID $standby_attendanceID: processed for job $jobID (first APPROVED occurrence)</span>";
            // Only process if this job is the first APPROVED job for this standby_attendanceID
            // Standby employees
            $sql2 = "SELECT empID FROM standbyassignments WHERE standby_attendanceID = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param('i', $standby_attendanceID);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            while ($row2 = $result2->fetch_assoc()) {
                $standby_empIDs[] = $row2['empID'];
            }
            // Report preparation user (if not already set)
            if ($report_preparation_by === null) {
                $sql3 = "SELECT report_preparation_by FROM jobreport_preparation WHERE standby_attendanceID = ? LIMIT 1";
                $stmt3 = $conn->prepare($sql3);
                $stmt3->bind_param('i', $standby_attendanceID);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                if ($row3 = $result3->fetch_assoc()) {
                    $report_preparation_by = $row3['report_preparation_by'];
                }
            }
        } else {
            $debug_log[] = "<span style='color:red'>standby_attendanceID $standby_attendanceID: skipped for job $jobID (already processed in approved job " . ($firstApprovedJobID ? $firstApprovedJobID : '-') . ")</span>";
        }
    }
    
    // Remove duplicate empIDs from standby_empIDs
    $standby_empIDs = array_unique($standby_empIDs);
    
    // 6. Get rates
    $rates = [];
    $sql = "SELECT rateID, rate FROM rates";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $rates[$row['rateID']] = $row['rate'];
    }
    
    // Get standby counts for all employees using the new function
    $standbyCountsData = getStandbyCounts($conn, $month, $year);
    $standbyCounts = $standbyCountsData['all'];
    
    // 7. Prepare employee data
    $employees = [];
    // Assigned employees: jobAllowance + jobMealAllowance (based on day count)
    foreach ($assigned as $empID => $dayCount) {
        // Get userID from employees
        $sql = "SELECT userID FROM employees WHERE empID = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $empID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if (!$row) continue;
        $userID = $row['userID'];
        // Get user info
        $sql = "SELECT fname, lname, rateID FROM users WHERE userID = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (!$user) continue;
        $fullName = $user['fname'] . ' ' . $user['lname'];
        $rateID = $user['rateID'];
        $jobAllowance = isset($rates[$rateID]) ? $rates[$rateID] * $dayCount : 0;
        $jobMealAllowance = isset($rates[3]) ? $rates[3] * $dayCount : 0;
        $employees[$userID] = [
            'name' => $fullName,
            'jobAllowance' => $jobAllowance,
            'jobMealAllowance' => $jobMealAllowance,
            'standbyAttendanceAllowance' => 0,
            'standbyMealAllowance' => 0,
            'reportPreparationAllowance' => 0
        ];
    }
    
    // Standby employees: standbyAttendanceAllowance + standbyMealAllowance (using calculated counts)
    foreach ($standby_empIDs as $empID) {
        $sql = "SELECT userID FROM employees WHERE empID = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $empID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if (!$row) continue;
        $userID = $row['userID'];
        $sql = "SELECT fname, lname FROM users WHERE userID = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (!$user) continue;
        $fullName = $user['fname'] . ' ' . $user['lname'];
        
        // Use the calculated standby count for this employee
        $standbyCount = $standbyCounts[$empID] ?? 0;
        $standbyAttendanceAllowance = isset($rates[1]) ? $rates[1] * $standbyCount : 0;
        $standbyMealAllowance = isset($rates[2]) ? $rates[2] * $standbyCount : 0;
        
        if (!isset($employees[$userID])) {
            $employees[$userID] = [
                'name' => $fullName,
                'jobAllowance' => 0,
                'jobMealAllowance' => 0,
                'standbyAttendanceAllowance' => $standbyAttendanceAllowance,
                'standbyMealAllowance' => $standbyMealAllowance,
                'reportPreparationAllowance' => 0
            ];
        } else {
            $employees[$userID]['standbyAttendanceAllowance'] = $standbyAttendanceAllowance;
            $employees[$userID]['standbyMealAllowance'] = $standbyMealAllowance;
        }
    }
    
    // Report preparation allowance
    if ($report_preparation_by) {
        $sql = "SELECT fname, lname FROM users WHERE userID = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $report_preparation_by);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user) {
            $fullName = $user['fname'] . ' ' . $user['lname'];
            if (!isset($employees[$report_preparation_by])) {
                $employees[$report_preparation_by] = [
                    'name' => $fullName,
                    'jobAllowance' => 0,
                    'jobMealAllowance' => 0,
                    'standbyAttendanceAllowance' => 0,
                    'standbyMealAllowance' => 0,
                    'reportPreparationAllowance' => isset($rates[4]) ? $rates[4] : 0
                ];
            } else {
                $employees[$report_preparation_by]['reportPreparationAllowance'] = isset($rates[4]) ? $rates[4] : 0;
            }
        }
    }
    
    // Output debug log
    if (count($debug_log) > 0) {
        echo '<div class="mb-2"><strong>DEBUG LOG:</strong><br>' . implode('<br>', $debug_log) . '</div>';
    }
    
    // Output job details
    echo '<div class="card mb-3">';
    echo '<div class="card-header"><h5>Job Details</h5></div>';
    echo '<div class="card-body">';
    echo '<div class="row">';
    echo '<div class="col-md-4"><strong>Jobkey:</strong> ' . htmlspecialchars($jobDetails['jobkey']) . '</div>';
    echo '<div class="col-md-4"><strong>Start Date:</strong> ' . date('Y-m-d', strtotime($jobDetails['start_date'])) . '</div>';
    echo '<div class="col-md-4"><strong>End Date:</strong> ' . ($jobDetails['end_date'] ? date('Y-m-d', strtotime($jobDetails['end_date'])) : 'N/A') . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Output table
    if (count($employees) === 0) {
        echo '<div class="alert alert-info">No employees found for this job.</div>';
        exit();
    }
    
    echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
    echo '<thead><tr><th>Employee</th><th>Job Allowance</th><th>Job Meal Allowance</th><th>Standby Attendance Allowance</th><th>Standby Meal Allowance</th><th>Report Preparation Allowance</th><th>Total</th></tr></thead><tbody>';
    $grandTotal = 0;
    foreach ($employees as $emp) {
        $total = $emp['jobAllowance'] + $emp['jobMealAllowance'] + $emp['standbyAttendanceAllowance'] + $emp['standbyMealAllowance'] + $emp['reportPreparationAllowance'];
        $grandTotal += $total;
        echo '<tr>';
        echo '<td>' . htmlspecialchars($emp['name']) . '</td>';
        echo '<td>' . number_format($emp['jobAllowance'], 2) . '</td>';
        echo '<td>' . number_format($emp['jobMealAllowance'], 2) . '</td>';
        echo '<td>' . number_format($emp['standbyAttendanceAllowance'], 2) . '</td>';
        echo '<td>' . number_format($emp['standbyMealAllowance'], 2) . '</td>';
        echo '<td>' . number_format($emp['reportPreparationAllowance'], 2) . '</td>';
        echo '<td class="fw-bold">' . number_format($total, 2) . '</td>';
        echo '</tr>';
    }
    // Add total row
    echo '<tr class="table-info fw-bold"><td colspan="6" class="text-end">Total Allowance for Job</td><td>' . number_format($grandTotal, 2) . '</td></tr>';
    echo '</tbody></table></div>';
    exit();
}
}
echo '<div class="alert alert-danger">Invalid request.</div>';
