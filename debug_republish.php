<?php
// Debug script for republish functionality
include 'config/dbConnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) {
    echo "Access denied - User not logged in or not Operation Manager";
    exit();
}

$userID = $_SESSION['userID'];

echo "<h2>Debug Republish Functionality</h2>";
echo "<p>User ID: $userID</p>";

// Check what's in the published table
echo "<h3>Published Table Contents:</h3>";
$publishedSql = "SELECT * FROM published WHERE publishedBy = ? ORDER BY publishedDate DESC LIMIT 10";
$publishedStmt = $conn->prepare($publishedSql);
$publishedStmt->bind_param('i', $userID);
$publishedStmt->execute();
$publishedResult = $publishedStmt->get_result();

if ($publishedResult->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Published ID</th><th>Month</th><th>Year</th><th>Published By</th><th>Published Date</th></tr>";
    while ($row = $publishedResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['publishedID'] . "</td>";
        echo "<td>" . $row['month'] . "</td>";
        echo "<td>" . $row['year'] . "</td>";
        echo "<td>" . $row['publishedBy'] . "</td>";
        echo "<td>" . $row['publishedDate'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records found in published table for user $userID</p>";
}
$publishedStmt->close();

// Check what's in the payments table
echo "<h3>Payments Table Contents (Last 10):</h3>";
$paymentsSql = "SELECT * FROM payments ORDER BY date_time DESC LIMIT 10";
$paymentsResult = $conn->query($paymentsSql);

if ($paymentsResult->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Payment ID</th><th>Emp ID</th><th>Month</th><th>Year</th><th>Total Amount</th><th>Date Time</th></tr>";
    while ($row = $paymentsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['paymentID'] . "</td>";
        echo "<td>" . $row['empID'] . "</td>";
        echo "<td>" . $row['month'] . "</td>";
        echo "<td>" . $row['year'] . "</td>";
        echo "<td>" . $row['totalDivingAllowance'] . "</td>";
        echo "<td>" . $row['date_time'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records found in payments table</p>";
}

// Test republish for a specific month/year
if (isset($_GET['test_month']) && isset($_GET['test_year'])) {
    $testMonth = $_GET['test_month'];
    $testYear = intval($_GET['test_year']);
    
    echo "<h3>Testing Republish for $testMonth $testYear:</h3>";
    
    // Check if published record exists
    $checkPublishedSql = "SELECT COUNT(*) as count FROM published WHERE month = ? AND year = ? AND publishedBy = ?";
    $checkPublishedStmt = $conn->prepare($checkPublishedSql);
    $checkPublishedStmt->bind_param('sii', $testMonth, $testYear, $userID);
    $checkPublishedStmt->execute();
    $checkPublishedResult = $checkPublishedStmt->get_result();
    $publishedCount = $checkPublishedResult->fetch_assoc()['count'];
    $checkPublishedStmt->close();
    
    echo "<p>Published count for $testMonth $testYear: $publishedCount</p>";
    
    // Check if payments exist
    $checkPaymentsSql = "SELECT COUNT(*) as count FROM payments WHERE month = ? AND year = ?";
    $checkPaymentsStmt = $conn->prepare($checkPaymentsSql);
    $checkPaymentsStmt->bind_param('si', $testMonth, $testYear);
    $checkPaymentsStmt->execute();
    $checkPaymentsResult = $checkPaymentsStmt->get_result();
    $paymentCount = $checkPaymentsResult->fetch_assoc()['count'];
    $checkPaymentsStmt->close();
    
    echo "<p>Payment count for $testMonth $testYear: $paymentCount</p>";
    
    if ($publishedCount == 0 && $paymentCount == 0) {
        echo "<p style='color: red;'>No published payments found for $testMonth $testYear</p>";
    } else {
        echo "<p style='color: green;'>Records found - republish should work</p>";
    }
}

echo "<h3>Test Republish:</h3>";
echo "<form method='GET'>";
echo "<label>Month: <input type='text' name='test_month' placeholder='January'></label><br>";
echo "<label>Year: <input type='text' name='test_year' placeholder='2024'></label><br>";
echo "<input type='submit' value='Test Republish'>";
echo "</form>";

$conn->close();
?> 