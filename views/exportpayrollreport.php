<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 5) { // 5 = Accountant
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
    <title>Payroll Export - SubseaOps</title>
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
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/plugins.min.css" />
    <link rel="stylesheet" href="../assets/css/kaiadmin.min.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <style>
        .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-left: 10px; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .card-header { background-color: #f8f9fa; border-bottom: 1px solid rgba(0,0,0,.05); }
        .form-label { font-weight: 500; color: #495057; }
        .table th { white-space: nowrap; background-color: #f8f9fa; }
        .table td, .table th { vertical-align: middle; }
        .badge-month { background-color: #2e59d9; font-size: 0.85rem; padding: 0.35em 0.65em; }
        
        /* Approval Status Table Styles */
        .approval-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .approval-table thead th {
            background-color: #2e59d9;
            color: white;
            font-weight: 500;
            padding: 12px 15px;
            text-align: left;
        }
        .approval-table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .approval-table tbody tr:last-child td {
            border-bottom: none;
        }
        .approval-table tbody tr:nth-child(odd) {
            background-color: #f8f9fa;
        }
        .approval-table tbody tr:hover {
            background-color: #f1f3ff;
        }
        .approval-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-approved {
            background-color: #e6f7ee;
            color: #1f9254;
        }
        .status-pending {
            background-color: #fef7e6;
            color: #cd6200;
        }
        .approver-name {
            font-weight: 600;
            color: #1a237e;
        }
        .approval-date {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 3px;
        }
        .approval-icon {
            margin-right: 8px;
            font-size: 1.1em;
        }
        .progress-tracker {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .progress-tracker::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 4px;
            background-color: #e9ecef;
            z-index: 1;
        }
        .progress-step {
            position: relative;
            text-align: center;
            z-index: 2;
        }
        .step-bubble {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            border: 3px solid white;
        }
        .step-bubble.active {
            background-color: #2e59d9;
            color: white;
        }
        .step-bubble.completed {
            background-color: #1f9254;
            color: white;
        }
        .step-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        .step-label.active {
            color: #2e59d9;
            font-weight: 600;
        }
        .step-label.completed {
            color: #1f9254;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'components/accountantSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <?php include 'components/navbar.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Payroll Export</h3>
                    <!-- <ul class="breadcrumbs mb-3">
                        <li class="nav-home"><a href="accountantDashboard.php"><i class="icon-home"></i></a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Payroll Export</a></li>
                    </ul> -->
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-14 col-lg-14">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h4 class="card-title mb-0">Export Monthly Payroll</h4>
                                    <p class="text-muted mb-0">Review, export, and send monthly payroll to payroll team</p>
                                </div>
                                <span class="badge badge-month">
                                    <?= date('F Y') ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <form id="searchForm" class="row g-3 align-items-end">
                                    <div class="col-md-5">
                                        <label for="month" class="form-label">Select Month</label>
                                        <select name="month" id="month" class="form-select">
                                            <?php for ($m=1; $m<=12; $m++): 
                                                $monthName = date('F', mktime(0,0,0,$m,1));
                                            ?>
                                                <option value="<?= $monthName ?>" <?= $monthName == date('F') ? 'selected' : '' ?>>
                                                    <?= $monthName ?>
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
                                        <button type="submit" class="btn btn-primary" id="searchBtn">
                                            <i class="fas fa-search me-2"></i>
                                            <span>Search</span>
                                            <span id="loadingSpinner" class="loading" style="display:none;"></span>
                                        </button>
                                    </div>
                                </form>
                                <br/>
                                <div id="approvalStatusContainer"></div>
                                <div id="payrollTableContainer"></div>
                                <div class="row mt-3" id="exportControls" style="display:none;">
                                    <div class="col-md-12 d-flex align-items-center gap-2">
                                        <select id="exportFileType" class="form-select w-auto">
                                            <option value="csv">CSV</option>
                                            <option value="pdf">PDF</option>
                                            <option value="docx">DOCX</option>
                                            <option value="xlsx">XLSX</option>
                                        </select>
                                        <button class="btn btn-success" id="exportBtn" disabled>
                                            <i class="fas fa-file-export me-2"></i>Export Report
                                        </button>
                                        <button class="btn btn-info" id="sendPayrollBtn" disabled>
                                            <i class="fas fa-paper-plane me-2"></i>Send Monthly Bill to Payroll
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'components/footer.php'; ?>
    </div>
</div>
<script src="../assets/js/core/jquery-3.7.1.min.js"></script>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
<script src="../assets/js/kaiadmin.min.js"></script>
<!-- SweetAlert CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
function renderApprovalStatus(approvals) {
    // Progress tracker
    let progressHtml = `
        <div class="progress-tracker">
            <div class="progress-step">
                <div class="step-bubble ${approvals.accountant ? 'completed' : 'active'}">1</div>
                <div class="step-label ${approvals.accountant ? 'completed' : 'active'}">Accountant Review</div>
            </div>
            <div class="progress-step">
                <div class="step-bubble ${approvals.director ? 'completed' : (approvals.accountant ? 'active' : '')}">2</div>
                <div class="step-label ${approvals.director ? 'completed' : (approvals.accountant ? 'active' : '')}">Director Approval</div>
            </div>
        </div>
    `;
    
    // Approval table
    let tableHtml = `
        <div class="table-responsive">
            <table class="approval-table">
                <thead>
                    <tr>
                        <th>Approval Stage</th>
                        <th>Status</th>
                        <th>Approved By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <i class="fas fa-user-tie approval-icon"></i>
                            Accountant Review
                        </td>
                        <td>
                            ${approvals.accountant ? 
                                '<span class="approval-status status-approved"><i class="fas fa-check-circle"></i> Approved</span>' : 
                                '<span class="approval-status status-pending"><i class="fas fa-clock"></i> Pending</span>'}
                        </td>
                        <td>
                            ${approvals.accountant ? 
                                `<span class="approver-name">${approvals.accountant.fname} ${approvals.accountant.lname}</span>` : 
                                'Not approved yet'}
                        </td>
                        <td>
                            ${approvals.accountant ? 
                                `<span class="approval-date">${formatDate(approvals.accountant.date)}</span>` : 
                                '-'}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <i class="fas fa-user-crown approval-icon"></i>
                            Director Approval
                        </td>
                        <td>
                            ${approvals.director ? 
                                '<span class="approval-status status-approved"><i class="fas fa-check-circle"></i> Approved</span>' : 
                                '<span class="approval-status status-pending"><i class="fas fa-clock"></i> Pending</span>'}
                        </td>
                        <td>
                            ${approvals.director ? 
                                `<span class="approver-name">${approvals.director.fname} ${approvals.director.lname}</span>` : 
                                'Not approved yet'}
                        </td>
                        <td>
                            ${approvals.director ? 
                                `<span class="approval-date">${formatDate(approvals.director.date)}</span>` : 
                                '-'}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    return progressHtml + tableHtml;
}

// Helper to format date as e.g. 2024-06-01 => Jun 1, 2024
function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function renderPaymentsTable(payments, totals) {
    if (!payments.length) return '<div class="alert alert-info">No payments found for this month/year.</div>';
    let html = '<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr>';
    const headers = ['Employee', 'Job Allowance', 'Job Meal', 'Standby Attendance', 'Standby Meal', 'Report Prep', 'Total Diving', 'Date'];
    headers.forEach(h => html += `<th>${h}</th>`);
    html += '</tr></thead><tbody>';
    payments.forEach(row => {
        html += '<tr>';
        html += `<td>${row.fname} ${row.lname}</td>`;
        html += `<td>${parseFloat(row.jobAllowance).toFixed(2)}</td>`;
        html += `<td>${parseFloat(row.jobMealAllowance).toFixed(2)}</td>`;
        html += `<td>${parseFloat(row.standbyAttendanceAllowance).toFixed(2)}</td>`;
        html += `<td>${parseFloat(row.standbyMealAllowance).toFixed(2)}</td>`;
        html += `<td>${parseFloat(row.reportPreparationAllowance).toFixed(2)}</td>`;
        html += `<td>${parseFloat(row.totalDivingAllowance).toFixed(2)}</td>`;
        html += `<td>${row.date_time}</td>`;
        html += '</tr>';
    });
    html += '</tbody><tfoot><tr style="font-weight:bold;background:#e9ecef;"><td>Monthly Total</td><td></td><td></td><td></td><td></td><td></td>';
    html += `<td>${parseFloat(totals.totalDivingAllowance).toFixed(2)}</td><td></td></tr></tfoot></table></div>`;
    return html;
}

function fetchPayrollData(month, year) {
    $('#loadingSpinner').show();
    $.get('../controllers/exportPayrollReportController.php', { month, year }, function(data) {
        let res = {};
        try { res = JSON.parse(data); } catch (e) { res = {}; }
        $('#loadingSpinner').hide();
        if (res.approvals) {
            $('#approvalStatusContainer').html(renderApprovalStatus(res.approvals));
        }
        if (res.payments) {
            $('#payrollTableContainer').html(renderPaymentsTable(res.payments, res.totals));
            if (res.payments.length > 0) {
                $('#exportControls').show();
            } else {
                $('#exportControls').hide();
            }
        } else {
            $('#exportControls').hide();
        }
        // Enable/disable export/send buttons
        if (res.allApproved) {
            $('#exportBtn').prop('disabled', false);
            $('#sendPayrollBtn').prop('disabled', false);
        } else {
            $('#exportBtn').prop('disabled', true);
            $('#sendPayrollBtn').prop('disabled', true);
        }
    });
}

$(document).ready(function() {
    fetchPayrollData($('#month').val(), $('#year').val());
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        fetchPayrollData($('#month').val(), $('#year').val());
    });
    $('#exportBtn').on('click', function() {
        var month = $('#month').val();
        var year = $('#year').val();
        var fileType = $('#exportFileType').val();
        window.location.href = '../controllers/exportPayrollReportController.php?action=export&month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year) + '&fileType=' + encodeURIComponent(fileType);
    });
    $('#sendPayrollBtn').on('click', function() {
        console.log('Button clicked!'); // Debug log
        var month = $('#month').val();
        var year = $('#year').val();
        var btn = $(this);
        
        console.log('Month:', month, 'Year:', year); // Debug log
        
        // Check if SweetAlert is available
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert is not loaded!');
            alert('SweetAlert not loaded. Using fallback alert.');
            if (confirm('Send Monthly Bill to Payroll?')) {
                btn.prop('disabled', true).html('Sending...');
                $.post('../controllers/exportPayrollReportController.php', { action: 'email', month, year }, function(response) {
                    if (response === 'success') {
                        alert('Sent Successfully!');
                    } else {
                        alert('Error: ' + response);
                    }
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Send Monthly Bill to Payroll');
                });
            }
            return;
        }
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Send Monthly Bill to Payroll?',
            text: `Are you sure you want to send the payroll report for ${month} ${year} to the payroll team?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, send it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('Sending...');
                $.post('../controllers/exportPayrollReportController.php', { action: 'email', month, year }, function(response) {
                    if (response === 'success') {
                        Swal.fire(
                            'Sent Successfully!',
                            'The payroll report has been sent to the payroll team.',
                            'success'
                        );
                    } else {
                        Swal.fire(
                            'Error!',
                            'Failed to send payroll report: ' + response,
                            'error'
                        );
                    }
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Send Monthly Bill to Payroll');
                });
            }
        });
    });
});
</script>
</body>
</html>