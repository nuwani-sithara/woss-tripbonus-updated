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
                        <h3 class="fw-bold mb-3">Job Creation Form</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Fill This Form To Create A New Job</div>
                                </div>
                                <form method="POST" action="../controllers/createJobController.php" class="space-y-6" enctype="multipart/form-data">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="startDate">Start Date</label>
                                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="jobNumber">Job Number</label>
                                                    <input type="text" class="form-control" id="jobNumber" name="jobNumber" required 
                                                        placeholder="Enter job number (e.g., 1028)">
                                                </div>
                                                
                                                <div class="form-group" id="vesselNameGroup">
                                                    <label for="vesselName">Vessel Name</label>
                                                    <select class="form-control" id="vesselName" name="vesselID" required>
                                                        <option value="">Select Vessel</option>
                                                        <?php while ($vessel = mysqli_fetch_assoc($vessel_result)) { ?>
                                                            <option value="<?php echo $vessel['vesselID']; ?>"><?php echo $vessel['vessel_name']; ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="jobType">Job Type</label>
                                                    <select class="form-control" id="jobType" name="jobTypeID" required>
                                                        <option value="">Select Job Type</option>
                                                        <?php while ($jobType = mysqli_fetch_assoc($jobType_result)) { ?>
                                                            <option value="<?php echo $jobType['jobtypeID']; ?>"><?php echo $jobType['type_name']; ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>

                                                <div class="form-group" id="boatNameGroup">
                                                    <label for="boatName">Boat Name</label>
                                                    <select class="form-control" id="boatName" name="boatID" required>
                                                        <option value="">Select Boat</option>
                                                        <?php while ($boat = mysqli_fetch_assoc($boat_result)) { ?>
                                                            <option value="<?php echo $boat['boatID']; ?>"><?php echo $boat['boat_name']; ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6" id="portNameGroup">
                                                <div class="form-group">
                                                    <label for="portName">Port Name</label>
                                                    <select class="form-control" id="portName" name="portID" required>
                                                        <option value="">Select Port</option>
                                                        <?php while ($port = mysqli_fetch_assoc($port_result)) { ?>
                                                            <option value="<?php echo $port['portID']; ?>"><?php echo $port['portname']; ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="comment">Comments</label>
                                                    <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- <div class="form-group">
                                            <label for="isSpecialProject">
                                                <input type="checkbox" id="isSpecialProject" name="isSpecialProject" value="1" />
                                                Is this a Special Project?
                                            </label>
                                        </div>

                                        <div id="specialProjectForm" style="display: none;">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="name">Project Name</label>
                                                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter Special Project Name">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="vessel">Vessel (Special Project)</label>
                                                        <input type="text" class="form-control" id="vessel" name="vessel" placeholder="Enter Vessel Name for Special Project">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="date">Date (Special Project)</label>
                                                        <input type="date" class="form-control" id="date" name="date" placeholder="Select Date for Special Project">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="evidence">Evidence (PDF, XLSX, Word, Mail Trailer)</label>
                                                        <input type="file" class="form-control" id="evidence" name="evidence" accept=".pdf, .xlsx, .xls, .doc, .docx, .eml, .msg">
                                                    </div>
                                                </div>
                                            </div>
                                        </div> -->
                                    </div>
                                    <div class="card-action">
                                        <button class="btn btn-success" name="create_job" type="submit">Create Job</button>
                                        <button class="btn btn-danger" type="reset">Cancel</button>
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
            // Toggle special project form
            $('#isSpecialProject').change(function() {
                if($(this).is(':checked')) {
                    $('#specialProjectForm').slideDown();
                } else {
                    $('#specialProjectForm').slideUp();
                }
            });

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
        });

        // Add this script to the page
        $(document).ready(function() {
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
            
            // Add a preview element to the form (place it after the job number field)
            $('#jobNumber').after('<div class="form-text">Job Key Preview: <span id="jobKeyPreview" class="fw-bold">Job key will appear here</span></div>');
        });
    </script>
</body>
</html>