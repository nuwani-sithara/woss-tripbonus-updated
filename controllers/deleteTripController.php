<?php
session_start();
require_once(__DIR__ . '/../config/dbConnect.php');

// Check if user is logged in and has role_id = 1 (supervisor)
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

$userID = $_SESSION['userID'];

// Handle trip deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trip'])) {
    $tripID = intval($_POST['tripID']);
    $jobID = intval($_POST['jobID']);
    
    // Validate that the trip belongs to a job created by this supervisor
    $jobCheck = $conn->query("SELECT jobID FROM jobs WHERE jobID = $jobID AND jobCreatedBy = $userID");
    if ($jobCheck->num_rows == 0) {
        $_SESSION['error'] = "You don't have permission to delete this trip.";
        header("Location: ../views/managejobdays.php?jobID=$jobID");
        exit();
    }
    
    // Check if the job is already approved/rejected (should not be deletable)
    $approvalCheck = $conn->query("SELECT approval_status FROM approvals WHERE jobID = $jobID");
    if ($approvalCheck->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete trips from jobs that have been approved or rejected.";
        header("Location: ../views/managejobdays.php?jobID=$jobID");
        exit();
    }
    
    // Validate that the trip exists and belongs to the specified job
    $tripCheck = $conn->query("SELECT tripID FROM trips WHERE tripID = $tripID AND jobID = $jobID");
    if ($tripCheck->num_rows == 0) {
        $_SESSION['error'] = "Trip not found or doesn't belong to the specified job.";
        header("Location: ../views/managejobdays.php?jobID=$jobID");
        exit();
    }
    
    // Delete trip and related data
    deleteTripAndRelatedData($conn, $tripID);
    
    $_SESSION['success'] = "Trip deleted successfully!";
    header("Location: ../views/managejobdays.php?jobID=$jobID");
    exit();
}

// Function to delete trip and all related data
function deleteTripAndRelatedData($conn, $tripID) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete job assignments for this trip
        $conn->query("DELETE FROM jobassignments WHERE tripID = $tripID");
        
        // Delete job attendance for this trip
        $conn->query("DELETE FROM job_attendance WHERE tripID = $tripID");
        
        // Delete the trip itself
        $conn->query("DELETE FROM trips WHERE tripID = $tripID");
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
}

// If accessed directly without POST data, redirect back
header("Location: ../views/supervisoreditjobs.php");
exit();
?> 