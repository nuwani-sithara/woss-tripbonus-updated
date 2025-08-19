<?php
include '../config/dbConnect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $spProjectID = (int)$_POST['spProjectID'];
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $vesselID = (int)($_POST['vesselID'] ?? 0);
        $date = $conn->real_escape_string($_POST['date'] ?? '');
        $allowance = isset($_POST['allowance']) ? floatval($_POST['allowance']) : null;
        $evidencePath = null;

        // Get current evidence path
        $current = $conn->query("SELECT evidence FROM specialproject WHERE spProjectID = $spProjectID")->fetch_assoc();
        $currentEvidence = $current ? $current['evidence'] : null;

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
                    // Optionally delete old file
                    if ($currentEvidence && file_exists("../uploads/evidence/" . $currentEvidence)) {
                        @unlink("../uploads/evidence/" . $currentEvidence);
                    }
                }
            }
        } else {
            $evidencePath = $currentEvidence;
        }

        $update = $conn->prepare("UPDATE specialproject SET name=?, vesselID=?, date=?, evidence=?, allowance=? WHERE spProjectID=?");
        $update->bind_param("sisdsi", $name, $vesselID, $date, $evidencePath, $allowance, $spProjectID);
        $update->execute();
        $update->close();

        $_SESSION['success_message'] = "Special project updated successfully.";
        header("Location: ../views/approvejobs.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: ../views/approvejobs.php");
        exit();
    }
} else {
    header("Location: ../views/approvejobs.php");
    exit();
} 