<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and has role_id = 1
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 3) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// No PHP needed here, all data is fetched via AJAX
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Trip-wise Attendance Verification</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
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
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <style>
      .employee-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        margin-right: 5px;
        margin-bottom: 5px;
        font-size: 12px;
      }
      .badge-missed {
        background-color: #f8d7da;
        color: #721c24;
      }
      .badge-present {
        background-color: #d4edda;
        color: #155724;
      }
      .trip-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 15px;
        background-color: #007bff;
        color: white;
        font-size: 11px;
        font-weight: 600;
        margin-left: 10px;
      }
      .card-job {
        border: 1px solid #e3e3e3;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 24px;
      }
      .card-job .card-header {
        background: #f7f7f7;
        border-bottom: 1px solid #e3e3e3;
        border-radius: 12px 12px 0 0;
      }
      .card-job .card-body {
        padding: 1.5rem;
      }
      .card-job .card-footer {
        background: #f7f7f7;
        border-top: 1px solid #e3e3e3;
        border-radius: 0 0 12px 12px;
        text-align: right;
      }
      .trip-info {
        background-color: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
      }
    </style>
  </head>
  <body>
    <div class="wrapper">
      <?php include 'components/attendanceVerifierSidebar.php'; ?>

      <div class="main-panel">
        <div class="main-header">
          <div class="main-header-logo">
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
          </div>
          <?php include 'components/navbar.php'; ?>
        </div>

        <div class="container">
          <div class="page-inner">
            <div class="page-header">
              <h3 class="fw-bold mb-3">Trip-wise Attendance Verification</h3>
              <ul class="breadcrumbs mb-3">
                <li class="nav-home">
                  <a href="#"><i class="icon-home"></i></a>
                </li>
                <li class="separator"><i class="icon-arrow-right"></i></li>
                <li class="nav-item"><a href="#">Attendance</a></li>
                <li class="separator"><i class="icon-arrow-right"></i></li>
                <li class="nav-item"><a href="#">Trip Verification</a></li>
              </ul>
            </div>
            
            <!-- Information Alert -->
            <div class="alert alert-info alert-dismissible fade show" role="alert">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Trip-wise Verification System:</strong> Each card represents a specific trip that needs attendance verification. 
              Multiple trips from the same job are shown separately to allow independent verification. 
              This ensures that attendance conflicts in one trip don't affect other trips.
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            
            <div id="cardsContainer"></div>
          </div>
        </div>
        <?php include 'components/footer.php'; ?>
      </div>
    </div>

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
      // Function to render attendance verification cards
      // Each card represents a specific trip that needs verification
      // This allows attendance verifiers to handle each trip independently
      function renderCards(jobs) {
          let html = '';
          if (!jobs.length) {
              html = '<div class="alert alert-info">No attendance records to verify.</div>';
          }
          
          jobs.forEach(job => {
              // Create employee list with proper status indicators
              let empList = job.assigned_employees.map(emp => {
                  let badgeClass, statusText;
                  
                  if (emp.standby_status === null) {
                      badgeClass = 'badge-missed';
                      statusText = 'Not Marked';
                  } else if (emp.standby_status === 0) {
                      badgeClass = 'badge-warning';
                      statusText = 'Checked Out';
                  } else {
                      badgeClass = 'badge-present';
                      statusText = 'Present';
                  }
                  
                  return `<span class="employee-badge ${badgeClass}">${emp.fname} ${emp.lname} (${statusText})</span>`;
              }).join(' ');
              
              // Create issues section
              let issuesHtml = '';
              if (job.missed_standby_employees.length > 0 || job.checked_out_employees.length > 0) {
                  issuesHtml = `<div class="mt-3"><strong>Attendance Issues:</strong><br>`;
                  
                  // Add not marked employees
                  issuesHtml += job.missed_standby_employees.map(emp => 
                      `<span class="employee-badge badge-missed">${emp.fname} ${emp.lname} (${emp.status_text})</span>`
                  ).join(' ');
                  
                  // Add checked out employees
                  issuesHtml += job.checked_out_employees.map(emp => 
                      `<span class="employee-badge badge-warning">${emp.fname} ${emp.lname} (${emp.status_text})</span>`
                  ).join(' ');
                  
                  issuesHtml += `</div>`;
              }
              
              // Build the card HTML
              html += `
                  <div class="card card-job">
                      <div class="card-header">
                          <h5 class="mb-0">Job ID: ${job.jobID} | ${job.job_date}</h5>
                          <span class="text-muted">Type: ${job.job_type_name}</span>
                      </div>
                      <div class="card-body">
                          <div class="trip-info">
                              <strong>Trip Information:</strong><br>
                              <strong>Trip Date:</strong> ${job.trip_date || 'Not specified'}<br>
                              <strong>Standby Attendance Date:</strong> ${job.standby_date}
                          </div>
                          <p><strong>Job Created Date:</strong> ${job.job_created_at || job.job_date}</p>
                          <p><strong>Assigned Employees:</strong><br>${empList}</p>
                          ${issuesHtml}
                      </div>
                      <div class="card-footer">
                          <button class="btn btn-success action-btn me-2" data-jobid="${job.job_attendanceID}" data-standbyid="${job.standby_attendanceID}" data-action="verify">Verify</button>
                          <button class="btn btn-danger action-btn" data-jobid="${job.job_attendanceID}" data-standbyid="${job.standby_attendanceID}" data-action="reject">Reject</button>
                      </div>
                  </div>
              `;
          });
          
          $("#cardsContainer").html(html);
      }

      // Function to load attendance data from the server
      // Fetches trip-specific attendance records that need verification
      function loadData() {
        $.ajax({
          url: "../controllers/attendanceVerifyController.php",
          dataType: "json",
          success: function (data) {
            if (data.error) {
              console.error("Error loading data:", data.error);
              alert("Error loading data: " + data.error);
              return;
            }
            renderCards(data.jobsToVerify || []);
          },
          error: function (xhr, status, error) {
            console.error("AJAX Error:", status, error);
            alert("Failed to load data. Check console for details.");
          }
        });
      }

      // Handle verify/reject button clicks
      // Each action is trip-specific and only affects the selected trip
      $(document).on("click", ".action-btn", function () {
        let btn = $(this);
        let job_attendanceID = btn.data("jobid");
        let standby_attendanceID = btn.data("standbyid");
        let action = btn.data("action");
        let actionText = action === "verify" ? "verify this attendance record" : "reject this attendance record";
        let confirmButtonText = action === "verify" ? "Yes, Verify" : "Yes, Reject";
        let confirmButtonColor = action === "verify" ? "#28a745" : "#dc3545";
        
        // Show confirmation dialog
        swal({
          title: "Are you sure?",
          text: `Do you want to ${actionText}?`,
          icon: "warning",
          buttons: {
            cancel: {
              text: "Cancel",
              visible: true,
              className: "btn btn-default"
            },
            confirm: {
              text: confirmButtonText,
              visible: true,
              className: action === "verify" ? "btn btn-success" : "btn btn-danger"
            }
          },
          dangerMode: action === "reject"
        }).then((willDo) => {
          if (willDo) {
            // Send verification/rejection request to server
            // This will only update the specific trip's attendance record
            $.ajax({
              url: "../controllers/attendanceVerifyController.php",
              method: "POST",
              dataType: "json",
              data: {
                job_attendanceID: job_attendanceID,
                standby_attendanceID: standby_attendanceID,
                action: action
              },
              success: function (response) {
                if (response.error) {
                  swal("Error", response.error, "error");
                } else {
                  // Reload data to show updated status
                  loadData();
                  swal("Success", `Attendance record has been ${action === "verify" ? "verified" : "rejected"}.`, "success");
                }
              },
              error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                swal("Error", "Failed to perform action. Check console for details.", "error");
              }
            });
          }
        });
      });

      // Initialize the page when document is ready
      $(function () {
        loadData();
      });
    </script>
  </body>
</html>
