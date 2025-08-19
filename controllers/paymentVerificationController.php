<?php
include '../config/dbConnect.php';
session_start();
require_once '../vendor/autoload.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 5) {
    http_response_code(403);
    exit('Access denied');
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export') {
    $month = isset($_GET['month']) ? $_GET['month'] : date('F');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $fileType = isset($_GET['fileType']) ? $_GET['fileType'] : 'csv';

    // Query payment data
    $paymentsSql = "SELECT p.*, u.fname, u.lname, e.empID FROM payments p LEFT JOIN employees e ON p.empID = e.empID LEFT JOIN users u ON e.userID = u.userID WHERE p.month = ? AND p.year = ?";
    $stmt = $conn->prepare($paymentsSql);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    // --- Fetch job counts and standby counts for all employees in this month/year ---
    $empIDs = [];
    $paymentsRows = [];
    while ($row = $result->fetch_assoc()) {
        $empIDs[] = $row['empID'];
        $paymentsRows[] = $row;
    }
    $empIDs = array_unique($empIDs);
    $empIDsStr = implode(',', array_map('intval', $empIDs));
    // Get numeric month for queries
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
    // Standby count per empID
    $standbyCounts = [];
    if ($empIDsStr) {
        $standbyCountSql = "SELECT sa.empID, COUNT(*) as standby_count
            FROM standbyassignments sa
            JOIN standby_attendance s ON sa.standby_attendanceID = s.standby_attendanceID
            WHERE MONTH(s.date) = ? AND YEAR(s.date) = ?
              AND sa.empID IN ($empIDsStr)
            GROUP BY sa.empID";
        $stmtStandby = $conn->prepare($standbyCountSql);
        $stmtStandby->bind_param('ii', $monthNum, $year);
        $stmtStandby->execute();
        $resStandby = $stmtStandby->get_result();
        while ($row = $resStandby->fetch_assoc()) {
            $standbyCounts[$row['empID']] = $row['standby_count'];
        }
        $stmtStandby->close();
    }

    $filename = "Payment_Report_{$month}_{$year}." . $fileType;
    $headers = ['Employee', 'Job Count', 'Standby Attendance Count', 'Job Allowance', 'Job Meal', 'Standby Attendance', 'Standby Meal', 'Report Prep', 'Total Diving', 'Date'];
    $rows = [];
    foreach ($paymentsRows as $row) {
        $empID = $row['empID'];
        $rows[] = [
            $row['fname'] . ' ' . $row['lname'],
            $jobCounts[$empID] ?? 0,
            $standbyCounts[$empID] ?? 0,
            $row['jobAllowance'],
            $row['jobMealAllowance'],
            $row['standbyAttendanceAllowance'],
            $row['standbyMealAllowance'],
            $row['reportPreparationAllowance'],
            $row['totalDivingAllowance'],
            $row['date_time']
        ];
    }
    if ($fileType === 'csv') {
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        // Add totals row
        $totalsRow = [
            'Monthly Total', '', '',
            number_format(array_sum(array_column($rows, 3)), 2),
            number_format(array_sum(array_column($rows, 4)), 2),
            number_format(array_sum(array_column($rows, 5)), 2),
            number_format(array_sum(array_column($rows, 6)), 2),
            number_format(array_sum(array_column($rows, 7)), 2),
            number_format(array_sum(array_column($rows, 8)), 2),
            ''
        ];
        fputcsv($output, $totalsRow);
        fclose($output);
        exit();
    } elseif ($fileType === 'xlsx') {
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            header('Content-Type: text/plain');
            echo "PhpSpreadsheet library not installed.";
            exit();
        }
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, NULL, 'A1');
        $sheet->fromArray($rows, NULL, 'A2');
        // Add totals row
        $totalsRow = [
            'Monthly Total', '', '',
            number_format(array_sum(array_column($rows, 3)), 2),
            number_format(array_sum(array_column($rows, 4)), 2),
            number_format(array_sum(array_column($rows, 5)), 2),
            number_format(array_sum(array_column($rows, 6)), 2),
            number_format(array_sum(array_column($rows, 7)), 2),
            number_format(array_sum(array_column($rows, 8)), 2),
            ''
        ];
        $sheet->fromArray([$totalsRow], NULL, 'A' . (count($rows) + 2));
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $writer->save('php://output');
        exit();
    } elseif ($fileType === 'docx') {
        if (!class_exists('PhpOffice\\PhpWord\\PhpWord')) {
            header('Content-Type: text/plain');
            echo "PhpWord library not installed.";
            exit();
        }
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        foreach ($headers as $header) {
            $table->addCell(2000)->addText($header);
        }
        foreach ($rows as $row) {
            $table->addRow();
            foreach ($row as $cell) {
                $table->addCell(2000)->addText($cell);
            }
        }
        // Add totals row
        $table->addRow();
        $totalsRow = [
            'Monthly Total', '', '',
            number_format(array_sum(array_column($rows, 3)), 2),
            number_format(array_sum(array_column($rows, 4)), 2),
            number_format(array_sum(array_column($rows, 5)), 2),
            number_format(array_sum(array_column($rows, 6)), 2),
            number_format(array_sum(array_column($rows, 7)), 2),
            number_format(array_sum(array_column($rows, 8)), 2),
            ''
        ];
        foreach ($totalsRow as $cell) {
            $table->addCell(2000)->addText($cell);
        }
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $writer->save('php://output');
        exit();
    } elseif ($fileType === 'pdf') {
        if (!class_exists('TCPDF')) {
            header('Content-Type: text/plain');
            echo "TCPDF library not installed.";
            exit();
        }
        $pdf = new \TCPDF();
        $pdf->AddPage();
        $html = '<h2>Payment Report - ' . htmlspecialchars($month) . ' ' . htmlspecialchars($year) . '</h2>';
        $html .= '<table border="1" cellpadding="4"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        // Add totals row
        $totalsRow = [
            'Monthly Total', '', '',
            number_format(array_sum(array_column($rows, 3)), 2),
            number_format(array_sum(array_column($rows, 4)), 2),
            number_format(array_sum(array_column($rows, 5)), 2),
            number_format(array_sum(array_column($rows, 6)), 2),
            number_format(array_sum(array_column($rows, 7)), 2),
            number_format(array_sum(array_column($rows, 8)), 2),
            ''
        ];
        $html .= '<tr style="font-weight:bold;background:#e9ecef;">';
        foreach ($totalsRow as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filename, 'D');
        exit();
    } else {
        header('Content-Type: text/plain');
        echo "Invalid file type.";
        exit();
    }
}
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = isset($_GET['month']) ? $_GET['month'] : date('F');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    // Check if already verified or rejected
    $verifyCheckSql = "SELECT * FROM paymentverify WHERE month = ? AND year = ?";
    $verifyStmt = $conn->prepare($verifyCheckSql);
    $verifyStmt->bind_param('si', $month, $year);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $isVerified = $verifyResult->num_rows > 0;
    $verifyInfo = $verifyResult->fetch_assoc();
    if ($isVerified) {
        $statusText = 'verified';
        $badgeClass = 'bg-success';
        if ($verifyInfo && isset($verifyInfo['approval_status']) && $verifyInfo['approval_status'] == 3) {
            $statusText = 'rejected';
            $badgeClass = 'bg-danger';
        }
        echo '<div class="alert alert-success">All payments for ' . htmlspecialchars($month) . ' ' . htmlspecialchars($year) . ' are <span class="badge ' . $badgeClass . '">' . ucfirst($statusText) . '</span>.';
        if ($verifyInfo && isset($verifyInfo['paymentVerifyBy'])) {
            echo ' <br>By UserID: ' . htmlspecialchars($verifyInfo['paymentVerifyBy']) . ' on ' . htmlspecialchars($verifyInfo['paymentVerifyDate']);
        }
        echo '</div>';
    } else {
        // Store button HTML to be displayed after the table
        $buttonHtml = '<div class="d-flex justify-content-end mt-4">';
        $buttonHtml .= '<button class="btn btn-danger verify-btn me-2" id="rejectAllBtn" style="display:none;">';
        $buttonHtml .= '<i class="fas fa-times-circle me-2"></i>Reject All Payments for ' . htmlspecialchars($month) . ' ' . htmlspecialchars($year);
        $buttonHtml .= '</button>';
        $buttonHtml .= '<button class="btn btn-success verify-btn" id="verifyAllBtn" style="display:none;">';
        $buttonHtml .= '<i class="fas fa-check-circle me-2"></i>Verify All Payments for ' . htmlspecialchars($month) . ' ' . htmlspecialchars($year);
        $buttonHtml .= '</button>';
        $buttonHtml .= '</div>';
    }
    echo '<!--VERIFIED_STATUS-->';
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
    echo '<div id="monthlyTotals" class="mb-3">';
    echo '<div class="card card-body bg-light">';
    echo '<h5 class="mb-2">Monthly Totals</h5>';
    echo '<div class="row">';
    echo '<div class="col-md-4"><strong>Job Allowance:</strong> ' . number_format($totals['totalJobAllowance'], 2) . '</div>';
    echo '<div class="col-md-4"><strong>Job Meal Allowance:</strong> ' . number_format($totals['totalJobMealAllowance'], 2) . '</div>';
    echo '<div class="col-md-4"><strong>Standby Attendance Allowance:</strong> ' . number_format($totals['totalStandbyAttendanceAllowance'], 2) . '</div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="col-md-4"><strong>Standby Meal Allowance:</strong> ' . number_format($totals['totalStandbyMealAllowance'], 2) . '</div>';
    echo '<div class="col-md-4"><strong>Report Preparation Allowance:</strong> ' . number_format($totals['totalReportPreparationAllowance'], 2) . '</div>';
    echo '<div class="col-md-4"><strong>Total Diving Allowance:</strong> ' . number_format($totals['totalDivingAllowance'], 2) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    $totalsStmt->close();
    // Now output the table
    $paymentsSql = "SELECT p.*, u.fname, u.lname, e.empID FROM payments p LEFT JOIN employees e ON p.empID = e.empID LEFT JOIN users u ON e.userID = u.userID WHERE p.month = ? AND p.year = ?";
    $stmt = $conn->prepare($paymentsSql);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    // --- Fetch job counts and standby counts for all employees in this month/year ---
    $empIDs = [];
    $paymentsRows = [];
    while ($row = $result->fetch_assoc()) {
        $empIDs[] = $row['empID'];
        $paymentsRows[] = $row;
    }
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
    // Standby count per empID
    $standbyCounts = [];
    if ($empIDsStr) {
        $standbyCountSql = "SELECT sa.empID, COUNT(*) as standby_count
            FROM standbyassignments sa
            JOIN standby_attendance s ON sa.standby_attendanceID = s.standby_attendanceID
            WHERE MONTH(s.date) = ? AND YEAR(s.date) = ?
              AND sa.empID IN ($empIDsStr)
            GROUP BY sa.empID";
        $stmtStandby = $conn->prepare($standbyCountSql);
        $stmtStandby->bind_param('ii', $monthNum, $year);
        $stmtStandby->execute();
        $resStandby = $stmtStandby->get_result();
        while ($row = $resStandby->fetch_assoc()) {
            $standbyCounts[$row['empID']] = $row['standby_count'];
        }
        $stmtStandby->close();
    }
    echo '<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr><th>Employee</th><th>Job Count</th><th>Standby Attendance Count</th><th>Job Allowance</th><th>Job Meal</th><th>Standby Attendance</th><th>Standby Meal</th><th>Report Prep</th><th>Total Diving</th><th>Date</th></tr></thead><tbody>';
    foreach ($paymentsRows as $row) {
        $empID = $row['empID'];
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['fname'] . ' ' . $row['lname']) . '</td>';
        echo '<td>' . ($jobCounts[$empID] ?? 0) . '</td>';
        echo '<td>' . ($standbyCounts[$empID] ?? 0) . '</td>';
        echo '<td>' . number_format($row['jobAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['jobMealAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['standbyAttendanceAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['standbyMealAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['reportPreparationAllowance'], 2) . '</td>';
        echo '<td>' . number_format($row['totalDivingAllowance'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($row['date_time']) . '</td>';
        // if ($isVerified) {
        //     echo '<td><span class="badge bg-success">Verified</span></td>';
        // } else {
        //     echo '<td><span class="badge bg-warning text-dark">Pending</span></td>';
        // }
        echo '</tr>';
    }
    // Add totals row in table footer
    echo '</tbody><tfoot><tr style="font-weight:bold;background:#e9ecef;"><td>Monthly Total</td><td></td><td></td>'
    .'<td>' . number_format($totals['totalJobAllowance'], 2) . '</td>'
    .'<td>' . number_format($totals['totalJobMealAllowance'], 2) . '</td>'
    .'<td>' . number_format($totals['totalStandbyAttendanceAllowance'], 2) . '</td>'
    .'<td>' . number_format($totals['totalStandbyMealAllowance'], 2) . '</td>'
    .'<td>' . number_format($totals['totalReportPreparationAllowance'], 2) . '</td>'
    .'<td>' . number_format($totals['totalDivingAllowance'], 2) . '</td>'
    .'<td colspan="2"></td></tr></tfoot></table></div>';
    
    // Add the buttons after the table if they exist
    if (isset($buttonHtml)) {
        echo $buttonHtml;
    }
    
    $stmt->close();
    $verifyStmt->close();
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $month = $_POST['month'];
    $year = intval($_POST['year']);
    $userID = $_SESSION['userID'];
    $checkSql = "SELECT * FROM paymentverify WHERE month = ? AND year = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo 'already_verified';
        exit();
    }
    $stmt->close();
    $insertSql = "INSERT INTO paymentverify (paymentVerifyBy, month, year, paymentVerifyDate, approval_status) VALUES (?, ?, ?, NOW(), 1)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param('isi', $userID, $month, $year);
    if ($stmt->execute()) {
        // Step 1: Get the inserted paymentVerifyID
        $paymentVerifyID = $conn->insert_id;
        // Update published_status to 1 for verified
        $updatePublishedSql = "UPDATE published SET published_status = 1 WHERE month = ? AND year = ?";
        $updatePublishedStmt = $conn->prepare($updatePublishedSql);
        $updatePublishedStmt->bind_param('si', $month, $year);
        $updatePublishedStmt->execute();
        $updatePublishedStmt->close();
        // Step 2: Insert into approvals
        $approval_status = 1;
        $approval_stage = 'payment_verification';
        $approval_by = $userID;
        $approval_date = date('Y-m-d H:i:s');
        $insertApprovalSql = "INSERT INTO approvals (paymentVerifyID, approval_status, approval_stage, approval_by, approval_date) VALUES (?, ?, ?, ?, ?)";
        $approvalStmt = $conn->prepare($insertApprovalSql);
        $approvalStmt->bind_param('iisis', $paymentVerifyID, $approval_status, $approval_stage, $approval_by, $approval_date);
        if ($approvalStmt->execute()) {
            // Step 3: Get the inserted approvalID
            $approvalID = $conn->insert_id;
            // Step 4: Insert into paymentverifier
            $insertVerifierSql = "INSERT INTO paymentverifier (approvalID) VALUES (?)";
            $verifierStmt = $conn->prepare($insertVerifierSql);
            $verifierStmt->bind_param('i', $approvalID);
            $verifierStmt->execute();
            $verifierStmt->close();
        }
        $approvalStmt->close();

        // Fetch publishedID for this month/year
        $publishedID = null;
        $pubStmt = $conn->prepare("SELECT publishedID FROM published WHERE month = ? AND year = ?");
        $pubStmt->bind_param('si', $month, $year);
        $pubStmt->execute();
        $pubStmt->bind_result($publishedID);
        $pubStmt->fetch();
        $pubStmt->close();
        
        echo 'success';

        // Send notification to OM & CEO
        $notifyUrl = 'https://tripbonus.worldsubsea.lk/controllers/sendPaymentVerifyNotificationController.php';
        $notifyData = [
            'paymentVerifyID' => $paymentVerifyID,
            'publishedID' => $publishedID  // Fetch publishedID for this month/year
        ];
        $ch = curl_init($notifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $notifyResponse = curl_exec($ch);
        curl_close($ch);
    } else {
        echo 'error';
    }
    $stmt->close();
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    $month = $_POST['month'];
    $year = intval($_POST['year']);
    $userID = $_SESSION['userID'];
    $comment = isset($_POST['comment']) ? $_POST['comment'] : null;
    $checkSql = "SELECT * FROM paymentverify WHERE month = ? AND year = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo 'already_verified';
        exit();
    }
    $stmt->close();
    $insertSql = "INSERT INTO paymentverify (paymentVerifyBy, month, year, paymentVerifyDate, approval_status, comment) VALUES (?, ?, ?, NOW(), 3, ?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param('isis', $userID, $month, $year, $comment);
    if ($stmt->execute()) {
        // Step 1: Get the inserted paymentVerifyID
        $paymentVerifyID = $conn->insert_id;
        // Update published_status to 3 for rejected
        $updatePublishedSql = "UPDATE published SET published_status = 3 WHERE month = ? AND year = ?";
        $updatePublishedStmt = $conn->prepare($updatePublishedSql);
        $updatePublishedStmt->bind_param('si', $month, $year);
        $updatePublishedStmt->execute();
        $updatePublishedStmt->close();
        // Step 2: Insert into approvals
        $approval_status = 3;
        $approval_stage = 'payment_verification';
        $approval_by = $userID;
        $approval_date = date('Y-m-d H:i:s');
        $insertApprovalSql = "INSERT INTO approvals (paymentVerifyID, approval_status, approval_stage, approval_by, approval_date) VALUES (?, ?, ?, ?, ?)";
        $approvalStmt = $conn->prepare($insertApprovalSql);
        $approvalStmt->bind_param('iisis', $paymentVerifyID, $approval_status, $approval_stage, $approval_by, $approval_date);
        if ($approvalStmt->execute()) {
            // Step 3: Get the inserted approvalID
            $approvalID = $conn->insert_id;
            // Step 4: Insert into paymentverifier
            $insertVerifierSql = "INSERT INTO paymentverifier (approvalID) VALUES (?)";
            $verifierStmt = $conn->prepare($insertVerifierSql);
            $verifierStmt->bind_param('i', $approvalID);
            $verifierStmt->execute();
            $verifierStmt->close();
        }
        $approvalStmt->close();
        // Fetch publishedID for this month/year
        $publishedID = null;
        $pubStmt = $conn->prepare("SELECT publishedID FROM published WHERE month = ? AND year = ?");
        $pubStmt->bind_param('si', $month, $year);
        $pubStmt->execute();
        $pubStmt->bind_result($publishedID);
        $pubStmt->fetch();
        $pubStmt->close();

        echo 'success';

        // Send rejection notification to OM
        $notifyUrl = 'https://tripbonus.worldsubsea.lk/controllers/sendPaymentRejectNotificationController.php';
        $notifyData = [
            'paymentVerifyID' => $paymentVerifyID,
            'publishedID' => $publishedID
        ];
        $ch = curl_init($notifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $notifyResponse = curl_exec($ch);
        curl_close($ch);

    } else {
        echo 'error';
    }
    $stmt->close();
    exit();
}
echo 'Invalid request';
