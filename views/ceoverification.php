<?php
session_start();
include '../config/dbConnect.php';
// Check if user is logged in and has CEO role (adjust roleID as needed)
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 6) {
    header("Location: ../index.php?error=access_denied");
    exit();
}
$currentMonth = date('F');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>CEO Payment Verification | WOSS Trip Bonus System</title>
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
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/plugins.min.css" />
    <link rel="stylesheet" href="../assets/css/kaiadmin.min.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <style>
      .card {
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      }
      .table th, .table td {
        vertical-align: middle;
      }
      .verify-btn {
        min-width: 220px;
        font-size: 1rem;
        border-radius: 8px;
      }
      .custom-success-bg {
        background-color: #eafaf1 !important;
        border-color: #b7eedc !important;
        color: #155724 !important;
      }
    
      .notify-success {
        background-color: #fff3cd !important;
        border-color: #ffeaa7 !important;
        color: #856404 !important;
      }
      .bottom-notification {
        position: fixed;
        left: 50%;
        bottom: 30px;
        transform: translateX(-50%);
        z-index: 9999;
        min-width: 350px;
        max-width: 90vw;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        border-radius: 8px;
        display: none;
      }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'components/ceoSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <div class="main-header-logo">
                <div class="logo-header" data-background-color="dark">
                    <a href="../index.html" class="logo">
                        <img src="../assets/img/Logo_white.png" alt="navbar brand" class="navbar-brand" height="20" />
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar"><i class="gg-menu-right"></i></button>
                        <button class="btn btn-toggle sidenav-toggler"><i class="gg-menu-left"></i></button>
                    </div>
                    <button class="topbar-toggler more"><i class="gg-more-vertical-alt"></i></button>
                </div>
            </div>
            <?php include 'components/navbar.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                    <div>
                        <h3 class="fw-bold mb-3">CEO Payment Verification</h3>
                        <h6 class="op-7 mb-2">Verify all payments for a selected month and year</h6>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <form id="searchForm" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="month" class="form-label">Month</label>
                                <select id="month" class="form-select" required>
                                    <option value="">Select Month</option>
                                    <?php
                                    $months = [
                                        'January', 'February', 'March', 'April', 'May', 'June',
                                        'July', 'August', 'September', 'October', 'November', 'December'
                                    ];
                                    foreach ($months as $m) {
                                        $selected = ($m == $currentMonth) ? 'selected' : '';
                                        echo "<option value=\"$m\" $selected>$m</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="year" class="form-label">Year</label>
                                <select id="year" class="form-select" required>
                                    <option value="">Select Year</option>
                                    <?php for ($y=$currentYear; $y>=2020; $y--): ?>
                                        <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div id="ceoStatus"></div>
                <!-- Monthly Totals and Previous Approver Details -->
                <div id="monthlyTotalsContainer"></div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0" id="paymentsTable" style="display:none;">
                                <thead class="table-light">
                                    <tr>
                                        <!-- <th>Payment ID</th>
                                        <th>Employee ID</th> -->
                                        <th>Employee Name</th>
                                        <th>Job Count</th>
                                        <th>Standby Attendance Count</th>
                                        <th>Job Allowance</th>
                                        <th>Job Meal Allowance</th>
                                        <th>Standby Attendance Allowance</th>
                                        <th>Standby Meal Allowance</th>
                                        <th>Report Preparation Allowance</th>
                                        <th>Total Diving Allowance</th>
                                        <th>Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Payments will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button id="rejectBtn" class="btn btn-danger verify-btn me-2" style="display:none;">
                                <i class="fas fa-times-circle me-2"></i>Reject All Payments for This Month
                            </button>
                            <button id="verifyBtn" class="btn btn-success verify-btn" style="display:none;">
                                <i class="fas fa-check-circle me-2"></i>Verify All Payments for This Month
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Loading Overlay -->
<div id="loadingOverlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:99999;background:rgba(255,255,255,0.7);align-items:center;justify-content:center;">
  <div style="text-align:center;">
    <div class="spinner-border text-primary" style="width:3rem;height:3rem;" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <div style="margin-top:12px;font-weight:500;color:#333;">Processing, please wait...</div>
  </div>
</div>

<div id="bottomNotification" class="bottom-notification"></div>
<!-- Sweet Alert -->
<script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>
<script>
let currentMonth = '';
let currentYear = '';

// Function to fetch and display payments
function fetchPayments(month, year) {
    currentMonth = month;
    currentYear = year;
    fetch(`../controllers/ceoVerificationController.php?month=${month}&year=${year}`)
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#paymentsTable tbody');
            tbody.innerHTML = '';
            if (data.payments.length > 0) {
                data.payments.forEach(payment => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${(payment.fname || '') + ' ' + (payment.lname || '')}</td>
                            <td>${payment.jobCount || 0}</td>
                            <td>${payment.standbyCount || 0}</td>
                            <td>${payment.jobAllowance}</td>
                            <td>${payment.jobMealAllowance}</td>
                            <td>${payment.standbyAttendanceAllowance}</td>
                            <td>${payment.standbyMealAllowance}</td>
                            <td>${payment.reportPreparationAllowance}</td>
                            <td>${payment.totalDivingAllowance}</td>
                            <td>${payment.date_time}</td>
                        </tr>
                    `;
                });
                document.getElementById('paymentsTable').style.display = '';
            } else {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center">No payments found for this period.</td></tr>';
                document.getElementById('paymentsTable').style.display = '';
            }

            // CEO verification status
            const ceoStatus = document.getElementById('ceoStatus');
            const verifyBtn = document.getElementById('verifyBtn');
            const rejectBtn = document.getElementById('rejectBtn');
            if (data.ceoVerified) {
                ceoStatus.innerHTML = '<div class="alert alert-success notify-success mt-3"><i class="fas fa-check-circle me-2"></i>This month has already been CEO verified.</div>';
                verifyBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
            } else if (data.payments.length > 0) {
                ceoStatus.innerHTML = '';
                verifyBtn.style.display = '';
                rejectBtn.style.display = '';
            } else {
                ceoStatus.innerHTML = '';
                verifyBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
            }

            // Monthly Totals and Approver Details
            const totals = data.monthlyTotals || {};
            const approver = data.approver;
            let totalsHtml = '';
            if (Object.keys(totals).length > 0) {
                totalsHtml += `
                <div class="card card-body bg-light mb-3">
                  <h5 class="mb-2">Monthly Totals</h5>
                  <div class="row">
                    <div class="col-md-4"><strong>Job Allowance:</strong> ${Number(totals.totalJobAllowance || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                    <div class="col-md-4"><strong>Job Meal Allowance:</strong> ${Number(totals.totalJobMealAllowance || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                    <div class="col-md-4"><strong>Standby Attendance Allowance:</strong> ${Number(totals.totalStandbyAttendanceAllowance || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                  </div>
                  <div class="row">
                    <div class="col-md-4"><strong>Standby Meal Allowance:</strong> ${Number(totals.totalStandbyMealAllowance || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                    <div class="col-md-4"><strong>Report Preparation Allowance:</strong> ${Number(totals.totalReportPreparationAllowance || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                    <div class="col-md-4"><strong>Total Diving Allowance:</strong> ${Number(totals.totalDivingAllowance || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                  </div>
                </div>
                `;
            }
            // if (approver && approver.fname) {
            //     totalsHtml += `
            //     <div class="alert alert-info mb-3">
            //       <i class="fas fa-user-check me-2"></i>
            //       <strong>Previous Approver:</strong> ${approver.fname} ${approver.lname ? approver.lname : ''} <br/>
            //       <strong>Verified Date:</strong> ${approver.paymentVerifyDate ? approver.paymentVerifyDate : '-'}
            //     </div>
            //     `;
            // }
            document.getElementById('monthlyTotalsContainer').innerHTML = totalsHtml;
        });
}

// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    if (month && year) {
        fetchPayments(month, year);
    }
});

document.getElementById('searchForm').onsubmit = function(e) {
    e.preventDefault();
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    fetchPayments(month, year);
};

function showBottomNotification(message, type = 'success') {
  const notif = document.getElementById('bottomNotification');
  let icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
  let alertClass = type === 'success' ? 'alert-success custom-success-bg' : 'alert-danger';
  let heading = type === 'success' ? 'Payments Verified Successfully' : 'Payments Rejected';
  notif.innerHTML = `<div class="alert ${alertClass} d-flex align-items-center mb-0">
    <i class="fas ${icon} me-3 fs-4"></i>
    <div>
      <h5 class="alert-heading mb-1">${heading}</h5>
      <p class="mb-0">${message}</p>
    </div>
  </div>`;
  notif.style.display = 'block';
  setTimeout(() => {
    notif.style.display = 'none';
  }, 4000);
}

document.getElementById('verifyBtn').onclick = function() {
    if (!currentMonth || !currentYear) return;
    
    swal({
        title: 'Are you sure?',
        text: 'Do you want to verify all payments for ' + currentMonth + ' ' + currentYear + '?',
        icon: 'warning',
        buttons: {
            cancel: {
                text: 'Cancel',
                visible: true,
                className: 'btn btn-default'
            },
            confirm: {
                text: 'Yes, Verify',
                visible: true,
                className: 'btn btn-success'
            }
        }
    }).then(function(value) {
        if (value) {
            showLoader(); // <-- Show loader
            fetch('../controllers/ceoVerificationController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ month: currentMonth, year: currentYear, action: 'verify' })
            })
            .then(res => res.json())
            .then(resp => {
                hideLoader(); // <-- Hide loader
                if (resp.success) {
                    showBottomNotification('Payments for <b>' + currentMonth + ' ' + currentYear + '</b> have been verified and recorded.');
                    document.getElementById('verifyBtn').style.display = 'none';
                    document.getElementById('rejectBtn').style.display = 'none';
                    document.getElementById('ceoStatus').innerHTML = '<div class="alert alert-success notify-success mt-3"><i class="fas fa-check-circle me-2"></i>This month has now been verified.</div>';
                } else {
                    swal('Error', 'Verification failed!', 'error');
                }
            })
            .catch(() => hideLoader()); // Ensure loader is hidden on error
        }
    });
};

document.getElementById('rejectBtn').onclick = function() {
    if (!currentMonth || !currentYear) return;
    
    swal({
        title: 'Are you sure?',
        text: 'Do you want to reject all payments for ' + currentMonth + ' ' + currentYear + '? Please provide a reason.',
        content: {
            element: "textarea",
            attributes: {
                placeholder: "Enter rejection reason...",
                id: "rejectComment",
                rows: 4
            },
        },
        buttons: {
            cancel: {
                text: 'Cancel',
                visible: true,
                className: 'btn btn-default'
            },
            confirm: {
                text: 'Yes, Reject',
                visible: true,
                className: 'btn btn-danger'
            }
        },
        dangerMode: true
    }).then(function(value) {
        if (value) {
            var comment = document.getElementById('rejectComment').value;
            if (!comment || comment.trim() === "") {
                swal('Error', 'Please provide a rejection reason.', 'error');
                return;
            }
            showLoader(); // <-- Show loader
            fetch('../controllers/ceoVerificationController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    month: currentMonth, 
                    year: currentYear, 
                    action: 'reject',
                    comment: comment 
                })
            })
            .then(res => res.json())
            .then(resp => {
                hideLoader(); // <-- Hide loader
                if (resp.success) {
                    showBottomNotification('Payments for <b>' + currentMonth + ' ' + currentYear + '</b> have been rejected and marked as not verified.', 'danger');
                    document.getElementById('verifyBtn').style.display = 'none';
                    document.getElementById('rejectBtn').style.display = 'none';
                    document.getElementById('ceoStatus').innerHTML = '<div class="alert alert-danger mt-3"><i class="fas fa-times-circle me-2"></i>This month has been CEO rejected.</div>';
                } else {
                    swal('Error', 'Rejection failed!', 'error');
                }
            })
            .catch(() => hideLoader()); // Ensure loader is hidden on error
        }
    });
};

function showLoader() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}
function hideLoader() {
    document.getElementById('loadingOverlay').style.display = 'none';
}
</script>
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
</body>
</html>
