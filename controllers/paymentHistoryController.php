<?php
include '../config/dbConnect.php';
session_start();

if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) {
    http_response_code(403);
    exit('Access denied');
}

$userID = $_SESSION['userID'];

// Handle GET request for payment history
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = isset($_GET['month']) ? $_GET['month'] : '';
    $year = isset($_GET['year']) ? intval($_GET['year']) : '';
    
    // Build WHERE clause for filtering
    $whereClause = "WHERE ph.publishedBy = ?";
    $params = [$userID];
    $types = "i";
    
    if (!empty($month)) {
        $whereClause .= " AND ph.month = ?";
        $params[] = $month;
        $types .= "s";
    }
    
    if (!empty($year)) {
        $whereClause .= " AND ph.year = ?";
        $params[] = $year;
        $types .= "i";
    }
    
    // Query payment history
    $sql = "SELECT ph.*, 
            CONCAT(IFNULL(pu.fname,''), ' ', IFNULL(pu.lname,'')) as publisher_name
            FROM payment_history ph
            LEFT JOIN users pu ON ph.publishedBy = pu.userID
            $whereClause
            ORDER BY ph.history_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo '<div class="alert alert-info">No payment history found for the selected criteria.</div>';
        exit();
    }
    
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover">';
    echo '<thead><tr>';
    echo '<th>Month/Year</th>';
    echo '<th>Published Date</th>';
    echo '<th>Publisher</th>';
    echo '<th>Accountant Details</th>';
    
    echo '<th>Director Details</th>';
    echo '<th>Action Type</th>';
    echo '<th>History Date</th>';
    echo '</tr></thead><tbody>';
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($row['month']) . ' ' . htmlspecialchars($row['year']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($row['publishedDate']) . '</td>';
        echo '<td>' . htmlspecialchars($row['publisher_name'] ?: 'Unknown') . '</td>';
        
        // Accountant Details
        if ($row['accountant_status'] == 'Pending') {
            echo '<td><span class="text-muted">Awaiting verification</span></td>';
        } else {
            $accountantStatusClass = $row['accountant_status'] == 'Verified' ? 'status-verified' : 'status-rejected';
            echo '<td class="verifier-details">';
            echo '<span class="badge ' . $accountantStatusClass . '">' . htmlspecialchars($row['accountant_status']) . '</span><br>';
            echo '<strong>' . htmlspecialchars($row['accountant_name'] ?: 'Unknown') . '</strong><br>';
            echo '<small>' . htmlspecialchars($row['accountant_date'] ?: 'N/A') . '</small>';
            if ($row['accountant_status'] == 'Rejected' && $row['accountant_comment']) {
                echo '<br><small class="text-danger"><strong>Reason:</strong> ' . htmlspecialchars($row['accountant_comment']) . '</small>';
            }
            echo '</td>';
        }
        
        
        
        // Director Details
        if ($row['director_status'] == 'Pending') {
            echo '<td><span class="text-muted">Awaiting verification</span></td>';
        } else {
            $directorStatusClass = $row['director_status'] == 'Verified' ? 'status-verified' : 'status-rejected';
            echo '<td class="verifier-details">';
            echo '<span class="badge ' . $directorStatusClass . '">' . htmlspecialchars($row['director_status']) . '</span><br>';
            echo '<strong>' . htmlspecialchars($row['director_name'] ?: 'Unknown') . '</strong><br>';
            echo '<small>' . htmlspecialchars($row['director_date'] ?: 'N/A') . '</small>';
            if ($row['director_status'] == 'Rejected' && $row['director_comment']) {
                echo '<br><small class="text-danger"><strong>Reason:</strong> ' . htmlspecialchars($row['director_comment']) . '</small>';
            }
            echo '</td>';
        }
        
        // Action Type
        $actionClass = $row['action_type'] == 'Republished' ? 'badge-warning' : 'badge-info';
        echo '<td><span class="badge ' . $actionClass . '">' . htmlspecialchars($row['action_type']) . '</span></td>';
        
        // History Date
        echo '<td>' . htmlspecialchars($row['history_date']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
    
    $stmt->close();
    exit();
}

echo 'Invalid request';
?> 