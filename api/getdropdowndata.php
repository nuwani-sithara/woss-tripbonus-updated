<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include '../config/dbConnect.php';

$response = [
    "vessels" => [],
    "boats" => [],
    "ports" => [],
    "jobTypes" => []
];

try {
    // Fetch vessels
    $vessel_result = mysqli_query($conn, "SELECT vesselID, vessel_name FROM vessels");
    if ($vessel_result) {
        while ($row = mysqli_fetch_assoc($vessel_result)) {
            $response["vessels"][] = $row;
        }
    }

    // Fetch boats
    $boat_result = mysqli_query($conn, "SELECT boatID, boat_name FROM boats");
    if ($boat_result) {
        while ($row = mysqli_fetch_assoc($boat_result)) {
            $response["boats"][] = $row;
        }
    }

    // Fetch ports
    $port_result = mysqli_query($conn, "SELECT portID, portname FROM ports");
    if ($port_result) {
        while ($row = mysqli_fetch_assoc($port_result)) {
            $response["ports"][] = $row;
        }
    }

    // Fetch job types
    $jobType_result = mysqli_query($conn, "SELECT jobtypeID, type_name FROM jobtype");
    if ($jobType_result) {
        while ($row = mysqli_fetch_assoc($jobType_result)) {
            $response["jobTypes"][] = $row;
        }
    }

    echo json_encode([
        "status" => "success",
        "data" => $response
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
