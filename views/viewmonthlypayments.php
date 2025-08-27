<?php
// viewMonthlyPayments.php
session_start();
include '../config/dbConnect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

$month = isset($_GET['month']) ? $_GET['month'] : date('F');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Monthly Payments | Admin Portal</title>
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
    <style>
        .table th {
            white-space: nowrap;
            background-color: #f8f9fa;
        }
        .total-highlight {
            font-weight: 600;
            color: #2e59d9;
        }
        .badge-month {
            background-color: #2e59d9;
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
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
                        <img src="../assets/img/app-logo1.png" alt="navbar brand" class="navbar-brand" height="20" />
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
                    <h3 class="fw-bold mb-3">Monthly Payments - <?= $month ?> <?= $year ?></h3>
                    
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-14 col-lg-14">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h4 class="card-title mb-0">Monthly Payments Details</h4>
                                    <p class="text-muted mb-0">Payment details for <?= $month ?> <?= $year ?></p>
                                </div>
                                <span class="badge badge-month">
                                    <?= $month ?> <?= $year ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div id="paymentsTable"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <a href="monthlypayments.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Publish
            </a>
            </div>
             
        </div>
       
        <?php include 'components/footer.php'; ?>
    </div>
</div>
<!--   Core JS Files   -->
<script src="../assets/js/core/jquery-3.7.1.min.js"></script>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
<script src="../assets/js/kaiadmin.min.js"></script>
<script src="../assets/js/setting-demo2.js"></script>
<script>
$(document).ready(function() {
    loadPaymentsTable('<?= $month ?>', <?= $year ?>);
});

function loadPaymentsTable(month, year) {
    $.ajax({
        url: '../controllers/getMonthlyPaymentsController.php',
        type: 'GET',
        data: {
            month: month,
            year: year
        },
        beforeSend: function() {
            $('#paymentsTable').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        },
        success: function(response) {
            $('#paymentsTable').html(response);
        },
        error: function() {
            $('#paymentsTable').html('<div class="alert alert-danger">Error loading payments data</div>');
        }
    });
}
</script>
</body>
</html>