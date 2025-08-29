<?php
include '../config/dbConnect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_job'])) {
    try {
        $startDate = $_POST['startDate'] . ' ' . date('H:i:s');
        $comment = $_POST['comment'] ?? '';
        $jobTypeID = (int)$_POST['jobTypeID'];
        $vesselID = !empty($_POST['vesselID']) ? (int)$_POST['vesselID'] : null;
        $boatID = !empty($_POST['boatID']) ? (int)$_POST['boatID'] : null;
        $portID = !empty($_POST['portID']) ? (int)$_POST['portID'] : null;
        $isSpecialProject = isset($_POST['isSpecialProject']) && $_POST['isSpecialProject'] == '1';
        $userID = $_SESSION['userID'];

        // For General job type, create or get a "General" vessel
        if ($jobTypeID == 6 && $vesselID === null) {
            $generalVesselCheck = $conn->query("SELECT vesselID FROM vessels WHERE vessel_name = 'General'");
            if ($generalVesselCheck->num_rows > 0) {
                $generalVessel = $generalVesselCheck->fetch_assoc();
                $vesselID = $generalVessel['vesselID'];
            } else {
                $conn->query("INSERT INTO vessels (vessel_name) VALUES ('General')");
                $vesselID = $conn->insert_id;
            }
        }

        // Start transaction
        $conn->begin_transaction();

        // Insert job (trigger will automatically generate jobNumber and jobkey)
        $jobInsert = $conn->prepare("INSERT INTO jobs (start_date, comment, jobtypeID, vesselID, jobCreatedBy, boatID) VALUES (?, ?, ?, ?, ?, ?)");
        $jobInsert->bind_param("ssiiii", $startDate, $comment, $jobTypeID, $vesselID, $userID, $boatID);
        $jobInsert->execute();
        $jobID = $conn->insert_id;
        $jobInsert->close();

        // Automatically create the first trip
        $tripInsert = $conn->prepare("INSERT INTO trips (jobID, trip_date) VALUES (?, ?)");
        $tripInsert->bind_param("is", $jobID, $startDate);
        $tripInsert->execute();
        $tripInsert->close();

        // Assign port
        if ($portID !== null) {
            $portAssign = $conn->prepare("INSERT INTO portassignments (portID, jobID) VALUES (?, ?)");
            $portAssign->bind_param("ii", $portID, $jobID);
            $portAssign->execute();
            $portAssign->close();
        }

        // Handle special project
        if ($isSpecialProject) {
            $name = $conn->real_escape_string($_POST['name'] ?? '');
            $vessel = $conn->real_escape_string($_POST['vessel'] ?? '');
            $date = $conn->real_escape_string($_POST['date'] ?? '');
            $evidencePath = null;

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
                        $evidencePath = $targetDir . $newName;
                    }
                }
            }

            $spInsert = $conn->prepare("INSERT INTO specialproject (name, vessel, date, evidence) VALUES (?, ?, ?, ?)");
            $spInsert->bind_param("ssss", $name, $vessel, $date, $evidencePath);
            $spInsert->execute();
            $spProjectID = $conn->insert_id;
            $spInsert->close();

            $spLink = $conn->prepare("INSERT INTO jobspecialprojects (spProjectID, jobID) VALUES (?, ?)");
            $spLink->bind_param("ii", $spProjectID, $jobID);
            $spLink->execute();
            $spLink->close();
        }

        // Commit transaction
        $conn->commit();

        // Redirect to manage job days
        header("Location: ../views/managejobdays.php?jobID=$jobID");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}