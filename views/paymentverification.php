<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 5) { // 5 = Accountant
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
    <title>Payment Verification | Accountant Portal</title>
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
        .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-left: 10px; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .card-header { background-color: #f8f9fa; border-bottom: 1px solid rgba(0,0,0,.05); }
        .form-label { font-weight: 500; color: #495057; }
        .table th { white-space: nowrap; background-color: #f8f9fa; }
        .table td, .table th { vertical-align: middle; }
        .badge-month { background-color: #2e59d9; font-size: 0.85rem; padding: 0.35em 0.65em; }
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
    <?php include 'components/accountantSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <?php include 'components/navbar.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Payment Verification</h3>
                    <!-- <ul class="breadcrumbs mb-3">
                        <li class="nav-home"><a href="accountantDashboard.php"><i class="icon-home"></i></a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Payment Verification</a></li>
                    </ul> -->
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-14 col-lg-14">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h4 class="card-title mb-0">Verify Monthly Payments</h4>
                                    <p class="text-muted mb-0">Review and verify monthly payments for employees</p>
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
                                            <?php for (
                                                $m=1; $m<=12; $m++): 
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
                                <div id="verifyAllContainer"></div>
                                <div id="paymentsTableContainer"></div>
                                <!-- <div class="row mt-3" id="exportControls" style="display:none;">
                                    <div class="col-md-3">
                                        <select id="exportFileType" class="form-select">
                                            <option value="csv">CSV</option>
                                            <option value="pdf">PDF</option>
                                            <option value="docx">DOCX</option>
                                            <option value="xlsx">XLSX</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-success" id="exportBtn">
                                            <i class="fas fa-file-export me-2"></i>Export Report
                                        </button>
                                    </div>
                                </div> -->
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
<div id="bottomNotification" class="bottom-notification"></div>
<script src="../assets/js/core/jquery-3.7.1.min.js"></script>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
<script src="../assets/js/kaiadmin.min.js"></script>
<!-- Sweet Alert -->
<script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>
<script>
function fetchPayments(month, year) {
    $('#loadingSpinner').show();
    $.get('../controllers/paymentVerificationController.php', { month, year }, function(data) {
        // Split response: first line is verification status, rest is table
        var split = data.split('<!--VERIFIED_STATUS-->');
        var verifiedStatus = split[0];
        var tableHtml = split[1];
        $('#verifyAllContainer').html(verifiedStatus);
        $('#paymentsTableContainer').html(tableHtml);
        
        // Show/hide buttons based on verification status
        if (verifiedStatus.includes('alert-success')) {
            // Already verified - hide buttons
            $('#verifyAllBtn, #rejectAllBtn').hide();
        } else {
            // Not verified - show buttons
            $('#verifyAllBtn, #rejectAllBtn').show();
        }
        
        $('#loadingSpinner').hide();
        $('#exportControls').show();
    });
}
function showBottomNotification(message, type = 'success') {
    const notif = document.getElementById('bottomNotification');
    notif.innerHTML = `<div class="alert alert-${type} d-flex align-items-center custom-success-bg mb-0">
        <i class="fas fa-check-circle me-3 fs-4"></i>
        <div>
            <h5 class="alert-heading mb-1">Payments Verified Successfully</h5>
            <p class="mb-0">${message}</p>
        </div>
    </div>`;
    notif.style.display = 'block';
    setTimeout(() => {
        notif.style.display = 'none';
    }, 4000);
}
$(document).ready(function() {
    fetchPayments($('#month').val(), $('#year').val());
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        fetchPayments($('#month').val(), $('#year').val());
    });
    $(document).on('click', '#verifyAllBtn', function() {
        var month = $('#month').val();
        var year = $('#year').val();
        var btn = $(this);
        swal({
            title: 'Are you sure?',
            text: 'Do you want to verify all payments for ' + month + ' ' + year + '?',
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
            },
            dangerMode: false
        }).then(function(willDo) {
            if (willDo) {
                showLoader(); // Show loader
                btn.prop('disabled', true).html('Verifying...');
                $.post('../controllers/paymentVerificationController.php', { action: 'verify', month, year }, function(response) {
                    hideLoader(); // Hide loader
                    if (response === 'success') {
                        fetchPayments(month, year);
                        showBottomNotification('Payments for <b>' + month + ' ' + year + '</b> have been verified and recorded.');
                    } else {
                        btn.prop('disabled', false).html('Verify All');
                        swal('Error', 'Verification failed!', 'error');
                    }
                }).fail(function() {
                    hideLoader(); // Hide loader on failure
                    btn.prop('disabled', false).html('Verify All');
                    swal('Error', 'An error occurred while processing your request.', 'error');
                });
            }
        });
    });
    $(document).on('click', '#rejectAllBtn', function() {
        var month = $('#month').val();
        var year = $('#year').val();
        var btn = $(this);
        swal({
            title: 'Are you sure?',
            text: 'Do you want to reject all payments for ' + month + ' ' + year + '? Please provide a reason.',
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
                var comment = $('#rejectComment').val();
                if (!comment || comment.trim() === "") {
                    swal('Error', 'Please provide a rejection reason.', 'error');
                    return;
                }
                showLoader(); // Show loader
                btn.prop('disabled', true).html('Rejecting...');
                $.post('../controllers/paymentVerificationController.php', { action: 'reject', month, year, comment: comment }, function(response) {
                    hideLoader(); // Hide loader
                    if (response === 'success') {
                        fetchPayments(month, year);
                        // Show bottom notification for rejection
                        const notif = document.getElementById('bottomNotification');
                        notif.innerHTML = `<div class="alert alert-danger d-flex align-items-center mb-0" style="background-color:#f8d7da !important; border-color:#f5c6cb !important; color:#721c24 !important;"><i class="fas fa-times-circle me-3 fs-4"></i><div><h5 class="alert-heading mb-1">Payments Rejected</h5><p class="mb-0">Payments for <b>${month} ${year}</b> have been rejected and marked as not verified.</p></div></div>`;
                        notif.style.display = 'block';
                        setTimeout(() => {
                            notif.style.display = 'none';
                        }, 4000);
                    } else {
                        btn.prop('disabled', false).html('Reject All');
                        swal('Error', 'Rejection failed!', 'error');
                    }
                }).fail(function() {
                    hideLoader(); // Hide loader on failure
                    btn.prop('disabled', false).html('Reject All');
                    swal('Error', 'An error occurred while processing your request.', 'error');
                });
            }
        });
    });
    $(document).on('click', '#exportBtn', function() {
        var month = $('#month').val();
        var year = $('#year').val();
        var fileType = $('#exportFileType').val();
        window.location.href = '../controllers/paymentVerificationController.php?action=export&month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year) + '&fileType=' + encodeURIComponent(fileType);
    });
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
