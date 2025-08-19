<?php
// Enable strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$response = [
    'success' => false,
    'message' => '',
    'summary' => [],
    'pendingJobs' => []
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method. Only POST requests are allowed.");
    }

    $month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 12]
    ]);
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 2020, 'max_range' => date('Y')]
    ]);
    if (!$month || !$year) {
        throw new Exception("Invalid month or year parameters. Month must be 1-12 and year must be 2020-current year.");
    }

    require_once '../config/dbConnect.php';
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // 1. Get all jobs for the month/year with trip information
    $jobs = [];
    $sql = "SELECT j.jobID, j.jobtypeID, j.start_date, t.tripID, t.trip_date, ja.empID, CONCAT(IFNULL(u.fname,''), ' ', IFNULL(u.lname,'')) AS empName
            FROM jobs j
            LEFT JOIN trips t ON j.jobID = t.jobID
            LEFT JOIN jobassignments ja ON t.tripID = ja.tripID
            LEFT JOIN employees e ON ja.empID = e.empID
            LEFT JOIN users u ON e.userID = u.userID
            WHERE MONTH(j.start_date) = ? AND YEAR(j.start_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    $stmt->close();

    // Get trip counts per job
    $jobTripCounts = [];
    $sql = "SELECT j.jobID, COUNT(t.tripID) as trip_count
            FROM jobs j
            LEFT JOIN trips t ON j.jobID = t.jobID
            WHERE MONTH(j.start_date) = ? AND YEAR(j.start_date) = ?
            GROUP BY j.jobID";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobTripCounts[$row['jobID']] = $row['trip_count'];
    }
    $stmt->close();

    // Fetch all job type names
    $jobTypeNames = [];
    $result = $conn->query("SELECT jobtypeID, type_name FROM jobtype");
    while ($row = $result->fetch_assoc()) {
        $jobTypeNames[$row['jobtypeID']] = $row['type_name'];
    }

    // --- NEW: Job type wise job counts ---
    $jobTypeJobIDs = [];
    foreach ($jobs as $job) {
        if ($job['jobtypeID'] && $job['jobID']) {
            $jtID = $job['jobtypeID'];
            $jobTypeJobIDs[$jtID][$job['jobID']] = true;
        }
    }
    $jobTypeCountsDisplay = [];
    foreach ($jobTypeJobIDs as $jtID => $jobIDs) {
        $jobTypeCountsDisplay[] = [
            'jobtypeID' => $jtID,
            'type_name' => isset($jobTypeNames[$jtID]) ? $jobTypeNames[$jtID] : 'Unknown',
            'count' => count($jobIDs)
        ];
    }
    // --- END NEW ---

    // 2. Get approval status for all jobs in this month/year
    $jobApprovals = [];
    $sql = "SELECT a.jobID, a.approval_status 
            FROM approvals a 
            JOIN jobs j ON a.jobID = j.jobID 
            WHERE MONTH(j.start_date) = ? AND YEAR(j.start_date) = ?
            AND a.approval_stage = 'job_approval'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobApprovals[$row['jobID']] = $row['approval_status'];
    }
    $stmt->close();

    // --- NEW: Approved and Rejected jobs with trip information ---
    $approvedJobs = [];
    $rejectedJobs = [];
    $jobTrips = []; // Store trip information for each job
    
    foreach ($jobs as $job) {
        $approvalStatus = isset($jobApprovals[$job['jobID']]) ? $jobApprovals[$job['jobID']] : null;
        
        // Initialize job display if not exists
        if (!isset($approvedJobs[$job['jobID']]) && !isset($rejectedJobs[$job['jobID']])) {
            $jobDisplay = [
                'jobID' => $job['jobID'],
                'jobtypeID' => $job['jobtypeID'],
                'job_type_name' => isset($jobTypeNames[$job['jobtypeID']]) ? $jobTypeNames[$job['jobtypeID']] : null,
                'start_date' => $job['start_date'],
                'trip_count' => $jobTripCounts[$job['jobID']] ?? 0,
                'employees' => [],
                'trips' => []
            ];
            
            if ($approvalStatus === 1) {
                $approvedJobs[$job['jobID']] = $jobDisplay;
            } elseif ($approvalStatus === 3) {
                $rejectedJobs[$job['jobID']] = $jobDisplay;
            }
        }
        
        // Add trip information if exists
        if ($job['tripID']) {
            $tripInfo = [
                'tripID' => $job['tripID'],
                'trip_date' => $job['trip_date'],
                'employees' => []
            ];
            
            if ($job['empID']) {
                $tripInfo['employees'][] = [
                    'empID' => $job['empID'],
                    'empName' => $job['empName']
                ];
            }
            
            // Add to appropriate job array
            if ($approvalStatus === 1 && isset($approvedJobs[$job['jobID']])) {
                // Check if trip already exists
                $tripExists = false;
                foreach ($approvedJobs[$job['jobID']]['trips'] as $existingTrip) {
                    if ($existingTrip['tripID'] == $job['tripID']) {
                        $tripExists = true;
                        // Add employee if not already in this trip
                        if ($job['empID']) {
                            $empExists = false;
                            foreach ($existingTrip['employees'] as $emp) {
                                if ($emp['empID'] == $job['empID']) {
                                    $empExists = true;
                                    break;
                                }
                            }
                            if (!$empExists) {
                                $approvedJobs[$job['jobID']]['trips'][array_search($existingTrip, $approvedJobs[$job['jobID']]['trips'])]['employees'][] = [
                                    'empID' => $job['empID'],
                                    'empName' => $job['empName']
                                ];
                            }
                        }
                        break;
                    }
                }
                if (!$tripExists) {
                    $approvedJobs[$job['jobID']]['trips'][] = $tripInfo;
                }
            } elseif ($approvalStatus === 3 && isset($rejectedJobs[$job['jobID']])) {
                // Check if trip already exists
                $tripExists = false;
                foreach ($rejectedJobs[$job['jobID']]['trips'] as $existingTrip) {
                    if ($existingTrip['tripID'] == $job['tripID']) {
                        $tripExists = true;
                        // Add employee if not already in this trip
                        if ($job['empID']) {
                            $empExists = false;
                            foreach ($existingTrip['employees'] as $emp) {
                                if ($emp['empID'] == $job['empID']) {
                                    $empExists = true;
                                    break;
                                }
                            }
                            if (!$empExists) {
                                $rejectedJobs[$job['jobID']]['trips'][array_search($existingTrip, $rejectedJobs[$job['jobID']]['trips'])]['employees'][] = [
                                    'empID' => $job['empID'],
                                    'empName' => $job['empName']
                                ];
                            }
                        }
                        break;
                    }
                }
                if (!$tripExists) {
                    $rejectedJobs[$job['jobID']]['trips'][] = $tripInfo;
                }
            }
        }
        
        // Add employee to job level if exists
        if ($job['empID']) {
            if ($approvalStatus === 1 && isset($approvedJobs[$job['jobID']])) {
                $empExists = false;
                foreach ($approvedJobs[$job['jobID']]['employees'] as $emp) {
                    if ($emp['empID'] == $job['empID']) {
                        $empExists = true;
                        break;
                    }
                }
                if (!$empExists) {
                    $approvedJobs[$job['jobID']]['employees'][] = [
                        'empID' => $job['empID'],
                        'empName' => $job['empName']
                    ];
                }
            } elseif ($approvalStatus === 3 && isset($rejectedJobs[$job['jobID']])) {
                $empExists = false;
                foreach ($rejectedJobs[$job['jobID']]['employees'] as $emp) {
                    if ($emp['empID'] == $job['empID']) {
                        $empExists = true;
                        break;
                    }
                }
                if (!$empExists) {
                    $rejectedJobs[$job['jobID']]['employees'][] = [
                        'empID' => $job['empID'],
                        'empName' => $job['empName']
                    ];
                }
            }
        }
    }
    $approvedJobs = array_values($approvedJobs);
    $rejectedJobs = array_values($rejectedJobs);
    // --- END NEW ---

    // 3. Find pending jobs (no approval, or approval_status not 1 and not 3)
    $pendingJobs = [];
    foreach ($jobs as $job) {
        $approvalStatus = isset($jobApprovals[$job['jobID']]) ? $jobApprovals[$job['jobID']] : null;
        if ($approvalStatus === null || ($approvalStatus != 1 && $approvalStatus != 3)) {
            if (!isset($pendingJobs[$job['jobID']])) {
                $pendingJobs[$job['jobID']] = [
                    'jobID' => $job['jobID'],
                    'jobtypeID' => $job['jobtypeID'],
                    'job_type_name' => isset($jobTypeNames[$job['jobtypeID']]) ? $jobTypeNames[$job['jobtypeID']] : null,
                    'start_date' => $job['start_date'],
                    'trip_count' => $jobTripCounts[$job['jobID']] ?? 0,
                    'employees' => [],
                    'trips' => []
                ];
            }
            
            // Add trip information if exists
            if ($job['tripID']) {
                $tripInfo = [
                    'tripID' => $job['tripID'],
                    'trip_date' => $job['trip_date'],
                    'employees' => []
                ];
                
                if ($job['empID']) {
                    $tripInfo['employees'][] = [
                        'empID' => $job['empID'],
                        'empName' => $job['empName']
                    ];
                }
                
                // Check if trip already exists
                $tripExists = false;
                foreach ($pendingJobs[$job['jobID']]['trips'] as $existingTrip) {
                    if ($existingTrip['tripID'] == $job['tripID']) {
                        $tripExists = true;
                        // Add employee if not already in this trip
                        if ($job['empID']) {
                            $empExists = false;
                            foreach ($existingTrip['employees'] as $emp) {
                                if ($emp['empID'] == $job['empID']) {
                                    $empExists = true;
                                    break;
                                }
                            }
                            if (!$empExists) {
                                $pendingJobs[$job['jobID']]['trips'][array_search($existingTrip, $pendingJobs[$job['jobID']]['trips'])]['employees'][] = [
                                    'empID' => $job['empID'],
                                    'empName' => $job['empName']
                                ];
                            }
                        }
                        break;
                    }
                }
                if (!$tripExists) {
                    $pendingJobs[$job['jobID']]['trips'][] = $tripInfo;
                }
            }
            
            // Add employee to job level if exists
            if ($job['empID']) {
                $empExists = false;
                foreach ($pendingJobs[$job['jobID']]['employees'] as $emp) {
                    if ($emp['empID'] == $job['empID']) {
                        $empExists = true;
                        break;
                    }
                }
                if (!$empExists) {
                    $pendingJobs[$job['jobID']]['employees'][] = [
                        'empID' => $job['empID'],
                        'empName' => $job['empName']
                    ];
                }
            }
        }
    }
    $pendingJobs = array_values($pendingJobs);

    // 4. Summary: total employees, total jobs, job types (names), employee names
    $employeeIDs = [];
    $employeeNames = [];
    $jobTypeIDs = [];
    foreach ($jobs as $job) {
        if ($job['empID']) {
            $employeeIDs[$job['empID']] = true;
            $employeeNames[$job['empID']] = $job['empName'];
        }
        if ($job['jobtypeID']) {
            $jobTypeIDs[$job['jobtypeID']] = true;
        }
    }
    $jobTypeNamesList = [];
    foreach (array_keys($jobTypeIDs) as $jtID) {
        if (isset($jobTypeNames[$jtID])) {
            $jobTypeNamesList[] = $jobTypeNames[$jtID];
        }
    }
    $summary = [
        'totalEmployees' => count($employeeIDs),
        'totalJobs' => count(array_unique(array_column($jobs, 'jobID'))),
        'jobTypes' => $jobTypeNamesList,
        'employeeNames' => array_values($employeeNames),
        // --- NEW ---
        'jobTypeCounts' => $jobTypeCountsDisplay,
        'approvedJobs' => $approvedJobs,
        'rejectedJobs' => $rejectedJobs
        // --- END NEW ---
    ];

    $response['success'] = true;
    $response['summary'] = $summary;
    $response['pendingJobs'] = $pendingJobs;
    $response['message'] = 'Summary fetched successfully.';

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
} finally {
    echo json_encode($response);
    if (isset($conn) && $conn) $conn->close();
    exit;
} 