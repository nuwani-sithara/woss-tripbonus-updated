<?php
include '../config/dbConnect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $jobID = (int)$_POST['jobID'];
        $endDate = $_POST['endDate'] . ' ' . date('H:i:s');

        // Update job with end date
        $updateJob = $conn->prepare("UPDATE jobs SET end_date = ? WHERE jobID = ?");
        $updateJob->bind_param("si", $endDate, $jobID);
        $updateJob->execute();
        $updateJob->close();

        // Redirect back to manage job days
        header("Location: ../views/managejobdays.php?jobID=$jobID");
        exit();

    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}