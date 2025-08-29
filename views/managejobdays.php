<?php
session_start();
include '../config/dbConnect.php';

// Check if user is logged in and has role_id = 1
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

$jobID = $_GET['jobID'] ?? 0;
if ($jobID <= 0) {
    die("Invalid Job ID");
}

// Get job details
$jobQuery = "SELECT j.*, v.vessel_name, jt.type_name, b.boat_name, p.portname 
             FROM jobs j
             LEFT JOIN vessels v ON j.vesselID = v.vesselID
             LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
             LEFT JOIN boatassignments ba ON j.jobID = ba.jobID
             LEFT JOIN boats b ON ba.boatID = b.boatID
             LEFT JOIN portassignments pa ON j.jobID = pa.jobID
             LEFT JOIN ports p ON pa.portID = p.portID
             WHERE j.jobID = $jobID";
$jobResult = mysqli_query($conn, $jobQuery);
$job = mysqli_fetch_assoc($jobResult);

// Check if job is editable based on approval stage and end date
// A job is editable if:
// 1. It's not in approvals table with approval_stage = 'job_approval', OR
// 2. The end_date is NULL or empty (incomplete jobs can still be edited even if approved)
$isJobEditable = true;

// Check approval status
$approvalCheck = mysqli_query($conn, "SELECT approval_stage FROM approvals WHERE jobID = $jobID");
$isApproved = false;
if ($approvalCheck && mysqli_num_rows($approvalCheck) > 0) {
    $approvalRow = mysqli_fetch_assoc($approvalCheck);
    $isApproved = ($approvalRow['approval_stage'] === 'job_approval');
}

// Check end date
$endDate = $job['end_date'];

// Job is editable if it's not approved with job_approval stage OR if end_date is empty
// This allows editing of incomplete jobs even if they are approved
if ($isApproved && !empty($endDate)) {
    $isJobEditable = false;
}

// Get existing trips for this job
$tripsQuery = "SELECT t.*, COUNT(ja.empID) as employee_count 
               FROM trips t
               LEFT JOIN jobassignments ja ON t.tripID = ja.tripID
               WHERE t.jobID = $jobID
               GROUP BY t.tripID
               ORDER BY t.trip_date";
$tripsResult = mysqli_query($conn, $tripsQuery);

// Fetch all trips as array for easier handling
$trips = [];
if ($tripsResult) {
    while ($row = mysqli_fetch_assoc($tripsResult)) {
        $trips[] = $row;
    }
}

// For first trip assignment, fetch standby and other divers like assignEmployees.php
$firstTripID = $trips[0]['tripID'] ?? null;
$standby_attendanceID = null;
$diver_list = [];
$all_divers = [];
$assignedDivers = [];
if ($firstTripID) {
    // Get the most recent standby attendance record
    $diverQuery = "
        SELECT DISTINCT u.userID, u.fname, u.lname
        FROM standbyassignments sa
        JOIN employees e ON sa.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN standby_attendance sta ON sa.standby_attendanceID = sta.standby_attendanceID
        WHERE u.roleID IN (2, 8, 9)
        AND sa.status = 1
    ";
    $diverResult = mysqli_query($conn, $diverQuery);
    while ($diver = mysqli_fetch_assoc($diverResult)) {
        $diver_list[] = $diver;
    }

    // Still get the latest standby_attendanceID for the form
    $standbyQuery = "SELECT standby_attendanceID FROM standby_attendance ORDER BY date DESC LIMIT 1";
    $standbyResult = mysqli_query($conn, $standbyQuery);
    $standbyRow = mysqli_fetch_assoc($standbyResult);
    $standby_attendanceID = $standbyRow['standby_attendanceID'] ?? null;
    // Get other divers (not in standby)
    $allDiversQuery = "SELECT u.userID, u.fname, u.lname 
                   FROM users u 
                   WHERE u.roleID IN (2, 8, 9) 
                   AND u.userID NOT IN (
                       SELECT DISTINCT u.userID
                       FROM standbyassignments sa
                       JOIN employees e ON sa.empID = e.empID
                       JOIN users u ON e.userID = u.userID
                       WHERE sa.status = 1
                         AND u.roleID IN (2, 8, 9)
                   )";
    $allDiversResult = mysqli_query($conn, $allDiversQuery);
    while ($diver = mysqli_fetch_assoc($allDiversResult)) {
        $all_divers[] = $diver;
    }
    // Get currently assigned divers for this trip
    $assignedQuery = "SELECT u.userID, u.fname, u.lname 
                      FROM jobassignments ja
                      JOIN employees e ON ja.empID = e.empID
                      JOIN users u ON e.userID = u.userID
                      WHERE ja.tripID = $firstTripID";
    $assignedResult = mysqli_query($conn, $assignedQuery);
    while ($diver = mysqli_fetch_assoc($assignedResult)) {
        $assignedDivers[$diver['userID']] = true;
    }
}

// Fetch employee list for assignment UI
include_once __DIR__ . '/../controllers/getEmployeesController.php';
$employees = getEmployees();

// Only show the assignment form if there is exactly one trip and it has no employees assigned
$showFirstDayAssignForm = false;
if (count($trips) === 1 && isset($trips[0]['employee_count']) && intval($trips[0]['employee_count']) === 0) {
    $showFirstDayAssignForm = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Manage Job Days - SubseaOps</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../assets/img/app-logo1.png" type="image/x-icon" />
    <!-- Fonts and icons -->
    <script src="../assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
      WebFont.load({
        google: { families: ["Public Sans:300,400,500,600,700"] },
        custom: {
          families: [
            "Font Awesome 5 Solid",
            "Font Awesome 5 Regular",
            "Font Awesome 5 Brands",
            "simple-line-icons",
          ],
          urls: ["../assets/css/fonts.min.css"],
        },
        active: function () {
          sessionStorage.fonts = true;
        },
      });
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/plugins.min.css" />
    <link rel="stylesheet" href="../assets/css/kaiadmin.min.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <style>
        .section-header {
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
        }
        .card-action-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-inline-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-inline-group .form-control {
            flex: 1;
        }
        .danger-section {
            border-left: 4px solid #f5365c;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'components/sidebar.php'; ?>

        <div class="main-panel">
        <div class="main-header">
                <div class="main-header-logo">
                    <!-- Logo Header -->
                    <div class="logo-header" data-background-color="dark">
                        <a href="../index.html" class="logo">
                            <img
                                src="../assets/img/app-logo.png"
                                alt="navbar brand"
                                class="navbar-brand"
                                height="20"
                            />
                        </a>
                        <div class="nav-toggle">
                            <button class="btn btn-toggle toggle-sidebar">
                                <i class="gg-menu-right"></i>
                            </button>
                            <button class="btn btn-toggle sidenav-toggler">
                                <i class="gg-menu-left"></i>
                            </button>
                        </div>
                        <button class="topbar-toggler more">
                            <i class="gg-more-vertical-alt"></i>
                        </button>
                    </div>
                    <!-- End Logo Header -->
                </div>
                <?php include 'components/navbar.php'; ?>
            </div>
            
            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Manage Job Days - <?php echo $job['vessel_name']; ?></h3>
                    </div>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success']; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['error']; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <?php if (!$isJobEditable): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> This job has been approved or rejected by the operation manager. You can view the details but cannot make changes or delete trips.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Job Details Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">Job Details</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Start Date:</strong> <?php echo $job['start_date']; ?></p>
                                    <p><strong>Vessel:</strong> <?php echo $job['vessel_name']; ?></p>
                                    <p><strong>Job Type:</strong> <?php echo $job['type_name']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Boat:</strong> <?php echo $job['boat_name']; ?></p>
                                    <p><strong>Port:</strong> <?php echo $job['portname']; ?></p>
                                    <p><strong>Comments:</strong> <?php echo $job['comment']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($showFirstDayAssignForm): ?>
                    <!-- Assign Employees to First Day Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">Assign Employees to First Day (<?php echo htmlspecialchars($trips[0]['trip_date']); ?>)</h4>
                        </div>
                        <form method="POST" action="../controllers/assignEmployeesController.php">
                            <input type="hidden" name="tripID" value="<?php echo $trips[0]['tripID']; ?>">
                            <input type="hidden" name="standby_attendanceID" value="<?php echo $standby_attendanceID; ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Standby Divers</label>
                                            <div class="selectgroup selectgroup-pills">
                                                <?php foreach ($diver_list as $diver): ?>
                                                    <label class="selectgroup-item">
                                                        <input type="checkbox" name="divers[]" value="<?php echo $diver['userID']; ?>" class="selectgroup-input" <?php echo isset($assignedDivers[$diver['userID']]) ? 'checked' : ''; ?>>
                                                        <span class="selectgroup-button"><?php echo htmlspecialchars($diver['fname'] . ' ' . $diver['lname']); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Other Divers</label>
                                            <div class="selectgroup selectgroup-pills">
                                                <?php foreach ($all_divers as $diver): ?>
                                                    <label class="selectgroup-item">
                                                        <input type="checkbox" name="otherDivers[]" value="<?php echo $diver['userID']; ?>" class="selectgroup-input" <?php echo isset($assignedDivers[$diver['userID']]) ? 'checked' : ''; ?>>
                                                        <span class="selectgroup-button"><?php echo htmlspecialchars($diver['fname'] . ' ' . $diver['lname']); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-success">Assign Employees</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Existing Days Table Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">Existing Days</h4>
                        </div>
                        <div class="card-body">
                            <?php if (count($trips) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Employees</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($trips as $trip): ?>
                                                <tr>
                                                    <td><?php echo date('d-m-Y', strtotime($trip['trip_date'])); ?></td>
                                                    <td><?php echo $trip['employee_count']; ?> employees</td>
                                                    <td>
                                                        <a href="assignemployees.php?tripID=<?php echo $trip['tripID']; ?>" class="btn btn-sm btn-info">Manage Employees</a>
                                                        <?php if ($isJobEditable): ?>
                                                        <button class="btn btn-sm btn-danger ml-1" data-toggle="modal" data-target="#deleteTripModal" 
                                                            data-tripid="<?php echo $trip['tripID']; ?>"
                                                            data-jobid="<?php echo $jobID; ?>"
                                                            data-tripdate="<?php echo $trip['trip_date']; ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No days added yet for this job.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($isJobEditable): ?>
                    <!-- Add New Day and Close Job Sections (Side by Side) -->
                    <div class="row">
                        <!-- Add New Day Section -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Additional Day</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="../controllers/addTripController.php">
                                        <input type="hidden" name="jobID" value="<?php echo $jobID; ?>">
                                        <div class="form-inline-group">
                                            <input type="date" class="form-control" id="tripDate" name="tripDate" required>
                                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Day</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Close Job Section -->
                        <div class="col-md-6">
                            <?php if (empty($job['end_date'])): ?>
                            <div class="card danger-section">
                                <div class="card-header bg-danger text-white">
                                    <h4 class="card-title">Close Job</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="../controllers/closeJobController.php">
                                        <input type="hidden" name="jobID" value="<?php echo $jobID; ?>">
                                        <div class="form-inline-group">
                                            <input type="date" class="form-control" id="endDate" name="endDate" required>
                                            <button type="submit" class="btn btn-danger">Close Job</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success">
                                This job was closed on <?php echo $job['end_date']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <a href="supervisoreditjobs.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Jobs
                    </a>
                </div>
            </div>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>
    
    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    
    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>
    
    <!-- Delete Trip Modal -->
    <div class="modal fade" id="deleteTripModal" tabindex="-1" role="dialog" aria-labelledby="deleteTripModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="../controllers/deleteTripController.php">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteTripModalLabel">Delete Trip</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="delete_trip" value="1">
                        <input type="hidden" id="delete_tripID" name="tripID" value="">
                        <input type="hidden" id="delete_jobID" name="jobID" value="">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone. Deleting this trip will also delete:
                            <ul class="mt-2 mb-0">
                                <li>All employee assignments for this trip</li>
                                <li>All attendance records for this trip</li>
                            </ul>
                        </div>
                        <p>Are you sure you want to delete the trip on "<span id="delete_trip_date"></span>"?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Trip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!--   Core JS Files   -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Chart JS -->
    <script src="../assets/js/plugin/chart.js/chart.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

    <!-- Chart Circle -->
    <script src="../assets/js/plugin/chart-circle/circles.min.js"></script>

    <!-- Datatables -->
    <script src="../assets/js/plugin/datatables/datatables.min.js"></script>

    <!-- Bootstrap Notify -->
    <script src="../assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

    <!-- jQuery Vector Maps -->
    <script src="../assets/js/plugin/jsvectormap/jsvectormap.min.js"></script>
    <script src="../assets/js/plugin/jsvectormap/world.js"></script>

    <!-- Sweet Alert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="../assets/js/kaiadmin.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Handle delete trip modal
        $(document).on('click', '[data-target="#deleteTripModal"]', function() {
            var tripID = $(this).data('tripid');
            var jobID = $(this).data('jobid');
            var tripDate = $(this).data('tripdate');
            $('#delete_tripID').val(tripID);
            $('#delete_jobID').val(jobID);
            $('#delete_trip_date').text(tripDate);
            $('#deleteTripModal').modal('show');
        });
    });
    </script>
</body>
</html>