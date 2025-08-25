<?php
include '../config/dbConnect.php';
session_start();
require_once '../vendor/autoload.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 5) {
    http_response_code(403);
    exit('Access denied');
}
$userID = $_SESSION['userID'];

function getApprovals($conn, $month, $year) {
    $roles = [
        'accountant' => ['table' => 'paymentverify', 'by' => 'paymentVerifyBy', 'date' => 'paymentVerifyDate'],
        'director' => ['table' => 'directorverify', 'by' => 'directorVerifyBy', 'date' => 'directorVerifyDate'],
    ];
    $result = [];
    foreach ($roles as $role => $info) {
        $sql = "SELECT {$info['by']} as userID, {$info['date']} as date, u.fname, u.lname FROM {$info['table']} v JOIN users u ON v.{$info['by']} = u.userID WHERE v.month = ? AND v.year = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $month, $year);
        $stmt->execute();
        $res = $stmt->get_result();
        $result[$role] = $res->fetch_assoc();
        $stmt->close();
    }
    return $result;
}

function getPayments($conn, $month, $year) {
    $sql = "SELECT p.*, u.fname, u.lname, e.empID FROM payments p LEFT JOIN employees e ON p.empID = e.empID LEFT JOIN users u ON e.userID = u.userID WHERE p.month = ? AND p.year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function getTotals($conn, $month, $year) {
    $sql = "SELECT SUM(jobAllowance) as totalJobAllowance, SUM(jobMealAllowance) as totalJobMealAllowance, SUM(standbyAttendanceAllowance) as totalStandbyAttendanceAllowance, SUM(standbyMealAllowance) as totalStandbyMealAllowance, SUM(reportPreparationAllowance) as totalReportPreparationAllowance, SUM(totalDivingAllowance) as totalDivingAllowance FROM payments WHERE month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $totals = $result->fetch_assoc();
    $stmt->close();
    return $totals;
}

function allApproved($approvals) {
    return $approvals['accountant'] && $approvals['director'];
}

function logAction($conn, $userID, $action_type, $month, $year, $file_type = null, $recipients = null, $details = null) {
    $sql = "INSERT INTO payroll_action_log (userID, action_type, month, year, file_type, recipients, details) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ississs', $userID, $action_type, $month, $year, $file_type, $recipients, $details);
    $stmt->execute();
    $stmt->close();
}

$month = isset($_GET['month']) ? $_GET['month'] : date('F');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    $approvals = getApprovals($conn, $month, $year);
    $payments = getPayments($conn, $month, $year);
    $totals = getTotals($conn, $month, $year);
    echo json_encode([
        'approvals' => $approvals,
        'payments' => $payments,
        'totals' => $totals,
        'allApproved' => allApproved($approvals)
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export') {
    $fileType = isset($_GET['fileType']) ? $_GET['fileType'] : 'csv';
    $approvals = getApprovals($conn, $month, $year);
    if (!allApproved($approvals)) {
        http_response_code(403);
        echo "Not all approvals present.";
        exit();
    }
    $payments = getPayments($conn, $month, $year);
    $totals = getTotals($conn, $month, $year);
    $filename = "Payroll_Report_{$month}_{$year}." . $fileType;
    $headers = ['Employee', 'Job Allowance', 'Job Meal', 'Standby Attendance', 'Standby Meal', 'Report Prep', 'Total Diving', 'Date'];
    $rows = [];
    foreach ($payments as $row) {
        $rows[] = [
            $row['fname'] . ' ' . $row['lname'],
            $row['jobAllowance'],
            $row['jobMealAllowance'],
            $row['standbyAttendanceAllowance'],
            $row['standbyMealAllowance'],
            $row['reportPreparationAllowance'],
            $row['totalDivingAllowance'],
            $row['date_time']
        ];
    }
    // Export logic (CSV, XLSX, DOCX, PDF) - similar to paymentVerificationController.php
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
            'Monthly Total',
            '', '', '', '', '',
            number_format($totals['totalDivingAllowance'], 2),
            ''
        ];
        fputcsv($output, $totalsRow);
        fclose($output);
        logAction($conn, $userID, 'export', $month, $year, $fileType, null, 'Payroll export');
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
            'Monthly Total', '', '', '', '', '',
            number_format($totals['totalDivingAllowance'], 2), ''
        ];
        $sheet->fromArray([$totalsRow], NULL, 'A' . (count($rows) + 2));
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $writer->save('php://output');
        logAction($conn, $userID, 'export', $month, $year, $fileType, null, 'Payroll export');
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
            'Monthly Total', '', '', '', '', '',
            number_format($totals['totalDivingAllowance'], 2), ''
        ];
        foreach ($totalsRow as $cell) {
            $table->addCell(2000)->addText($cell);
        }
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $writer->save('php://output');
        logAction($conn, $userID, 'export', $month, $year, $fileType, null, 'Payroll export');
        exit();
    } elseif ($fileType === 'pdf') {
        if (!class_exists('TCPDF')) {
            header('Content-Type: text/plain');
            echo "TCPDF library not installed.";
            exit();
        }
        $pdf = new \TCPDF();
        $pdf->AddPage();
        $html = '<h2>Payroll Report - ' . htmlspecialchars($month) . ' ' . htmlspecialchars($year) . '</h2>';
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
            'Monthly Total', '', '', '', '', '',
            number_format($totals['totalDivingAllowance'], 2), ''
        ];
        $html .= '<tr style="font-weight:bold;background:#e9ecef;">';
        foreach ($totalsRow as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filename, 'D');
        logAction($conn, $userID, 'export', $month, $year, $fileType, null, 'Payroll export');
        exit();
    } else {
        header('Content-Type: text/plain');
        echo "Invalid file type.";
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'email') {
    $approvals = getApprovals($conn, $month, $year);
    if (!allApproved($approvals)) {
        http_response_code(403);
        echo "Not all approvals present.";
        exit();
    }
    $payments = getPayments($conn, $month, $year);
    $totals = getTotals($conn, $month, $year);
    // Email logic using PHPMailer
    $recipients = 'lahiru@mclarens.lk, nuwanisithara.com@gmail.com, devindi@mclarens.lk';
    $to = 'lahiru@mclarens.lk';
    $cc = ['nuwanisithara.com@gmail.com', 'devindi@mclarens.lk'];
    $subject = "Payroll Report for $month $year";
    $headers = ['Employee', 'Job Allowance', 'Job Meal', 'Standby Attendance', 'Standby Meal', 'Report Prep', 'Total Diving', 'Date & Time'];
    $rows = [];
    foreach ($payments as $row) {
        $rows[] = [
            $row['fname'] . ' ' . $row['lname'],
            $row['jobAllowance'],
            $row['jobMealAllowance'],
            $row['standbyAttendanceAllowance'],
            $row['standbyMealAllowance'],
            $row['reportPreparationAllowance'],
            $row['totalDivingAllowance'],
            $row['date_time']
        ];
    }
    $totalsRow = [
        'Monthly Total', '', '', '', '', '',
        number_format($totals['totalDivingAllowance'], 2), ''
    ];
    // Build HTML table for email body
    $html = '<h2>Payroll Report - ' . htmlspecialchars($month) . ' ' . htmlspecialchars($year) . '</h2>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:sans-serif;font-size:14px;">';
    $html .= '<thead><tr style="background:#f8f9fa;">';
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
    $html .= '<tr style="font-weight:bold;background:#e9ecef;">';
    foreach ($totalsRow as $cell) {
        $html .= '<td>' . htmlspecialchars($cell) . '</td>';
    }
    $html .= '</tr>';
    $html .= '</tbody></table>';
    $html .= '<br><p>This is an automated payroll report generated by the WOSS Trip Bonus System.</p>';
    // Prepare CSV attachment
    $csvData = fopen('php://temp', 'r+');
    fputcsv($csvData, $headers);
    foreach ($rows as $row) {
        fputcsv($csvData, $row);
    }
    fputcsv($csvData, $totalsRow);
    rewind($csvData);
    $csvString = stream_get_contents($csvData);
    fclose($csvData);
    // Send email using PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com'; // Change as needed
        $mail->SMTPAuth = true;
        $mail->Username = 'systems@mclarens.lk'; // Change to your SMTP username
        $mail->Password = 'Com38518'; // Change to your SMTP password or app password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('tripbonus@worldsubsea.lk', 'WOSS Payroll System');
        $mail->addAddress($to);
        foreach ($cc as $ccEmail) {
            $mail->addCC($ccEmail);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->addStringAttachment($csvString, "Payroll_Report_{$month}_{$year}.csv", 'base64', 'text/csv');
        $mail->send();
        echo 'success';
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Mailer Error: ' . $mail->ErrorInfo;
    }
    logAction($conn, $userID, 'email', $month, $year, null, $recipients, 'Payroll email sent');
    exit();
}
echo 'Invalid request'; 