<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
$currentMonth = date('n');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Payment Status - SubseaOps</title>
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
        .status-pending { background-color: #ffc107; color: #000; }
        .status-verified { background-color: #28a745; color: #fff; }
        .status-rejected { background-color: #dc3545; color: #fff; }
        .rejection-reason { background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 0.25rem; padding: 0.75rem; margin-top: 0.5rem; }
        .table th { font-weight: 600; text-align: center; }
        .table td { text-align: center; }
        .table td:first-child { text-align: left; }
        .verifier-details { font-size: 0.9rem; }
        .verifier-details strong { color: #495057; }
        .verifier-details small { color: #6c757d; }
        .action-buttons { white-space: nowrap; }
        .action-buttons .btn { margin: 2px; }
        .table-responsive { overflow-x: auto; }
        .table th, .table td { min-width: 120px; }
        .table th:first-child, .table td:first-child { min-width: 150px; }
        .table th:nth-child(2), .table td:nth-child(2) { min-width: 140px; }
        .action-buttons { min-width: 200px; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <?php include 'components/navbar.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Payment Status</h3>
                    <!-- <ul class="breadcrumbs mb-3">
                        <li class="nav-home"><a href="adminDashboard.php"><i class="icon-home"></i></a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Payment Status</a></li>
                    </ul> -->
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-14 col-lg-14">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h4 class="card-title mb-0">Published Payment Status</h4>
                                    <p class="text-muted mb-0">View status of your published monthly payments</p>
                                </div>
                                <span class="badge badge-month">
                                    <?= date('F Y') ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <form id="searchForm" class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label for="month" class="form-label">Select Month</label>
                                        <select name="month" id="month" class="form-select">
                                            <option value="">All Months</option>
                                            <?php for ($m=1; $m<=12; $m++): 
                                                $monthName = date('F', mktime(0,0,0,$m,1));
                                            ?>
                                                <option value="<?= $monthName ?>" <?= $monthName == date('F') ? 'selected' : '' ?>>
                                                    <?= $monthName ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="year" class="form-label">Select Year</label>
                                        <select name="year" id="year" class="form-select">
                                            <option value="">All Years</option>
                                            <?php for ($y=$currentYear; $y>=2020; $y--): ?>
                                                <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>
                                                    <?= $y ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-grid">
                                        <button type="submit" class="btn btn-primary" id="searchBtn">
                                            <i class="fas fa-search me-2"></i>
                                            <span>Search</span>
                                            <span id="loadingSpinner" class="loading" style="display:none;"></span>
                                        </button>
                                    </div>
                                </form>
                                <br/>
                                <div id="statusTableContainer"></div>
                                
                                <!-- Payment History Section -->
                                <div class="mt-5">
                                    <h5 class="fw-bold mb-3">
                                        <i class="fas fa-history me-2"></i>Payment History
                                    </h5>
                                    <div id="historyTableContainer"></div>
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
<!-- Sweet Alert -->
<script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>
<script>
function fetchPaymentStatus(month, year) {
    $('#loadingSpinner').show();
    $.get('../controllers/paymentStatusController.php', { month, year }, function(data) {
        $('#statusTableContainer').html(data);
        $('#loadingSpinner').hide();
    });
    
    // Also fetch payment history
    fetchPaymentHistory(month, year);
}

function fetchPaymentHistory(month, year) {
    $.get('../controllers/paymentHistoryController.php', { month, year }, function(data) {
        $('#historyTableContainer').html(data);
    });
}

$(document).ready(function() {
    fetchPaymentStatus($('#month').val(), $('#year').val());
    
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        fetchPaymentStatus($('#month').val(), $('#year').val());
    });
    
    $(document).on('click', '.republishBtn', function() {
        var month = $(this).data('month');
        var year = $(this).data('year');
        var btn = $(this);
        
        swal({
            title: 'Are you sure?',
            text: 'Do you want to republish payments for ' + month + ' ' + year + '? This will redirect you to the monthly payments page to republish.',
            icon: 'warning',
            buttons: {
                cancel: {
                    text: 'Cancel',
                    visible: true,
                    className: 'btn btn-default'
                },
                confirm: {
                    text: 'Yes, Republish',
                    visible: true,
                    className: 'btn btn-success'
                }
            },
            dangerMode: false
        }).then(function(willDo) {
            if (willDo) {
                // First, call the republish action to clean up existing data
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Preparing...');
                
                $.post('../controllers/paymentStatusController.php', { 
                    action: 'republish', 
                    month: month, 
                    year: year 
                }, function(response) {
                    if (response === 'success') {
                        // Store the month and year in sessionStorage for the monthlyPayments page
                        sessionStorage.setItem('republishMonth', month);
                        sessionStorage.setItem('republishYear', year);
                        sessionStorage.setItem('republishMode', 'true');
                        
                        // Redirect to monthlyPayments.php
                        window.location.href = 'monthlypayments.php';
                    } else {
                        btn.prop('disabled', false).html('<i class="fas fa-redo me-1"></i>Republish');
                        swal('Error', 'Republishing failed: ' + response, 'error');
                    }
                }).fail(function(xhr, status, error) {
                    btn.prop('disabled', false).html('<i class="fas fa-redo me-1"></i>Republish');
                    swal('Error', 'Republishing failed: ' + error, 'error');
                });
            }
        });
    });
    
    $(document).on('click', '.viewRejectionBtn', function() {
        var comment = $(this).data('comment');
        var month = $(this).data('month');
        var year = $(this).data('year');
        var rejector = $(this).data('rejector');
        
        swal({
            title: 'Rejection Details',
            text: 'Payments for ' + month + ' ' + year + ' were rejected by the ' + rejector + '.',
            content: {
                element: "div",
                attributes: {
                    innerHTML: '<div class="rejection-reason"><strong>Reason:</strong><br>' + comment + '</div>'
                },
            },
            icon: 'error',
            buttons: {
                confirm: {
                    text: 'OK',
                    className: 'btn btn-primary'
                }
            }
        });
    });
});
</script>
</body>
</html> 