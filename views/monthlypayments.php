<?php
// Session and access control
session_start();
include '../config/dbConnect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Get current month and year
$currentMonth = date('n');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Publish and Finalize Monthly Job | Admin Portal</title>
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
    <!-- Export libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.05);
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .results-summary {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .total-highlight {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2e59d9;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table th {
            white-space: nowrap;
            position: relative;
            background-color: #f8f9fa;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .badge-month {
            background-color: #2e59d9;
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .custom-success-bg {
            background-color: #eafaf1 !important;
            border-color: #b7eedc !important;
            color: #155724 !important;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>
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
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Publish and Finalize Monthly Jobs</h3>
                    <!-- <ul class="breadcrumbs mb-3">
                        <li class="nav-home"><a href="dashboard.php"><i class="icon-home"></i></a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="payments.php">Payments</a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Monthly Calculation</a></li>
                    </ul> -->
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-14 col-lg-14">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h4 class="card-title mb-0">Publish and Finalize Monthly Jobs</h4>
                                    <p class="text-muted mb-0">Publish and finalize monthly job payments for employees</p>
                                </div>
                                <span class="badge badge-month">
                                    <?= date('F Y') ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <!-- Move the form above the summary section -->
                                <form id="paymentForm" class="row g-3 align-items-end">
                                    <div class="col-md-5">
                                        <label for="month" class="form-label">Select Month</label>
                                        <select name="month" id="month" class="form-select">
                                            <?php for ($m=1; $m<=12; $m++): ?>
                                                <option value="<?= $m ?>" <?= $m == $currentMonth ? 'selected' : '' ?>>
                                                    <?= date('F', mktime(0,0,0,$m,1)) ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="year" class="form-label">Select Year</label>
                                        <select name="year" id="year" class="form-select">
                                            <?php for ($y=$currentYear; $y>=2020; $y--): ?>
                                                <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>
                                                    <?= $y ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <button type="submit" class="btn btn-primary" id="publishBtn">
                                            <i class="fas fa-bullhorn me-2"></i>
                                            <span>Publish</span>
                                            <span id="loadingSpinner" class="loading" style="display:none;"></span>
                                        </button>
                                    </div>
                                </form>
                                <br/>
                                <div id="summarySection" class="mb-4"></div>
                                <div id="results" class="mt-4"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'components/footer.php'; ?>
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
<!--   Core JS Files   -->
<script src="../assets/js/core/jquery-3.7.1.min.js"></script>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
<script src="../assets/js/kaiadmin.min.js"></script>
<script src="../assets/js/setting-demo2.js"></script>
<!-- Sweet Alert -->
<script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>
<script>
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function getMonthName(monthNumber) {
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
    return months[parseInt(monthNumber) - 1];
}

async function fetchSummaryAndPending() {
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    const summarySection = document.getElementById('summarySection');
    const publishBtn = document.getElementById('publishBtn');
    summarySection.innerHTML = `<div class='text-center'><span class='loading'></span> Loading summary...</div>`;
    publishBtn.disabled = true;
    try {
        const formData = new FormData();
        formData.append('month', month);
        formData.append('year', year);
        const response = await fetch('../controllers/getMonthlySummaryController.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Failed to fetch summary');
        // Build summary card
        let html = `<div class="card results-summary shadow-sm mb-3">
            <div class="row">
                <div class="col-md-3">
                    <div class="fw-bold">Total Employees</div>
                    <div class="fs-4 text-primary">${result.summary.totalEmployees}</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">Total Jobs</div>
                    <div class="fs-4 text-success">${result.summary.totalJobs}</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">Job Types</div>
                    <div>${result.summary.jobTypes.length > 0 ? result.summary.jobTypes.map(jt => `<span class='badge bg-info text-dark me-1 mb-1'>${jt}</span>`).join(' ') : '-'}</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">Employee Names</div>
                    <div style="max-height: 60px; overflow-y: auto;">${result.summary.employeeNames.length > 0 ? result.summary.employeeNames.map(n => `<span class='badge bg-secondary me-1 mb-1'>${n}</span>`).join(' ') : '-'}</div>
                </div>
            </div>
        </div>`;
        // Job type wise job counts
        if (result.summary.jobTypeCounts && result.summary.jobTypeCounts.length > 0) {
            html += `<div class="mb-3">
                <h6 class="fw-bold mb-2">Job Type Wise Job Counts</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Job Type</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                        ${result.summary.jobTypeCounts.map(jt => `
                            <tr>
                                <td>${jt.type_name}</td>
                                <td>${jt.count}</td>
                            </tr>
                        `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>`;
        }
        // Approved jobs
        if (result.summary.approvedJobs && result.summary.approvedJobs.length > 0) {
            html += `<div class="mb-3">
                <h6 class="fw-bold mb-2 text-success"><i class="fas fa-check-circle me-1"></i>Approved Jobs</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Job ID</th>
                                <th>Job Type</th>
                                <th>Start Date</th>
                                <th>Total Days</th>
                                <th>Employees</th>
                                <th>Trip Details</th>
                            </tr>
                        </thead>
                        <tbody>
                        ${result.summary.approvedJobs.map(job => `
                            <tr>
                                <td>${job.jobID}</td>
                                <td>${job.job_type_name || '-'}</td>
                                <td>${job.start_date ? job.start_date.split(' ')[0] : '-'}</td>
                                <td><span class="badge bg-info">${job.trip_count || 0} days</span></td>
                                <td>${job.employees && job.employees.length > 0 ? job.employees.map(e => `<span class='badge bg-secondary me-1 mb-1'>${e.empName}</span>`).join(' ') : '-'}</td>
                                <td>
                                    ${job.trips && job.trips.length > 0 ? 
                                        `<div class="small">
                                            ${job.trips.map(trip => `
                                                <div class="mb-1">
                                                    <strong>Trip</strong> (${trip.trip_date ? trip.trip_date.split(' ')[0] : 'N/A'}): 
                                                    ${trip.employees && trip.employees.length > 0 ? 
                                                        trip.employees.map(e => `<span class='badge bg-light text-dark me-1'>${e.empName}</span>`).join(' ') : 
                                                        'No employees assigned'
                                                    }
                                                </div>
                                            `).join('')}
                                        </div>` : 
                                        'No trips found'
                                    }
                                </td>
                            </tr>
                        `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>`;
        }
        // Rejected jobs
        if (result.summary.rejectedJobs && result.summary.rejectedJobs.length > 0) {
            html += `<div class="mb-3">
                <h6 class="fw-bold mb-2 text-danger"><i class="fas fa-times-circle me-1"></i>Rejected Jobs</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-danger">
                            <tr>
                                <th>Job ID</th>
                                <th>Job Type</th>
                                <th>Start Date</th>
                                <th>Total Days</th>
                                <th>Employees</th>
                                <th>Trip Details</th>
                            </tr>
                        </thead>
                        <tbody>
                        ${result.summary.rejectedJobs.map(job => `
                            <tr>
                                <td>${job.jobID}</td>
                                <td>${job.job_type_name || '-'}</td>
                                <td>${job.start_date ? job.start_date.split(' ')[0] : '-'}</td>
                                <td><span class="badge bg-info">${job.trip_count || 0} days</span></td>
                                <td>${job.employees && job.employees.length > 0 ? job.employees.map(e => `<span class='badge bg-secondary me-1 mb-1'>${e.empName}</span>`).join(' ') : '-'}</td>
                                <td>
                                    ${job.trips && job.trips.length > 0 ? 
                                        `<div class="small">
                                            ${job.trips.map(trip => `
                                                <div class="mb-1">
                                                    <strong>Trip ${trip.tripID}</strong> (${trip.trip_date ? trip.trip_date.split(' ')[0] : 'N/A'}): 
                                                    ${trip.employees && trip.employees.length > 0 ? 
                                                        trip.employees.map(e => `<span class='badge bg-light text-dark me-1'>${e.empName}</span>`).join(' ') : 
                                                        'No employees assigned'
                                                    }
                                                </div>
                                            `).join('')}
                                        </div>` : 
                                        'No trips found'
                                    }
                                </td>
                            </tr>
                        `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>`;
        }
        // Pending approvals
        if (result.pendingJobs.length > 0) {
            html += `<div class="alert alert-warning shadow-sm">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
                    <div>
                        <strong>Pending Job Approvals:</strong> You must approve all jobs before publishing payments for <b>${getMonthName(month)} ${year}</b>.
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Job ID</th>
                                <th>Job Type</th>
                                <th>Start Date</th>
                                <th>Total Days</th>
                                <th>Assigned Employees</th>
                                <th>Trip Details</th>
                            </tr>
                        </thead>
                        <tbody>
                        ${result.pendingJobs.map(job => `
                            <tr>
                                <td>${job.jobID}</td>
                                <td>${job.job_type_name || '-'}</td>
                                <td>${job.start_date ? job.start_date.split(' ')[0] : '-'}</td>
                                <td><span class="badge bg-info">${job.trip_count || 0} days</span></td>
                                <td>${job.employees && job.employees.length > 0 ? job.employees.map(e => `<span class='badge bg-secondary me-1 mb-1'>${e.empName}</span>`).join(' ') : '-'}</td>
                                <td>
                                    ${job.trips && job.trips.length > 0 ? 
                                        `<div class="small">
                                            ${job.trips.map(trip => `
                                                <div class="mb-1">
                                                    <strong>Trip ${trip.tripID}</strong> (${trip.trip_date ? trip.trip_date.split(' ')[0] : 'N/A'}): 
                                                    ${trip.employees && trip.employees.length > 0 ? 
                                                        trip.employees.map(e => `<span class='badge bg-light text-dark me-1'>${e.empName}</span>`).join(' ') : 
                                                        'No employees assigned'
                                                    }
                                                </div>
                                            `).join('')}
                                        </div>` : 
                                        'No trips found'
                                    }
                                </td>
                            </tr>
                        `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>`;
            publishBtn.disabled = true;
        } else {
            publishBtn.disabled = false;
        }
        summarySection.innerHTML = html;
    } catch (error) {
        summarySection.innerHTML = `<div class='alert alert-danger'>${error.message}</div>`;
        publishBtn.disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in republish mode
    const republishMode = sessionStorage.getItem('republishMode');
    const republishMonth = sessionStorage.getItem('republishMonth');
    const republishYear = sessionStorage.getItem('republishYear');
    
    if (republishMode === 'true' && republishMonth && republishYear) {
        // Convert month name to month number
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                           'July', 'August', 'September', 'October', 'November', 'December'];
        const monthNumber = monthNames.indexOf(republishMonth) + 1;
        
        // Set the form values
        document.getElementById('month').value = monthNumber;
        document.getElementById('year').value = republishYear;
        
        // Clear the session storage
        sessionStorage.removeItem('republishMode');
        sessionStorage.removeItem('republishMonth');
        sessionStorage.removeItem('republishYear');
        
        // Show a notification
        swal({
            title: 'Republish Mode',
            text: `You are republishing payments for ${republishMonth} ${republishYear}. Please review and publish the payments.`,
            icon: 'info',
            buttons: {
                confirm: {
                    text: 'OK',
                    className: 'btn btn-primary'
                }
            }
        });
    }
    
    fetchSummaryAndPending();
});

document.getElementById('month').addEventListener('change', fetchSummaryAndPending);
document.getElementById('year').addEventListener('change', fetchSummaryAndPending);

document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    
    // Show confirmation dialog
    const result = await swal({
        title: 'Are you sure?',
        text: `You are about to publish and finalize monthly payments for ${getMonthName(month)} ${year}. This action cannot be undone.`,
        icon: 'warning',
        buttons: {
            cancel: {
                text: 'Cancel',
                value: false,
                visible: true,
                className: 'btn btn-secondary',
                closeModal: true,
            },
            confirm: {
                text: 'Yes, Publish Payments',
                value: true,
                visible: true,
                className: 'btn btn-primary',
                closeModal: true
            }
        },
        dangerMode: true,
    });
    
    if (!result) {
        return; // User cancelled
    }
    
    const publishBtn = document.getElementById('publishBtn');
    const spinner = document.getElementById('loadingSpinner');
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = '';
    // Show loading state
    publishBtn.disabled = true;
    spinner.style.display = 'inline-block';
    showLoader(); // <-- Show overlay loader
    try {
        const formData = new FormData(this);
        const response = await fetch('../controllers/calculateMonthlyPaymentsController.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Server returned an error');
        }
        resultsDiv.innerHTML = `
            <div class="alert alert-success d-flex align-items-center custom-success-bg">
                <i class="fas fa-check-circle me-3 fs-4"></i>
                <div>
                    <h5 class="alert-heading mb-1">Payments Published Successfully</h5>
                    <p class="mb-0">Payments for <b>${getMonthName(month)} ${year}</b> have been published and recorded.</p>
                </div>
            </div>`;
        fetchSummaryAndPending(); // Refresh summary after publish
    } catch (error) {
        resultsDiv.innerHTML = `
            <div class="alert alert-danger d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-3 fs-4"></i>
                <div>
                    <h5 class="alert-heading mb-1">Error Publishing Payments</h5>
                    <p class="mb-0">${error.message}</p>
                </div>
            </div>`;
    } finally {
        publishBtn.disabled = false;
        spinner.style.display = 'none';
        hideLoader(); // <-- Hide overlay loader
    }
});
function showLoader() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}
function hideLoader() {
    document.getElementById('loadingOverlay').style.display = 'none';
}
</script>
</body>
</html>