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
        .form-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #2c7be5;
        }
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c7be5;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .form-section-title i {
            margin-right: 10px;
        }
        .card {
            box-shadow: 0 4px 24px 0 rgba(0,0,0,.08);
            border: none;
            border-radius: 10px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #edf2f9;
            padding: 20px 25px;
        }
        .card-title {
            font-weight: 600;
            color: #1e2a35;
            font-size: 18px;
        }
        .card-body {
            padding: 25px;
        }
        .card-action {
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-top: 1px solid #edf2f9;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #344050;
        }
        .form-control {
            border-radius: 6px;
            padding: 10px 15px;
            border: 1px solid #d8e2ef;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #2c7be5;
            box-shadow: 0 0 0 0.2rem rgba(44, 123, 229, 0.25);
        }
        .job-key-preview {
            background-color: #edf2f9;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 5px;
            font-weight: 500;
        }
        .required-field::after {
            content: "*";
            color: #e63757;
            margin-left: 3px;
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
                        <h3 class="fw-bold mb-3">Job Creation</h3>
                        <br/>
                        <!-- <p class="text-muted">Create a new job by filling out the form below</p> -->
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
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
                                                            // Reset pointer for job type result
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
                                                        <label for="jobNumber" class="required-field">Job Number</label>
                                                        <input type="text" class="form-control" id="jobNumber" name="jobNumber" required 
                                                            placeholder="Enter job number (e.g., 1028)">
                                                        <div class="form-text">Job Key Preview: <span id="jobKeyPreview" class="job-key-preview">Job key will appear here</span></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="comment">Comments</label>
                                                        <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Additional notes about this job"></textarea>
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
                                                            // Reset pointer for vessel result
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
                                                            // Reset pointer for boat result
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
                                                            // Reset pointer for port result
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
                                        <button class="btn btn-outline-danger" type="reset">
                                            <i class="icon-close"></i> Cancel
                                        </button>
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
                
                if (selectedJobType == '6') { // General job type
                    // Hide vessel, boat, and port fields
                    $('#vesselNameGroup').hide();
                    $('#boatNameGroup').hide();
                    $('#portNameGroup').hide();
                    
                    // Remove required attribute from hidden fields
                    $('#vesselName').removeAttr('required');
                    $('#boatName').removeAttr('required');
                    $('#portName').removeAttr('required');
                } else {
                    // Show vessel, boat, and port fields
                    $('#vesselNameGroup').show();
                    $('#boatNameGroup').show();
                    $('#portNameGroup').show();
                    
                    // Add required attribute back to visible fields
                    $('#vesselName').attr('required', 'required');
                    $('#boatName').attr('required', 'required');
                    $('#portName').attr('required', 'required');
                }
            });

            function updateJobKeyPreview() {
                var jobNumber = $('#jobNumber').val();
                var boatName = $('#boatName option:selected').text();
                
                if (jobNumber && boatName && boatName !== 'Select Boat') {
                    $('#jobKeyPreview').text('WOSS -' + jobNumber + ' ' + boatName);
                } else {
                    $('#jobKeyPreview').text('Job key will appear here');
                }
            }
            
            $('#jobNumber, #boatName').on('input change', updateJobKeyPreview);
        });
    </script>
</body>
</html>