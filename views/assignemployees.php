<?php
session_start();
include '../config/dbConnect.php';

// Check permissions and trip ID
if (!isset($_SESSION['userID'])) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

$tripID = $_GET['tripID'] ?? 0;
if ($tripID <= 0) {
    die("Invalid Trip ID");
}

// Get trip and job details
$tripQuery = "SELECT t.*, j.vesselID
              FROM trips t
              JOIN jobs j ON t.jobID = j.jobID
              WHERE t.tripID = $tripID";
$tripResult = mysqli_query($conn, $tripQuery);
$trip = mysqli_fetch_assoc($tripResult);

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
$all_divers = [];
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
$assignedDivers = [];
$assignedQuery = "SELECT u.userID, u.fname, u.lname 
                  FROM jobassignments ja
                  JOIN employees e ON ja.empID = e.empID
                  JOIN users u ON e.userID = u.userID
                  WHERE ja.tripID = $tripID";
$assignedResult = mysqli_query($conn, $assignedQuery);
while ($diver = mysqli_fetch_assoc($assignedResult)) {
    $assignedDivers[$diver['userID']] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>WOSS - Create New Job</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../assets/img/logo_white.png" type="image/x-icon" />
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
                                src="../assets/img/Logo_white.png"
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
                        <h3 class="fw-bold mb-3">Assign Employees - <?php echo $trip['vesselID']; ?> (Day <?php echo $trip['tripID']; ?>)</h3>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Select Employees for <?php echo $trip['trip_date']; ?></div>
                                </div>
                                <form method="POST" action="../controllers/assignEmployeesController.php">
                                    <input type="hidden" name="tripID" value="<?php echo $tripID; ?>">
                                    <input type="hidden" name="standby_attendanceID" value="<?php echo $standby_attendanceID; ?>">
                                    
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Standby Divers</label>
                                                    <div class="selectgroup selectgroup-pills">
                                                        <?php foreach ($diver_list as $diver): ?>
                                                            <label class="selectgroup-item">
                                                                <input type="checkbox" name="divers[]" value="<?php echo $diver['userID']; ?>" 
                                                                       class="selectgroup-input" <?php echo isset($assignedDivers[$diver['userID']]) ? 'checked' : ''; ?>>
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
                                                                <input type="checkbox" name="otherDivers[]" value="<?php echo $diver['userID']; ?>" 
                                                                       class="selectgroup-input" <?php echo isset($assignedDivers[$diver['userID']]) ? 'checked' : ''; ?>>
                                                                <span class="selectgroup-button"><?php echo htmlspecialchars($diver['fname'] . ' ' . $diver['lname']); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-action">
                                        <button type="submit" class="btn btn-success">Assign Employees</button>
                                        <a href="managejobdays.php?jobID=<?php echo $trip['jobID']; ?>" class="btn btn-secondary">Back to Job</a>
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
        
        <!-- jQuery Scrollbar -->
        <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

        <!-- jQuery Sparkline -->
        <script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>
</body>
</html>