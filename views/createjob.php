<?php
session_start();
include '../config/dbConnect.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has role_id = 1
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get the next job number from the sequence table
$nextJobNumber = 1000; // Default fallback
$sequenceQuery = mysqli_query($conn, "SELECT last_job_number + 1 as next_num FROM job_sequence WHERE id = 1");
if ($sequenceQuery && $sequenceResult = mysqli_fetch_assoc($sequenceQuery)) {
    $nextJobNumber = $sequenceResult['next_num'];
}

$vessel_result = mysqli_query($conn, "SELECT vesselID, vessel_name FROM vessels");
$boat_result = mysqli_query($conn, "SELECT boatID, boat_name FROM boats");
$port_result = mysqli_query($conn, "SELECT portID, portname FROM ports");
$jobType_result = mysqli_query($conn, "SELECT jobtypeID, type_name FROM jobtype");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Create New Job - SubseaOps</title>
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
        .compact-form .form-section {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 3px solid #2c7be5;
        }
        .compact-form .form-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c7be5;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        .compact-form .form-section-title i {
            margin-right: 8px;
            font-size: 16px;
        }
        .compact-form .card {
            box-shadow: 0 2px 12px 0 rgba(0,0,0,.06);
            border: none;
            border-radius: 8px;
        }
        .compact-form .card-header {
            padding: 15px 20px;
        }
        .compact-form .card-title {
            font-weight: 600;
            color: #1e2a35;
            font-size: 16px;
            margin: 0;
        }
        .compact-form .card-body {
            padding: 20px;
        }
        .compact-form .card-action {
            padding: 15px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .compact-form .form-group {
            margin-bottom: 15px;
        }
        .compact-form label {
            font-weight: 500;
            margin-bottom: 6px;
            color: #344050;
            font-size: 13px;
        }
        .compact-form .form-control {
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 14px;
            height: calc(1.8em + 0.75rem + 2px);
        }
        .compact-form .job-key-preview {
            background-color: #edf2f9;
            padding: 8px 12px;
            border-radius: 5px;
            margin-top: 5px;
            font-weight: 500;
            font-size: 13px;
        }
        .compact-form .required-field::after {
            content: "*";
            color: #e63757;
            margin-left: 3px;
        }
        .compact-form .form-text {
            font-size: 12px;
            margin-top: 4px;
        }
        .compact-form .btn {
            padding: 8px 16px;
            font-size: 14px;
        }
        .compact-form .page-header {
            margin-bottom: 20px;
        }
        .compact-form .page-inner {
            padding-top: 15px;
        }
        .page-header h3 {
            margin-bottom: 1rem;
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
                                src="../assets/img/app-logo1.png"
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
                        <h3 class="fw-bold mb-2">Job Creation</h3>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card compact-form">
                                <div class="card-header">
                                    <div class="card-title">Job Information</div>
                                </div>
                                <form method="POST" action="../controllers/createJobController.php" class="space-y-6" enctype="multipart/form-data">
                                    <div class="card-body">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class="icon-calendar"></i> Job Details
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="startDate" class="required-field">Start Date</label>
                                                        <input type="date" class="form-control" id="startDate" name="startDate" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="jobType" class="required-field">Job Type</label>
                                                        <select class="form-control" id="jobType" name="jobTypeID" required>
                                                            <option value="">Select Job Type</option>
                                                            <?php 
                                                            mysqli_data_seek($jobType_result, 0);
                                                            while ($jobType = mysqli_fetch_assoc($jobType_result)) { ?>
                                                                <option value="<?php echo $jobType['jobtypeID']; ?>"><?php echo $jobType['type_name']; ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="jobNumberPreview">Job Number Preview</label>
                                                        <input type="text" class="form-control" id="jobNumberPreview" value="<?php echo $nextJobNumber; ?>" readonly>
                                                        <div class="form-text">Job Key Preview: <span id="jobKeyPreview" class="job-key-preview">WOSS -<?php echo $nextJobNumber; ?> [boat name]</span></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="comment">Comments</label>
                                                        <textarea class="form-control" id="comment" name="comment" rows="2" placeholder="Additional notes about this job"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class="icon-anchor"></i> Vessel & Boat Information
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6" id="vesselNameGroup">
                                                    <div class="form-group">
                                                        <label for="vesselName" class="required-field">Vessel Name</label>
                                                        <select class="form-control" id="vesselName" name="vesselID" required>
                                                            <option value="">Select Vessel</option>
                                                            <?php 
                                                            mysqli_data_seek($vessel_result, 0);
                                                            while ($vessel = mysqli_fetch_assoc($vessel_result)) { ?>
                                                                <option value="<?php echo $vessel['vesselID']; ?>"><?php echo $vessel['vessel_name']; ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6" id="boatNameGroup">
                                                    <div class="form-group">
                                                        <label for="boatName" class="required-field">Boat Name</label>
                                                        <select class="form-control" id="boatName" name="boatID" required>
                                                            <option value="">Select Boat</option>
                                                            <?php 
                                                            mysqli_data_seek($boat_result, 0);
                                                            while ($boat = mysqli_fetch_assoc($boat_result)) { ?>
                                                                <option value="<?php echo $boat['boatID']; ?>"><?php echo $boat['boat_name']; ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class="icon-location-pin"></i> Port Information
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6" id="portNameGroup">
                                                    <div class="form-group">
                                                        <label for="portName" class="required-field">Port Name</label>
                                                        <select class="form-control" id="portName" name="portID" required>
                                                            <option value="">Select Port</option>
                                                            <?php 
                                                            mysqli_data_seek($port_result, 0);
                                                            while ($port = mysqli_fetch_assoc($port_result)) { ?>
                                                                <option value="<?php echo $port['portID']; ?>"><?php echo $port['portname']; ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-action">
                                        <button class="btn btn-success" name="create_job" type="submit">
                                            <i class="icon-check"></i> Create Job
                                        </button>
                                        <a href="jobs.php" class="btn btn-outline-danger">
                                            <i class="icon-close"></i> Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Handle job type change to hide/show fields for General job type
            $('#jobType').change(function() {
                var selectedJobType = $(this).val();
                
                if (selectedJobType == '6') {
                    $('#vesselNameGroup').hide();
                    $('#boatNameGroup').hide();
                    $('#portNameGroup').hide();
                    
                    $('#vesselName').removeAttr('required');
                    $('#boatName').removeAttr('required');
                    $('#portName').removeAttr('required');
                } else {
                    $('#vesselNameGroup').show();
                    $('#boatNameGroup').show();
                    $('#portNameGroup').show();
                    
                    $('#vesselName').attr('required', 'required');
                    $('#boatName').attr('required', 'required');
                    $('#portName').attr('required', 'required');
                }
                
                updateJobKeyPreview();
            });

            function updateJobKeyPreview() {
                var jobNumber = $('#jobNumberPreview').val();
                var boatName = $('#boatName option:selected').text();
                var selectedJobType = $('#jobType').val();
                
                if (selectedJobType == '6') {
                    $('#jobKeyPreview').text('WOSS -' + jobNumber);
                } else if (jobNumber && boatName && boatName !== 'Select Boat') {
                    $('#jobKeyPreview').text('WOSS -' + jobNumber + ' ' + boatName);
                } else {
                    $('#jobKeyPreview').text('WOSS -' + jobNumber + ' [boat name]');
                }
            }
            
            $('#boatName').on('change', updateJobKeyPreview);
            
            // Initialize the job key preview on page load
            updateJobKeyPreview();
        });
    </script>
</body>
</html>