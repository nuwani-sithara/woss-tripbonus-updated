<?php

session_start();
include '../config/dbConnect.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has supervisor role
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 1) { // Assuming roleID 1 is for supervisor
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$loggedUserID = $_SESSION['userID'];

// --- DASHBOARD DATA LOGIC ---

// 1. Latest Standby Employees
$latestStandbyEmployees = [];
$sql = "SELECT 
          sa.standby_attendanceID,
          sa.date,
          ea.empID,
          u.fname,
          u.lname,
          r.role_name
        FROM standby_attendance sa
        JOIN standbyassignments ea ON sa.standby_attendanceID = ea.standby_attendanceID
        JOIN employees e ON ea.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN roles r ON e.roleID = r.roleID
        WHERE ea.status = 1
          AND u.roleID IN (2, 8, 9)
        ORDER BY sa.date DESC, u.fname, u.lname
        LIMIT 10";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $latestStandbyEmployees[] = $row;
    }
}

// 2. Role-wise Standby Counts
$roleWiseStandbyCounts = [];
$sql = "SELECT 
          r.roleID,
          r.role_name,
          COUNT(*) as employee_count
        FROM standbyassignments ea
        JOIN employees e ON ea.empID = e.empID
        JOIN users u ON e.userID = u.userID
        JOIN roles r ON e.roleID = r.roleID
        WHERE ea.status = 1
          AND u.roleID IN (2, 8, 9)
        GROUP BY r.roleID, r.role_name
        ORDER BY employee_count DESC";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $roleWiseStandbyCounts[] = $row;
    }
}

// 3. Latest 10 Jobs Created by Supervisor
$latestJobs = [];
$sql = "SELECT 
          j.jobID,
          j.start_date,
          j.end_date,
          j.comment,
          jt.type_name as job_type,
          v.vessel_name,
          CASE 
            WHEN a.approval_status IS NULL THEN 'Pending'
            WHEN a.approval_status = 1 THEN 'Approved'
            WHEN a.approval_status = 3 THEN 'Rejected'
            ELSE 'Pending'
          END as status,
          CASE 
            WHEN a.approval_status IS NULL THEN 'warning'
            WHEN a.approval_status = 1 THEN 'success'
            WHEN a.approval_status = 3 THEN 'danger'
            ELSE 'warning'
          END as status_class,
          CASE 
            WHEN a.approval_status IS NULL THEN 'fas fa-clock'
            WHEN a.approval_status = 1 THEN 'fas fa-check-circle'
            WHEN a.approval_status = 3 THEN 'fas fa-times-circle'
            ELSE 'fas fa-clock'
          END as status_icon
        FROM jobs j
        LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
        LEFT JOIN vessels v ON j.vesselID = v.vesselID
        LEFT JOIN approvals a ON j.jobID = a.jobID AND a.approval_stage = 'job_approval'
        WHERE j.jobCreatedBy = $loggedUserID
        GROUP BY j.jobID
        ORDER BY j.start_date DESC
        LIMIT 10";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $latestJobs[] = $row;
    }
}

// 4. Clarification Needed Jobs
$clarificationJobs = [];
$sql = "SELECT 
          j.jobID,
          j.start_date,
          j.end_date,
          j.comment,
          jt.type_name as job_type,
          v.vessel_name,
          c.clarification_id,
          c.clarification_request_comment,
          c.clarification_status,
          c.clarification_requesterID,
          requester.fname as requester_fname,
          requester.lname as requester_lname
        FROM jobs j
        LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
        LEFT JOIN vessels v ON j.vesselID = v.vesselID
        JOIN clarifications c ON j.jobID = c.jobID
        LEFT JOIN users requester ON c.clarification_requesterID = requester.userID
        WHERE j.jobCreatedBy = $loggedUserID
        AND c.clarification_status = 0
        ORDER BY j.start_date DESC";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $clarificationJobs[] = $row;
    }
}

// Count statistics
$totalStandbyEmployees = count($latestStandbyEmployees);
$totalRoles = count($roleWiseStandbyCounts);
$totalJobs = count($latestJobs);
$totalClarifications = count($clarificationJobs);

// --- END DASHBOARD DATA LOGIC ---

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Supervisor Dashboard - WOSS Trip Bonus System</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="../assets/img/app-logo1.png"
      type="image/x-icon"
    />

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

    <!-- CSS Just for demo purpose, don't include it in your project -->
    <link rel="stylesheet" href="../assets/css/demo.css" />
    
    <!-- Custom styles for supervisor dashboard -->
    <style>
      :root {
        --card-border-radius: 10px;
        --card-box-shadow: 0 4px 6px rgba(0, 0, 0, 0.03);
        --card-hover-shadow: 0 10px 15px rgba(0, 0, 0, 0.05);
        --primary-color: #4361ee;
        --light-gray: #f8f9fa;
        --medium-gray: #e9ecef;
        --dark-gray: #6c757d;
      }
      
      body {
        background-color: #f5f7fb;
        color: #495057;
      }
      
      .card {
        border: none;
        border-radius: var(--card-border-radius);
        box-shadow: var(--card-box-shadow);
        transition: all 0.3s ease;
        margin-bottom: 24px;
        background-color: white;
      }
      
      .card:hover {
        box-shadow: var(--card-hover-shadow);
        transform: translateY(-2px);
      }
      
      .card-header {
        background-color: white !important;
        border-bottom: 1px solid var(--medium-gray);
        padding: 1.25rem 1.5rem;
      }
      
      .card-header h4 {
        color: #343a40 !important;
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0;
      }
      
      .card-header .badge {
        background-color: var(--light-gray) !important;
        color: var(--dark-gray) !important;
        font-weight: 500;
      }
      
      .table {
        margin-bottom: 0;
      }
      
      .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-top: none;
        border-bottom: 1px solid var(--medium-gray);
        color: var(--dark-gray);
        padding: 0.75rem 1rem;
      }
      
      .table td {
        padding: 1rem;
        vertical-align: middle;
        border-top: 1px solid var(--medium-gray);
      }
      
      .table-hover tbody tr:hover {
        background-color: rgba(67, 97, 238, 0.03);
      }
      
      /* Badge styles */
      .badge {
        font-weight: 500;
        padding: 0.35em 0.65em;
        font-size: 0.75rem;
      }
      
      .badge-primary-soft {
        background-color: rgba(67, 97, 238, 0.1);
        color: var(--primary-color);
      }
      
      .badge-success-soft {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
      }
      
      .badge-warning-soft {
        background-color: rgba(255, 193, 7, 0.1);
        color: #ffc107;
      }
      
      .badge-danger-soft {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
      }
      
      /* Role count cards */
      .role-count-card {
        background-color: white;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        border-left: 4px solid var(--primary-color);
        box-shadow: var(--card-box-shadow);
      }
      
      .role-count-card h5 {
        font-size: 1rem;
        margin-bottom: 0.25rem;
        color: #343a40;
      }
      
      .role-count-card p {
        font-size: 0.8rem;
        color: var(--dark-gray);
        margin-bottom: 0;
      }
      
      .role-count-card h2 {
        color: var(--primary-color);
        margin-bottom: 0;
      }
      
      /* Urgent alerts */
      .urgent-alert {
        border-left: 4px solid #dc3545;
      }
      
      .urgent-alert .card-header {
        border-left: 4px solid #dc3545;
        background-color: rgba(220, 53, 69, 0.03) !important;
      }
      
      /* Quick action buttons */
      .quick-action-btn {
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 0.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
      }
      
      .quick-action-btn:hover {
        transform: translateY(-3px);
        box-shadow: var(--card-hover-shadow);
      }
      
      .quick-action-btn i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
      }
      
      /* Empty state styling */
      .empty-state {
        padding: 2rem 0;
        text-align: center;
      }
      
      .empty-state i {
        font-size: 2.5rem;
        color: var(--medium-gray);
        margin-bottom: 1rem;
      }
      
      .empty-state p {
        color: var(--dark-gray);
      }
      
      /* Status indicators */
      .status-indicator {
        display: inline-flex;
        align-items: center;
      }
      
      .status-indicator i {
        margin-right: 0.35rem;
        font-size: 0.9rem;
      }
      
      /* Avatar styling */
      .avatar-sm {
        width: 36px;
        height: 36px;
      }
      
      .avatar-sm img {
        object-fit: cover;
      }
      
      /* Page header */
      .page-header h3 {
        font-weight: 600;
        color: #343a40;
      }
      
      .page-header h6 {
        color: var(--dark-gray);
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
            <div
              class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4"
            >
              <div>
                <h3 class="fw-bold mb-3">Supervisor Dashboard</h3>
                <?php
                // Get user's full name
                $userFname = isset($_SESSION['fname']) ? $_SESSION['fname'] : '';
                $userLname = isset($_SESSION['lname']) ? $_SESSION['lname'] : '';
                $userFullName = trim($userFname . ' ' . $userLname);
                $displayName = !empty($userFullName) ? $userFullName : ($_SESSION['username'] ?? 'Supervisor');
                ?>
                <h6 class="op-7 mb-2">Welcome back, <?php echo htmlspecialchars($displayName); ?>!</h6>
              </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
              <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-icon">
                        <div class="icon-big text-center icon-primary bubble-shadow-small">
                          <i class="fas fa-users"></i>
                        </div>
                      </div>
                      <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                          <p class="card-category">Standby Employees</p>
                          <h4 class="card-title"><?php echo $totalStandbyEmployees; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-icon">
                        <div class="icon-big text-center icon-success bubble-shadow-small">
                          <i class="fas fa-user-tag"></i>
                        </div>
                      </div>
                      <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                          <p class="card-category">Active Roles</p>
                          <h4 class="card-title"><?php echo $totalRoles; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-icon">
                        <div class="icon-big text-center icon-warning bubble-shadow-small">
                          <i class="fas fa-tasks"></i>
                        </div>
                      </div>
                      <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                          <p class="card-category">Recent Jobs</p>
                          <h4 class="card-title"><?php echo $totalJobs; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-icon">
                        <div class="icon-big text-center icon-danger bubble-shadow-small">
                          <i class="fas fa-exclamation-triangle"></i>
                        </div>
                      </div>
                      <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                          <p class="card-category">Clarifications</p>
                          <h4 class="card-title"><?php echo $totalClarifications; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Quick Actions Row -->
          <div class="row mt-4">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h4 class="card-title mb-0">
                    <i class="fas fa-bolt text-primary me-2"></i>
                    Quick Actions
                  </h4>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <a href="createjob.php" class="btn btn-light quick-action-btn text-primary">
                        <i class="fas fa-plus-circle"></i>
                        Create New Job
                      </a>
                    </div>
                    <div class="col-md-3 mb-3">
                      <a href="standbyattendancemark.php" class="btn btn-light quick-action-btn text-success">
                        <i class="fas fa-clock"></i>
                        Mark Attendance
                      </a>
                    </div>
                    <div class="col-md-3 mb-3">
                      <a href="supervisoreditjobs.php" class="btn btn-light quick-action-btn text-warning">
                        <i class="fas fa-edit"></i>
                        Manage Jobs
                      </a>
                    </div>
                    <div class="col-md-3 mb-3">
                      <a href="https://wa.me/94762276259" class="btn btn-light quick-action-btn text-info">
                        <!-- Need to add whatsapp link -->
                        <i class="fab fa-whatsapp"></i>
                        Contact Manager
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

            <!-- Main Content Row -->
          <div class="row">
            <!-- Latest Standby Employees -->
            <div class="col-md-6">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4 class="card-title mb-0">
                    <i class="fas fa-users text-primary me-2"></i>
                    Latest Standby Employees
                  </h4>
                  <span class="badge"><?php echo $totalStandbyEmployees; ?></span>
                </div>
                <div class="card-body">
                  <?php if (empty($latestStandbyEmployees)): ?>
                    <div class="empty-state">
                      <i class="fas fa-users"></i>
                      <p>No standby employees found</p>
                    </div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-hover">
                        <thead>
                          <tr>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Date</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($latestStandbyEmployees as $employee): ?>
                            <tr>
                              <td>
                                <div class="d-flex align-items-center">
                                  <div class="avatar-sm me-3">
                                    <img src="../assets/img/profile-icon1.jpg" alt="..." class="avatar-img rounded-circle" />
                                  </div>
                                  <div>
                                    <strong><?php echo htmlspecialchars($employee['fname'] . ' ' . $employee['lname']); ?></strong>
                                  </div>
                                </div>
                              </td>
                              <td>
                                <span class="badge badge-primary-soft"><?php echo htmlspecialchars($employee['role_name']); ?></span>
                              </td>
                              <td>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($employee['date'])); ?></small>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Role-wise Standby Counts -->
            <div class="col-md-6">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4 class="card-title mb-0">
                    <i class="fas fa-tasks text-primary me-2"></i>
                    Recent Jobs
                  </h4>
                  <span class="badge"><?php echo $totalJobs; ?></span>
                </div>
                <div class="card-body">
                  <?php if (empty($latestJobs)): ?>
                    <div class="empty-state">
                      <i class="fas fa-tasks"></i>
                      <p>No jobs created yet</p>
                    </div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-hover">
                        <thead>
                          <tr>
                            <th>Job Type</th>
                            <th>Vessel</th>
                            <th>Status</th>
                            <th>Date</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($latestJobs as $job): ?>
                            <tr>
                              <td>
                                <strong><?php echo htmlspecialchars($job['job_type'] ?? 'N/A'); ?></strong>
                                <?php if ($job['comment']): ?>
                                  <br><small class="text-muted"><?php echo htmlspecialchars(substr($job['comment'], 0, 50)) . (strlen($job['comment']) > 50 ? '...' : ''); ?></small>
                                <?php endif; ?>
                              </td>
                              <td><?php echo htmlspecialchars($job['vessel_name'] ?? 'N/A'); ?></td>
                              <td>
                                <span class="status-indicator badge badge-<?php echo $job['status_class']; ?>-soft">
                                  <i class="<?php echo $job['status_icon']; ?>"></i>
                                  <?php echo $job['status']; ?>
                                </span>
                              </td>
                              <td>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($job['start_date'])); ?></small>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="card urgent-alert">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Clarifications Needed
                  </h4>
                  <span class="badge"><?php echo $totalClarifications; ?></span>
                </div>
                <div class="card-body">
                  <?php if (empty($clarificationJobs)): ?>
                    <div class="empty-state">
                      <i class="fas fa-check-circle text-success"></i>
                      <p class="text-success">No clarifications needed</p>
                    </div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-hover">
                        <thead>
                          <tr>
                            <th>Job Type</th>
                            <th>Requester</th>
                            <th>Comment</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($clarificationJobs as $clarification): ?>
                            <tr>
                              <td>
                                <strong><?php echo htmlspecialchars($clarification['job_type'] ?? 'N/A'); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($clarification['vessel_name'] ?? 'N/A'); ?></small>
                              </td>
                              <td>
                                <span class="badge badge-warning-soft">
                                  <?php echo htmlspecialchars($clarification['requester_fname'] . ' ' . $clarification['requester_lname']); ?>
                                </span>
                              </td>
                              <td>
                                <small class="text-muted">
                                  <?php echo htmlspecialchars(substr($clarification['clarification_request_comment'], 0, 60)) . (strlen($clarification['clarification_request_comment']) > 60 ? '...' : ''); ?>
                                </small>
                              </td>
                              <td>
                                <a href="supervisoreditjobs.php?jobID=<?php echo $clarification['jobID']; ?>" 
                                   class="btn btn-sm btn-danger">
                                  <i class="fas fa-reply"></i> Respond
                                </a>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            </div>
          </div>

          


        </div>
        <?php include 'components/footer.php'; ?>
      </div>
    </div>

    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery.3.2.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery UI -->
    <script src="../assets/js/plugin/jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>
    <script src="../assets/js/plugin/jquery-ui-touch-punch/jquery.ui.touch-punch.min.js"></script>

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
    <script src="../assets/js/plugin/jqvmap/jquery.vmap.min.js"></script>
    <script src="../assets/js/plugin/jqvmap/maps/jquery.vmap.world.js"></script>

    <!-- Sweet Alert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Atlantis JS -->
    <script src="../assets/js/atlantis.min.js"></script>

    <!-- Custom JS for supervisor dashboard -->
    <script>
      // Auto-refresh dashboard every 30 seconds
      setInterval(function() {
        location.reload();
      }, 30000);

      // Add hover effects for cards
      document.addEventListener('DOMContentLoaded', function() {
        // Add click handlers for job status cards
        const jobCards = document.querySelectorAll('.job-status-card');
        jobCards.forEach(card => {
          card.addEventListener('click', function() {
            // You can add navigation to job details here
            console.log('Job card clicked');
          });
        });

        // Add urgency indicator for clarification jobs
        const urgentCards = document.querySelectorAll('.urgent-alert');
        urgentCards.forEach(card => {
          card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
          });
          card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
          });
        });
      });
    </script>
  </body>
</html>
