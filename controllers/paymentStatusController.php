<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the request
error_log("Payment Status Controller Accessed: " . date('Y-m-d H:i:s'));
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST DATA: " . print_r($_POST, true));
error_log("GET DATA: " . print_r($_GET, true));

include __DIR__ . '/../config/dbConnect.php';
// session_start();

if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) {
    http_response_code(403);
    exit('Access denied');
}

$userID = $_SESSION['userID'];

// Handle republish action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'republish') {
    $month = $_POST['month'];
    $year = intval($_POST['year']);
    
    // Check if published record exists for this month/year
    $checkPublishedSql = "SELECT COUNT(*) as count FROM published WHERE month = ? AND year = ? AND publishedBy = ?";
    $checkPublishedStmt = $conn->prepare($checkPublishedSql);
    $checkPublishedStmt->bind_param('sii', $month, $year, $userID);
    $checkPublishedStmt->execute();
    $checkPublishedResult = $checkPublishedStmt->get_result();
    $publishedCount = $checkPublishedResult->fetch_assoc()['count'];
    $checkPublishedStmt->close();
    
    // Check if payments exist for this month/year
    $checkPaymentsSql = "SELECT COUNT(*) as count FROM payments WHERE month = ? AND year = ?";
    $checkPaymentsStmt = $conn->prepare($checkPaymentsSql);
    $checkPaymentsStmt->bind_param('si', $month, $year);
    $checkPaymentsStmt->execute();
    $checkPaymentsResult = $checkPaymentsStmt->get_result();
    $paymentCount = $checkPaymentsResult->fetch_assoc()['count'];
    $checkPaymentsStmt->close();
    
    if ($publishedCount == 0 && $paymentCount == 0) {
        echo 'No published payments found for the selected month/year';
        exit();
    }
    
    // Debug information
    error_log("Republish request - Month: $month, Year: $year, UserID: $userID");
    error_log("Published count: $publishedCount, Payment count: $paymentCount");
    
    // Additional debugging - check what's actually in the database
    $debugSql = "SELECT * FROM published WHERE month = ? AND year = ? AND publishedBy = ?";
    $debugStmt = $conn->prepare($debugSql);
    $debugStmt->bind_param('sii', $month, $year, $userID);
    $debugStmt->execute();
    $debugResult = $debugStmt->get_result();
    $debugData = $debugResult->fetch_assoc();
    error_log("Debug - Published record: " . json_encode($debugData));
    $debugStmt->close();
    
    $debugPaymentsSql = "SELECT COUNT(*) as count FROM payments WHERE month = ? AND year = ?";
    $debugPaymentsStmt = $conn->prepare($debugPaymentsSql);
    $debugPaymentsStmt->bind_param('si', $month, $year);
    $debugPaymentsStmt->execute();
    $debugPaymentsResult = $debugPaymentsStmt->get_result();
    $debugPaymentsCount = $debugPaymentsResult->fetch_assoc()['count'];
    error_log("Debug - Payments count: $debugPaymentsCount");
    $debugPaymentsStmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Store payment history before deletion (only if published record exists)
        if ($publishedCount > 0) {
            $historySql = "INSERT INTO payment_history (month, year, publishedBy, publishedDate, 
                            accountant_status, accountant_comment, accountant_date, accountant_name,
                            director_status, director_comment, director_date, director_name,
                            history_date, action_type)
                            SELECT p.month, p.year, p.publishedBy, p.publishedDate,
                                   CASE 
                                       WHEN pv.approval_status = 3 THEN 'Rejected'
                                       WHEN pv.approval_status = 1 THEN 'Verified'
                                       ELSE 'Pending'
                                   END as accountant_status,
                                   pv.comment as accountant_comment,
                                   pv.paymentVerifyDate as accountant_date,
                                   CONCAT(IFNULL(u.fname,''), ' ', IFNULL(u.lname,'')) as accountant_name,
                                   CASE 
                                       WHEN dv.approval_status = 3 THEN 'Rejected'
                                       WHEN dv.approval_status = 1 THEN 'Verified'
                                       ELSE 'Pending'
                                   END as director_status,
                                   dv.comment as director_comment,
                                   dv.directorVerifyDate as director_date,
                                   CONCAT(IFNULL(du.fname,''), ' ', IFNULL(du.lname,'')) as director_name,
                                   NOW(), 'Republished'
                            FROM published p 
                            LEFT JOIN paymentverify pv ON p.month = pv.month AND p.year = pv.year
                            LEFT JOIN users u ON pv.paymentVerifyBy = u.userID
                            LEFT JOIN directorverify dv ON p.month = dv.month AND p.year = dv.year
                            LEFT JOIN users du ON dv.directorVerifyBy = du.userID
                            WHERE p.month = ? AND p.year = ? AND p.publishedBy = ?";
            $historyStmt = $conn->prepare($historySql);
            $historyStmt->bind_param('sii', $month, $year, $userID);
            $historyStmt->execute();
            $historyStmt->close();
        }
        
        // Delete existing payments for this month/year
        if ($paymentCount > 0) {
            $deletePaymentsSql = "DELETE FROM payments WHERE month = ? AND year = ?";
            $deletePaymentsStmt = $conn->prepare($deletePaymentsSql);
            $deletePaymentsStmt->bind_param('si', $month, $year);
            $deletePaymentsStmt->execute();
            $deletePaymentsStmt->close();
        }
        
        // Delete existing published record
        if ($publishedCount > 0) {
            $deletePublishedSql = "DELETE FROM published WHERE month = ? AND year = ?";
            $deletePublishedStmt = $conn->prepare($deletePublishedSql);
            $deletePublishedStmt->bind_param('si', $month, $year);
            $deletePublishedStmt->execute();
            $deletePublishedStmt->close();
        }
        
        // Delete existing payment verification record if exists
        $deleteVerifySql = "DELETE FROM paymentverify WHERE month = ? AND year = ?";
        $deleteVerifyStmt = $conn->prepare($deleteVerifySql);
        $deleteVerifyStmt->bind_param('si', $month, $year);
        $deleteVerifyStmt->execute();
        $deleteVerifyStmt->close();
        
        // Delete existing CEO verification record if exists
        $deleteCeoVerifySql = "DELETE FROM ceoverify WHERE month = ? AND year = ?";
        $deleteCeoVerifyStmt = $conn->prepare($deleteCeoVerifySql);
        $deleteCeoVerifyStmt->bind_param('si', $month, $year);
        $deleteCeoVerifyStmt->execute();
        $deleteCeoVerifyStmt->close();
        
        // Delete existing Director verification record if exists
        $deleteDirectorVerifySql = "DELETE FROM directorverify WHERE month = ? AND year = ?";
        $deleteDirectorVerifyStmt = $conn->prepare($deleteDirectorVerifySql);
        $deleteDirectorVerifyStmt->bind_param('si', $month, $year);
        $deleteDirectorVerifyStmt->execute();
        $deleteDirectorVerifyStmt->close();
        
        $conn->commit();
        echo 'success';
        
    } catch (Exception $e) {
        $conn->rollback();
        echo 'Error: ' . $e->getMessage();
    }
    
    exit();
}

// Handle GET request for status table
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = isset($_GET['month']) ? $_GET['month'] : '';
    $year = isset($_GET['year']) ? intval($_GET['year']) : '';
    
    // Build WHERE clause for filtering
    $whereClause = "WHERE p.publishedBy = ?";
    $params = [$userID];
    $types = "i";
    
    if (!empty($month)) {
        $whereClause .= " AND p.month = ?";
        $params[] = $month;
        $types .= "s";
    }
    
    if (!empty($year)) {
        $whereClause .= " AND p.year = ?";
        $params[] = $year;
        $types .= "i";
    }
    
    // Query published payments with verification status (accountant and Director separately)
    $sql = "SELECT p.*, 
            CASE 
                WHEN pv.approval_status = 3 THEN 'Rejected'
                WHEN pv.approval_status = 1 THEN 'Verified'
                ELSE 'Pending'
            END as accountant_status,
            CASE 
                WHEN dv.approval_status = 3 THEN 'Rejected'
                WHEN dv.approval_status = 1 THEN 'Verified'
                ELSE 'Pending'
            END as director_status,
            pv.comment as accountant_comment,
            dv.comment as director_comment,
            pv.paymentVerifyDate as accountant_date,
            dv.directorVerifyDate as director_date,
            CONCAT(IFNULL(u.fname,''), ' ', IFNULL(u.lname,'')) as accountant_name,
            CONCAT(IFNULL(du.fname,''), ' ', IFNULL(du.lname,'')) as director_name
            FROM published p 
            LEFT JOIN paymentverify pv ON p.month = pv.month AND p.year = pv.year
            LEFT JOIN users u ON pv.paymentVerifyBy = u.userID
            LEFT JOIN directorverify dv ON p.month = dv.month AND p.year = dv.year
            LEFT JOIN users du ON dv.directorVerifyBy = du.userID
            $whereClause
            ORDER BY p.publishedDate DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo '<div class="alert alert-info">No published payments found for the selected criteria.</div>';
        exit();
    }
    
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover">';
    echo '<thead><tr>';
    echo '<th>Month/Year</th>';
    echo '<th>Published Date</th>';
    echo '<th>Accountant Details</th>';
    
    echo '<th>Director Details</th>';
    echo '<th>Status</th>';
    echo '<th>Actions</th>';
    echo '</tr></thead><tbody>';
    
    while ($row = $result->fetch_assoc()) {
        // Determine overall status
        $overallStatus = 'Pending';
        $statusClass = 'status-pending';
        
        if ($row['accountant_status'] == 'Rejected' || $row['director_status'] == 'Rejected') {
            $overallStatus = 'Rejected';
            $statusClass = 'status-rejected';
        } elseif ($row['accountant_status'] == 'Verified' && $row['director_status'] == 'Verified') {
            $overallStatus = 'Verified';
            $statusClass = 'status-verified';
        }
        
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($row['month']) . ' ' . htmlspecialchars($row['year']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($row['publishedDate']) . '</td>';
        
        // Accountant Details
        if ($row['accountant_status'] == 'Pending') {
            echo '<td><span class="text-muted">Awaiting verification</span></td>';
        } else {
            $accountantName = trim($row['accountant_name']) ?: 'Unknown';
            $accountantDate = $row['accountant_date'] ?: 'N/A';
            $accountantStatusClass = $row['accountant_status'] == 'Verified' ? 'status-verified' : 'status-rejected';
            echo '<td class="verifier-details">';
            echo '<span class="badge ' . $accountantStatusClass . '">' . htmlspecialchars($row['accountant_status']) . '</span><br>';
            echo '<strong>' . htmlspecialchars($accountantName) . '</strong><br>';
            echo '<small>' . htmlspecialchars($accountantDate) . '</small>';
            if ($row['accountant_status'] == 'Rejected' && $row['accountant_comment']) {
                echo '<br><small class="text-danger"><strong>Reason:</strong> ' . htmlspecialchars($row['accountant_comment']) . '</small>';
            }
            echo '</td>';
        }
        
        
        
        // Director Details
        if ($row['director_status'] == 'Pending') {
            echo '<td><span class="text-muted">Awaiting verification</span></td>';
        } else {
            $directorName = trim($row['director_name']) ?: 'Unknown';
            $directorDate = $row['director_date'] ?: 'N/A';
            $directorStatusClass = $row['director_status'] == 'Verified' ? 'status-verified' : 'status-rejected';
            echo '<td class="verifier-details">';
            echo '<span class="badge ' . $directorStatusClass . '">' . htmlspecialchars($row['director_status']) . '</span><br>';
            echo '<strong>' . htmlspecialchars($directorName) . '</strong><br>';
            echo '<small>' . htmlspecialchars($directorDate) . '</small>';
            if ($row['director_status'] == 'Rejected' && $row['director_comment']) {
                echo '<br><small class="text-danger"><strong>Reason:</strong> ' . htmlspecialchars($row['director_comment']) . '</small>';
            }
            echo '</td>';
        }
        
        // Overall Status
        echo '<td><span class="badge ' . $statusClass . '">' . htmlspecialchars($overallStatus) . '</span></td>';
        
        // Actions
        echo '<td class="action-buttons">';
        if ($overallStatus == 'Rejected') {
            echo '<button class="btn btn-sm btn-success republishBtn" data-month="' . htmlspecialchars($row['month']) . '" data-year="' . htmlspecialchars($row['year']) . '">';
            echo '<i class="fas fa-redo me-1"></i>Republish';
            echo '</button>';
        } elseif ($overallStatus == 'Pending') {
            echo '<span class="text-muted">Awaiting verification</span>';
        } else {
            echo '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Fully Verified</span>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
    
    $stmt->close();
    exit();
}

echo 'Invalid request';
?> 