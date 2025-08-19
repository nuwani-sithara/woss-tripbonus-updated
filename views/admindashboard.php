<?php

session_start();
include '../config/dbConnect.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has role_id = 1
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 4) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- DASHBOARD DATA LOGIC ---
// Fetch role-wise user counts
$userCounts = [];
$roleNames = [];
$excludedRoles = ['Operations Officer', 'Operations Manager', 'Accountant', 'CEO', 'Director'];
$sql = "SELECT u.roleID, r.role_name, COUNT(*) as user_count FROM users u JOIN roles r ON u.roleID = r.roleID GROUP BY u.roleID";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (!in_array($row['role_name'], $excludedRoles)) {
            $userCounts[$row['roleID']] = $row['user_count'];
            $roleNames[$row['roleID']] = $row['role_name'];
        }
    }
}

// Fetch role-wise employees with payment info
$employees = [];
$sql = "SELECT e.empID, u.fname, u.lname, r.role_name, ra.rate_name, ra.rate FROM employees e JOIN users u ON e.userID = u.userID JOIN roles r ON e.roleID = r.roleID LEFT JOIN rates ra ON u.rateID = ra.rateID ORDER BY r.role_name, u.fname, u.lname";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Fetch ongoing jobs with all related information
$ongoingJobs = [];
$sql = "SELECT 
          j.jobID,
          jt.type_name as job_type,
          b.boat_name,
          p.portname,
          v.vessel_name,
          j.end_date,
          j.start_date
        FROM jobs j
        LEFT JOIN jobtype jt ON j.jobtypeID = jt.jobtypeID
        LEFT JOIN boatassignments ba ON j.jobID = ba.jobID
        LEFT JOIN boats b ON ba.boatID = b.boatID
        LEFT JOIN portassignments pa ON j.jobID = pa.jobID
        LEFT JOIN ports p ON pa.portID = p.portID
        LEFT JOIN vessels v ON j.vesselID = v.vesselID
        ORDER BY j.start_date DESC
        LIMIT 10";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $jobID = $row['jobID'];
        
        // Determine status
        $status = 'Ongoing';
        $statusClass = 'success';
        $statusIcon = 'fas fa-play-circle';
        
        if (!empty($row['end_date'])) {
            // Check approval status
            $approvalSql = "SELECT approval_status FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval' ORDER BY approvalID DESC LIMIT 1";
            $approvalRes = $conn->query($approvalSql);
            if ($approvalRes && $approvalRow = $approvalRes->fetch_assoc()) {
                switch ($approvalRow['approval_status']) {
                    case 1:
                        $status = 'Completed';
                        $statusClass = 'success';
                        $statusIcon = 'fas fa-check-circle';
                        break;
                    case 3:
                        $status = 'Rejected';
                        $statusClass = 'danger';
                        $statusIcon = 'fas fa-times-circle';
                        break;
                    case 2:
                        $status = 'Goes to Clarification';
                        $statusClass = 'warning';
                        $statusIcon = 'fas fa-question-circle';
                        break;
                    default:
                        $status = 'Pending';
                        $statusClass = 'warning';
                        $statusIcon = 'fas fa-clock';
                        break;
                }
            } else {
                $status = 'Pending';
                $statusClass = 'warning';
                $statusIcon = 'fas fa-clock';
            }
        }
        
        $ongoingJobs[] = [
            'jobID' => $row['jobID'],
            'job_type' => $row['job_type'] ?? 'N/A',
            'boat' => $row['boat_name'] ?? 'N/A',
            'port' => $row['portname'] ?? 'N/A',
            'vessel' => $row['vessel_name'] ?? 'N/A',
            'status' => $status,
            'statusClass' => $statusClass,
            'statusIcon' => $statusIcon
        ];
    }
}

// Month/year selection for job stats
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch job approval stats for selected month/year
$approvedCount = $rejectedCount = $pendingCount = 0;
// Approved
$sql = "SELECT COUNT(*) as cnt FROM jobs j JOIN approvals a ON j.jobID = a.jobID WHERE a.approval_status = 1 AND a.approval_stage = 'job_approval' AND MONTH(j.start_date) = $selectedMonth AND YEAR(j.start_date) = $selectedYear";
$res = $conn->query($sql);
if ($res && ($row = $res->fetch_assoc())) $approvedCount = $row['cnt'];
// Rejected
$sql = "SELECT COUNT(*) as cnt FROM jobs j JOIN approvals a ON j.jobID = a.jobID WHERE a.approval_status = 3 AND a.approval_stage = 'job_approval' AND MONTH(j.start_date) = $selectedMonth AND YEAR(j.start_date) = $selectedYear";
$res = $conn->query($sql);
if ($res && ($row = $res->fetch_assoc())) $rejectedCount = $row['cnt'];
// Pending
$sql = "SELECT COUNT(*) as cnt FROM jobs j LEFT JOIN approvals a ON j.jobID = a.jobID AND a.approval_stage = 'job_approval' WHERE (a.approval_status IS NULL OR (a.approval_status != 1 AND a.approval_status != 3)) AND MONTH(j.start_date) = $selectedMonth AND YEAR(j.start_date) = $selectedYear";
$res = $conn->query($sql);
if ($res && ($row = $res->fetch_assoc())) $pendingCount = $row['cnt'];
// --- END DASHBOARD DATA LOGIC ---

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Admin Dashboard - WOSS Trip Bonus System</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="../assets/img/logo_white.png"
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
    
    <!-- Custom styles for ongoing jobs table -->
    <style>
      .table-hover tbody tr:hover {
        background-color: rgba(23, 125, 255, 0.05);
        transform: scale(1.01);
        transition: all 0.2s ease;
      }
      
      .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
      }
      
      .btn-group .btn {
        border-radius: 0.375rem !important;
        margin: 0 1px;
      }
      
      .btn-group .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }
      
      .timeline-container {
        max-height: 300px;
        overflow-y: auto;
      }
      
      .timeline-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1rem;
        position: relative;
      }
      
      .timeline-marker {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 1rem;
        margin-top: 0.25rem;
        flex-shrink: 0;
      }
      
      .timeline-content h6 {
        margin-bottom: 0.25rem;
        font-weight: 600;
      }
      
      .card-header {
         background: linear-gradient(135deg, #2e59d9 0%,rgb(96, 121, 198) 100%);
         color: white !important;
         border-bottom: none;
       }
       
       .card-header h4,
       .card-header .card-title {
         color: white !important;
       }
      
      .card-header .badge {
        background-color: rgba(235, 224, 224, 0.2) !important;
        color: white !important;
      }
      
      .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
      }
      
      .table td {
        vertical-align: middle;
        padding: 0.75rem 0.5rem;
      }
      
      .text-primary {
        color: #177dff !important;
      }
      
      .bg-success {
        background-color: #28a745 !important;
      }
      
      .bg-warning {
        background-color: #ffc107 !important;
        color: #212529 !important;
      }
      
      .bg-danger {
        background-color: #dc3545 !important;
      }

      .badge-primary-soft {
        background-color: rgba(23, 125, 255, 0.1);
        color: #177dff;
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
    </style>
  </head>
  <body>
    <div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>

      <div class="main-panel">
        <div class="main-header">
          <div class="main-header-logo">
            <!-- Logo Header -->
            <div class="logo-header" data-background-color="dark">
              <a href="../views/admindashboard.php" class="logo">
                <img
                  src="../assets/img/logo_white.png"
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
                <h3 class="fw-bold mb-3">Admin Dashboard</h3>
                <h6 class="op-7 mb-2">Admin Dashboard - Operation Manager</h6>
              </div>
              <!-- <div class="ms-md-auto py-2 py-md-0">
                <a href="#" class="btn btn-label-info btn-round me-2">Manage</a>
                <a href="#" class="btn btn-primary btn-round">Add Customer</a>
              </div> -->
            </div>
            <div class="row">
              <!-- Role-wise User Counts Cards -->
              <?php foreach ($userCounts as $roleID => $count): ?>
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
                          <p class="card-category"><?php echo htmlspecialchars($roleNames[$roleID]); ?></p>
                          <h4 class="card-title"><?php echo $count; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <!-- Job Stats Month/Year Selector & Cards -->
            <form method="get" class="mb-3">
              <div class="row g-2 align-items-end">
                <div class="col-auto">
                  <label for="month" class="form-label mb-0">Month</label>
                  <select name="month" id="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                      <option value="<?php echo $m; ?>" <?php if ($selectedMonth == $m) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-auto">
                  <label for="year" class="form-label mb-0">Year</label>
                  <select name="year" id="year" class="form-select">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                      <option value="<?php echo $y; ?>" <?php if ($selectedYear == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-auto">
                  <button type="submit" class="btn btn-primary">Filter</button>
                </div>
              </div>
            </form>
            <div class="row mb-4">
              <div class="col-md-4">
                <div class="card card-stats card-round">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-icon">
                        <div class="icon-big text-center icon-success bubble-shadow-small">
                          <i class="fas fa-check-circle"></i>
                        </div>
                      </div>
                      <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                          <p class="card-category">Approved Jobs</p>
                          <h4 class="card-title"><?php echo $approvedCount; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-stats card-round">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-icon">
                        <div class="icon-big text-center icon-warning bubble-shadow-small">
                          <i class="fas fa-hourglass-half"></i>
                        </div>
                      </div>
                      <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                          <p class="card-category">Pending Jobs</p>
                          <h4 class="card-title"><?php echo $pendingCount; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-stats card-round">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-icon">
                        <div class="icon-big text-center icon-danger bubble-shadow-small">
                          <i class="fas fa-times-circle"></i>
                        </div>
                      </div>
                      <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                          <p class="card-category">Rejected Jobs</p>
                          <h4 class="card-title"><?php echo $rejectedCount; ?></h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Ongoing Jobs Table -->
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex align-items-center">
                      <h4 class="card-title mb-0">
                        <!-- <i class="fas fa-tasks me-2"></i> -->
                        Ongoing Jobs Overview
                      </h4>
                      <span class="badge bg-primary-soft text-primary ms-auto" id="jobCount"><?php echo count($ongoingJobs); ?> Active</span>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-hover" id="ongoingJobsTable">
                        <thead>
                          <tr>
                            <th class="text-uppercase small fw-bold">Job ID</th>
                            <th class="text-uppercase small fw-bold">Job Type</th>
                            <th class="text-uppercase small fw-bold">Boat</th>
                            <th class="text-uppercase small fw-bold">Port</th>
                            <th class="text-uppercase small fw-bold">Vessel</th>
                            <th class="text-uppercase small fw-bold">Status</th>
                            <!-- <th class="text-uppercase small fw-bold text-end">Actions</th> -->
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($ongoingJobs as $job): ?>
                          <tr>
                            <td class="fw-semibold">#<?php echo $job['jobID']; ?></td>
                            <td>
                              <span class="text-muted"><?php echo htmlspecialchars($job['job_type']); ?></span>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <i class="fas fa-ship me-2 text-muted"></i>
                                <span><?php echo htmlspecialchars($job['boat']); ?></span>
                              </div>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <i class="fas fa-anchor me-2 text-muted"></i>
                                <span><?php echo htmlspecialchars($job['port']); ?></span>
                              </div>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <i class="fas fa-ship me-2 text-muted"></i>
                                <span><?php echo htmlspecialchars($job['vessel']); ?></span>
                              </div>
                            </td>
                            <td>
                              <span class="badge badge-<?php echo $job['statusClass']; ?>-soft">
                                <i class="<?php echo $job['statusIcon']; ?> me-1"></i>
                                <?php echo $job['status']; ?>
                              </span>
                            </td>
                            <!-- <td class="text-end">
                              <button class="btn btn-sm btn-outline-primary" onclick="viewjobdetails(<?php echo $job['jobID']; ?>)">
                                <i class="fas fa-eye"></i> View
                              </button>
                            </td> -->
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    
                    <?php if (count($ongoingJobs) > 0): ?>
                    <div class="text-center mt-4">
                      <a href="approvejobs.php" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-2"></i>
                        View All Jobs
                      </a>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                      <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                      <h5 class="text-muted">No Ongoing Jobs</h5>
                      <p class="text-muted">There are currently no active jobs in the system.</p>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            
        </div>

        <?php include 'components/footer.php'; ?>
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

    <!-- Remove demo scripts to prevent unwanted notifications -->
    <!-- <script src="../assets/js/setting-demo.js"></script> -->
    <!-- <script src="../assets/js/demo.js"></script> -->
    <script>
      $("#lineChart").sparkline([102, 109, 120, 99, 110, 105, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#177dff",
        fillColor: "rgba(23, 125, 255, 0.14)",
      });

      $("#lineChart2").sparkline([99, 125, 122, 105, 110, 124, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#f3545d",
        fillColor: "rgba(243, 84, 93, .14)",
      });

      $("#lineChart3").sparkline([105, 103, 123, 100, 95, 105, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#ffa534",
        fillColor: "rgba(255, 165, 52, .14)",
      });

      

      // Job action functions
      function viewJobDetails(jobID) {
        // Redirect to job details page or show modal
        window.location.href = `approvejobs.php?jobID=${jobID}`;
      }

      function viewJobTimeline(jobID) {
        // Show job timeline/history in a modal
        Swal.fire({
          title: `Job #${jobID} Timeline`,
          html: '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading timeline...</div>',
          showConfirmButton: false,
          allowOutsideClick: false
        });
        
        // You can implement AJAX call here to fetch job timeline
        // For now, just show a placeholder
        setTimeout(() => {
          Swal.fire({
            title: `Job #${jobID} Timeline`,
            html: `
              <div class="timeline-container text-start">
                <div class="timeline-item">
                  <div class="timeline-marker bg-primary"></div>
                  <div class="timeline-content">
                    <h6>Job Created</h6>
                    <small class="text-muted">Start date</small>
                  </div>
                </div>
                <div class="timeline-item">
                  <div class="timeline-marker bg-warning"></div>
                  <div class="timeline-content">
                    <h6>Under Review</h6>
                    <small class="text-muted">Pending approval</small>
                  </div>
                </div>
              </div>
            `,
            confirmButtonText: 'Close',
            confirmButtonColor: '#177dff'
          });
        }, 1000);
      }

      // Initialize DataTable for ongoing jobs
      $(document).ready(function() {
        // Update job count
        const jobCount = $('#ongoingJobsTable tbody tr').length;
        $('#jobCount').text(jobCount + ' Active');
        
        if ($('#ongoingJobsTable').length) {
          $('#ongoingJobsTable').DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
            responsive: true,
            language: {
              search: "Search jobs:",
              lengthMenu: "Show _MENU_ jobs per page",
              info: "Showing _START_ to _END_ of _TOTAL_ jobs",
              paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
              }
            }
          });
        }
      });
    </script>
  </body>
</html>

