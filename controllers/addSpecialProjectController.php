<?php
include '../config/dbConnect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $jobID = (int)$_POST['jobID'];
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $vesselID = (int)($_POST['vesselID'] ?? 0);
        $date = $_POST['date'] . ' ' . date('H:i:s');
        $allowance = isset($_POST['allowance']) ? floatval($_POST['allowance']) : null;
        $evidencePath = null;

        // Handle file upload
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] == UPLOAD_ERR_OK) {
            $targetDir = "../uploads/evidence/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'xlsx', 'xls', 'doc', 'docx', 'eml', 'msg'];
            if (in_array($ext, $allowed)) {
                $newName = uniqid('evidence_') . '.' . $ext;
                if (move_uploaded_file($_FILES['evidence']['tmp_name'], $targetDir . $newName)) {
                    $evidencePath = $newName; // Store only the filename, not the full path
                }
            }
        }
        
        $conn->begin_transaction();
        // Insert special project
        $spInsert = $conn->prepare("INSERT INTO specialproject (name, vesselID, date, allowance, evidence) VALUES (?, ?, ?, ?, ?)");
        $spInsert->bind_param("sisds", $name, $vesselID, $date, $allowance, $evidencePath);
        

        
        $spInsert->execute();
        $spProjectID = $conn->insert_id;
        $spInsert->close();

        // Link to job
        $spLink = $conn->prepare("INSERT INTO jobspecialprojects (spProjectID, jobID) VALUES (?, ?)");
        $spLink->bind_param("ii", $spProjectID, $jobID);
        $spLink->execute();
        $spLink->close();

        $conn->commit();
        $_SESSION['success_message'] = "Special project added successfully.";
        header("Location: ../views/approvejobs.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: ../views/approvejobs.php");
        exit();
    }
} else {
    header("Location: ../views/approvejobs.php");
    exit();
} 