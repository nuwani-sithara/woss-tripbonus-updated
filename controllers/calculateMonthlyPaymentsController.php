<?php
// Enable strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Start output buffering
ob_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

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

function getJobCounts($conn, $month, $year) {
    $jobCounts = [];
    
    // Get approved jobs for the month
    $sql = "SELECT j.jobID
            FROM approvals a
            JOIN jobs j ON a.jobID = j.jobID
            WHERE a.approval_status = 1 AND a.approval_stage = 'job_approval'
              AND MONTH(j.start_date) = ? AND YEAR(j.start_date) = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("si", $month, $year);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    
    // Get all approved job IDs
    $approvedJobIDs = [];
    while ($row = $result->fetch_assoc()) {
        $approvedJobIDs[] = $row['jobID'];
    }
    $stmt->close();
    
    if (empty($approvedJobIDs)) {
        return $jobCounts;
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($approvedJobIDs) - 1) . '?';
    
    // Get trips associated with approved jobs and count employee assignments
    $sql = "SELECT ja.empID, COUNT(*) as day_count
            FROM trips t
            JOIN jobassignments ja ON t.tripID = ja.tripID
            WHERE t.jobID IN ($placeholders)
            GROUP BY ja.empID";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for trips query: " . $conn->error);
    }
    
    // Bind all job IDs
    $types = str_repeat('i', count($approvedJobIDs));
    $stmt->bind_param($types, ...$approvedJobIDs);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for trips query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobCounts[$row['empID']] = $row['day_count'];
    }
    $stmt->close();
    
    return $jobCounts;
}

function getEmployeeRates($conn) {
    $empRates = [];
    $sql = "SELECT e.empID, u.rateID, r.rate, 
            CONCAT(IFNULL(u.fname,''), ' ', IFNULL(u.lname,'')) AS empName
            FROM employees e
            JOIN users u ON e.userID = u.userID
            JOIN rates r ON u.rateID = r.rateID";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        $empRates[$row['empID']] = [
            'rateID' => $row['rateID'],
            'rate' => $row['rate'],
            'empName' => trim($row['empName']) ?: 'Unknown Employee'
        ];
    }
    return $empRates;
}

function getRates($conn) {
    $rates = [];
    $result = $conn->query("SELECT rateID, rate FROM rates WHERE rateID IN (1, 2, 3, 4)");
    while ($row = $result->fetch_assoc()) {
        $rates[$row['rateID']] = $row['rate'];
    }
    return $rates;
}

function getReportPreparationCounts($conn, $month, $year) {
    $reportCounts = [];
    $sql = "SELECT jrp.report_preparation_by as empID, COUNT(*) as report_count
            FROM jobreport_preparation jrp
            JOIN standby_attendance s ON jrp.standby_attendanceID = s.standby_attendanceID
            WHERE MONTH(s.date) = ? AND YEAR(s.date) = ?
            GROUP BY jrp.report_preparation_by";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reportCounts[$row['empID']] = $row['report_count'];
    }
    $stmt->close();
    return $reportCounts;
}

try {
    // Log the start of the process
    error_log("Starting payment calculation process");
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method. Only POST requests are allowed.");
    }

    // Validate input parameters
    $month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 12]
    ]);
    
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 2020, 'max_range' => date('Y')]
    ]);
    
    if (!$month || !$year) {
        throw new Exception("Invalid month or year parameters. Month must be 1-12 and year must be 2020-current year.");
    }

    // Database connection
    require_once '../config/dbConnect.php';
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // --- NEW: Check if already published for this month/year ---
    session_start();
    if (!isset($_SESSION['userID'])) {
        throw new Exception("User not logged in.");
    }
    $userID = $_SESSION['userID'];
    $monthName = date('F', mktime(0, 0, 0, $month, 10));
    $checkSql = "SELECT publishedID FROM published WHERE month = ? AND year = ?";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $checkStmt->bind_param("si", $monthName, $year);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $response['message'] = "Payments for $monthName $year have already been published.";
        echo json_encode($response);
        exit;
    }
    $checkStmt->close();
    // --- END NEW ---

    // 1. Get approved jobs and assigned employees
    $jobCounts = getJobCounts($conn, $month, $year);

    // 2. Get employee rates with proper name fields
    $empRates = getEmployeeRates($conn);

    // 3. Get all rates
    $rates = getRates($conn);

    // 4. Get standby attendance count per employee
    $standbyCountsData = getStandbyCounts($conn, $month, $year);
    $standbyCounts = $standbyCountsData['all']; // Use 'all' for payroll as before

    // 5. Get report preparation count per employee
    $reportCounts = getReportPreparationCounts($conn, $month, $year);

    // 6. Calculate payments for each employee
    $results = [];
    $allEmpIDs = array_unique(array_merge(
        array_keys($jobCounts),
        array_keys($standbyCounts),
        array_keys($reportCounts)
    ));

    if (empty($allEmpIDs)) {
        $response['message'] = "No records found for the selected month/year";
        echo json_encode($response);
        exit;
    }

    foreach ($allEmpIDs as $empID) {
        $empData = $empRates[$empID] ?? null;
        if (!$empData) {
            error_log("Employee data not found for ID: $empID");
            continue;
        }

        $jobCount = $jobCounts[$empID] ?? 0;
        $jobAllowance = $jobCount * ($empData['rate'] ?? 0);
        $jobMealAllowance = $jobCount * ($rates[3] ?? 0); // Rate ID 3 = job meal

        // Use total standby count for both allowances
        $standbyCount = $standbyCounts[$empID] ?? 0;
        $standbyAttendanceAllowance = $standbyCount * ($rates[1] ?? 0); // Rate ID 1 = standby
        $standbyMealAllowance = $standbyCount * ($rates[2] ?? 0); // Rate ID 2 = standby meal

        $reportCount = $reportCounts[$empID] ?? 0;
        $reportPreparationAllowance = $reportCount * ($rates[4] ?? 0); // Rate ID 4 = report

        $totalDivingAllowance = $jobAllowance + $jobMealAllowance + 
                              $standbyAttendanceAllowance + $standbyMealAllowance + 
                              $reportPreparationAllowance;

        // Check if payment already exists for this empID, month, year
        $stmt_check = $conn->prepare("SELECT paymentID FROM payments WHERE empID = ? AND month = ? AND year = ?");
        $stmt_check->bind_param("isi", $empID, $monthName, $year);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $stmt_check->close();
            continue; // Skip duplicate
        }
        $stmt_check->close();

        $stmt = $conn->prepare("
            INSERT INTO payments (empID, jobAllowance, jobMealAllowance, 
            standbyAttendanceAllowance, standbyMealAllowance, 
            reportPreparationAllowance, totalDivingAllowance, month, year, date_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "iddddddss",
            $empID,
            $jobAllowance,
            $jobMealAllowance,
            $standbyAttendanceAllowance,
            $standbyMealAllowance,
            $reportPreparationAllowance,
            $totalDivingAllowance,
            $monthName,
            $year
        );
        if (!$stmt->execute()) {
            error_log("Failed to insert payment for employee $empID: " . $stmt->error);
        }
        $stmt->close();

        $results[] = [
            'empID' => $empID,
            'empName' => $empData['empName'],
            'jobAllowance' => $jobAllowance,
            'jobMealAllowance' => $jobMealAllowance,
            'standbyAttendanceAllowance' => $standbyAttendanceAllowance,
            'standbyMealAllowance' => $standbyMealAllowance,
            'reportPreparationAllowance' => $reportPreparationAllowance,
            'totalDivingAllowance' => $totalDivingAllowance
        ];
    }

    // --- NEW: Insert into published table after successful payments ---
    $insertPubSql = "INSERT INTO published (publishedBy, month, year, publishedDate) VALUES (?, ?, ?, NOW())";
    $insertPubStmt = $conn->prepare($insertPubSql);
    if (!$insertPubStmt) {
        throw new Exception("Prepare failed for published insert: " . $conn->error);
    }
    $insertPubStmt->bind_param("isi", $userID, $monthName, $year);
    if (!$insertPubStmt->execute()) {
        throw new Exception("Failed to insert published record: " . $insertPubStmt->error);
    }
    $insertPubStmt->close();
    // --- END NEW ---

    // Prepare successful response
    $response['success'] = true;
    $response['message'] = "Payments calculated successfully";
    $response['data'] = $results;

    // Send notification to accountant
    $notificationData = [
        'month' => $monthName,
        'year' => $year,
        'publishedID' => $conn->insert_id // Get the last inserted publishedID
    ];

    $notificationUrl = 'https://subseaops.worldsubsea.lk/controllers/sendPaymentNotificationController.php';
    $ch = curl_init($notificationUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $notificationResponse = curl_exec($ch);
    curl_close($ch);

    // Log the notification attempt
    error_log("Notification sent to accountant: " . $notificationResponse);

} catch (Exception $e) {
    // Handle errors
    http_response_code(500);
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Payment calculation error: " . $e->getMessage());
} finally {
    // Clean output buffer and send response
    ob_end_clean();
    echo json_encode($response);
    if (isset($conn) && $conn) $conn->close();
    exit;
}

