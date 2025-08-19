<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include '../config/dbConnect.php';
error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => '', 'jobID' => null];

// Allow both JSON body and multipart form-data
$input = [];
if (strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    $input = json_decode(file_get_contents("php://input"), true);
} else {
    $input = $_POST;
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        throw new Exception("Invalid request method. Use POST.");
    }

    // Get values
    $startDate = ($input['startDate'] ?? date('Y-m-d')) . ' ' . date('H:i:s');
    $comment = $input['comment'] ?? '';
    $jobTypeID = (int)($input['jobTypeID'] ?? 0);
    $vesselID = !empty($input['vesselID']) ? (int)$input['vesselID'] : null;
    $boatID = !empty($input['boatID']) ? (int)$input['boatID'] : null;
    $portID = !empty($input['portID']) ? (int)$input['portID'] : null;
    $isSpecialProject = isset($input['isSpecialProject']) && $input['isSpecialProject'] == '1';
    $userID = (int)($input['userID'] ?? 0); // Pass userID in API call

    if ($jobTypeID === 0 || $userID === 0) {
        http_response_code(400);
        throw new Exception("Missing required fields: jobTypeID, userID.");
    }

    // General job type vessel handling
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

    // Insert job
    $jobInsert = $conn->prepare("
        INSERT INTO jobs (start_date, comment, jobtypeID, vesselID, jobCreatedBy)
        VALUES (?, ?, ?, ?, ?)
    ");
    $jobInsert->bind_param("ssiii", $startDate, $comment, $jobTypeID, $vesselID, $userID);
    $jobInsert->execute();
    $jobID = $conn->insert_id;
    $jobInsert->close();

    // Insert first trip
    $tripInsert = $conn->prepare("INSERT INTO trips (jobID, trip_date) VALUES (?, ?)");
    $tripInsert->bind_param("is", $jobID, $startDate);
    $tripInsert->execute();
    $tripInsert->close();

    // Port assignment
    if ($portID !== null) {
        $portAssign = $conn->prepare("INSERT INTO portassignments (portID, jobID) VALUES (?, ?)");
        $portAssign->bind_param("ii", $portID, $jobID);
        $portAssign->execute();
        $portAssign->close();
    }

    // Boat assignment
    if ($boatID !== null) {
        $boatAssign = $conn->prepare("INSERT INTO boatassignments (boatID, jobID) VALUES (?, ?)");
        $boatAssign->bind_param("ii", $boatID, $jobID);
        $boatAssign->execute();
        $boatAssign->close();
    }

    // Special Project
    if ($isSpecialProject) {
        $name = $conn->real_escape_string($input['name'] ?? '');
        $vessel = $conn->real_escape_string($input['vessel'] ?? '');
        $date = $conn->real_escape_string($input['date'] ?? '');
        $evidencePath = null;

        // File upload handling
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
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

    // Commit
    $conn->commit();

    $response['success'] = true;
    $response['message'] = 'Job created successfully';
    $response['jobID'] = $jobID;
    http_response_code(201);

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

$conn->close();
echo json_encode($response);
