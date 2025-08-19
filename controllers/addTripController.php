<?php
include '../config/dbConnect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $jobID = (int)$_POST['jobID'];
        $tripDate = $_POST['tripDate'] . ' ' . date('H:i:s');

        // Insert trip
        $tripInsert = $conn->prepare("INSERT INTO trips (jobID, trip_date) VALUES (?, ?)");
        $tripInsert->bind_param("is", $jobID, $tripDate);
        $tripInsert->execute();
        $tripID = $conn->insert_id;
        $tripInsert->close();

        // Redirect to assign employees
        header("Location: ../views/assignemployees.php?tripID=$tripID");
        exit();

    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}