<?php
header("Content-Type: application/json");
include '../config/dbConnect.php';

try {
    // ✅ Require userID in query param
    if (!isset($_GET['userID']) || empty($_GET['userID'])) {
        echo json_encode(["success" => false, "error" => "userID is required"]);
        exit();
    }
    $userID = (int)$_GET['userID'];

    // ✅ Decode input JSON body
    $input = json_decode(file_get_contents("php://input"), true);

    $startDate = ($input['startDate'] ?? date("Y-m-d")) . ' ' . date('H:i:s');
    $comment   = $input['comment'] ?? '';
    $jobTypeID = (int)($input['jobTypeID'] ?? 0);
    $vesselID  = !empty($input['vesselID']) ? (int)$input['vesselID'] : null;
    $boatID    = !empty($input['boatID']) ? (int)$input['boatID'] : null;
    $portID    = !empty($input['portID']) ? (int)$input['portID'] : null;
    $isSpecialProject = isset($input['isSpecialProject']) && $input['isSpecialProject'] == 1;

    if ($jobTypeID <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid job type"]);
        exit();
    }

    // ✅ For General jobs, auto-create/find "General" vessel
    if ($jobTypeID == 6 && $vesselID === null) {
        $generalVesselCheck = $conn->query("SELECT vesselID FROM vessels WHERE vessel_name = 'General'");
        if ($generalVesselCheck->num_rows > 0) {
            $vesselID = $generalVesselCheck->fetch_assoc()['vesselID'];
        } else {
            $conn->query("INSERT INTO vessels (vessel_name) VALUES ('General')");
            $vesselID = $conn->insert_id;
        }
    }

    // ✅ Start transaction
    $conn->begin_transaction();

    // Insert job
    $jobInsert = $conn->prepare("
        INSERT INTO jobs (start_date, comment, jobtypeID, vesselID, jobCreatedBy) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $jobInsert->bind_param("ssiii", $startDate, $comment, $jobTypeID, $vesselID, $userID);
    $jobInsert->execute();
    $jobID = $conn->insert_id;
    $jobInsert->close();

    // Get job number
    $jobResult = $conn->query("SELECT jobNumber FROM jobs WHERE jobID = $jobID");
    $jobData = $jobResult->fetch_assoc();
    $jobNumber = $jobData['jobNumber'];

    // Get boat name (if exists)
    $boatName = '';
    if ($boatID !== null) {
        $boatResult = $conn->query("SELECT boat_name FROM boats WHERE boatID = $boatID");
        if ($boatResult->num_rows > 0) {
            $boatName = $boatResult->fetch_assoc()['boat_name'];
        }
    }

    // Generate job key
    if ($jobTypeID == 6) {
        $jobKey = "WOSS -" . $jobNumber;
    } else if (!empty($boatName)) {
        $jobKey = "WOSS -" . $jobNumber . " " . $boatName;
    } else {
        $jobKey = "WOSS -" . $jobNumber . " [Unknown Boat]";
    }

    // Update jobkey
    $updateJobKey = $conn->prepare("UPDATE jobs SET jobkey = ? WHERE jobID = ?");
    $updateJobKey->bind_param("si", $jobKey, $jobID);
    $updateJobKey->execute();
    $updateJobKey->close();

    // Create first trip
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

    // Assign boat
    if ($boatID !== null) {
        $boatAssign = $conn->prepare("INSERT INTO boatassignments (boatID, jobID) VALUES (?, ?)");
        $boatAssign->bind_param("ii", $boatID, $jobID);
        $boatAssign->execute();
        $boatAssign->close();
    }

    // Handle special project
    if ($isSpecialProject) {
        $name   = $conn->real_escape_string($input['name'] ?? '');
        $vessel = $conn->real_escape_string($input['vessel'] ?? '');
        $date   = $conn->real_escape_string($input['date'] ?? '');
        $evidencePath = null;

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

    echo json_encode([
        "success" => true,
        "jobID" => $jobID,
        "jobKey" => $jobKey,
        "jobNumber" => $jobNumber
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
