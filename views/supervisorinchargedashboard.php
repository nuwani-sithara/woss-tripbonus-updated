<?php
session_start();
require_once("../config/dbConnect.php");

// Check if user is logged in and has role_id = 13 (supervisor-in-charge)
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 13) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Get counts for dashboard
$userID = $_SESSION['userID'];

// Count pending approvals
$pendingApprovalsQuery = "SELECT COUNT(*) as count FROM approvals a 
                         JOIN jobs j ON a.jobID = j.jobID 
                         WHERE a.approval_stage = 'supervisor_in_charge_approval' 
                         AND a.approval_status = 0";
$pendingResult = $conn->query($pendingApprovalsQuery);
$pendingCount = $pendingResult ? $pendingResult->fetch_assoc()['count'] : 0;

// Count rejected jobs by operations manager
$rejectedJobsQuery = "SELECT COUNT(*) as count FROM approvals a 
                     JOIN jobs j ON a.jobID = j.jobID 
                     WHERE a.approval_stage = 'job_approval' 
                     AND a.approval_status = 3";
$rejectedResult = $conn->query($rejectedJobsQuery);
$rejectedCount = $rejectedResult ? $rejectedResult->fetch_assoc()['count'] : 0;

// Count clarifications to resolve (from Operations Manager)
$clarificationsToResolveQuery = "SELECT COUNT(*) as count FROM clarifications c 
                                JOIN approvals a ON c.approvalID = a.approvalID 
                                WHERE c.clarification_resolverID = ? 
                                AND c.clarification_status = 0 
                                AND a.approval_stage = 'job_approval'";
$clarificationsStmt = $conn->prepare($clarificationsToResolveQuery);
$clarificationsStmt->bind_param("i", $userID);
$clarificationsStmt->execute();
$clarificationsResult = $clarificationsStmt->get_result();
$clarificationsCount = $clarificationsResult ? $clarificationsResult->fetch_assoc()['count'] : 0;

// Count resolved clarifications waiting for approval
$resolvedClarificationsQuery = "SELECT COUNT(*) as count FROM clarifications c 
                               JOIN approvals a ON c.approvalID = a.approvalID 
                               WHERE c.clarification_requesterID = ? 
                               AND c.clarification_status = 1 
                               AND a.approval_stage = 'supervisor_in_charge_approval'";
$resolvedClarificationsStmt = $conn->prepare($resolvedClarificationsQuery);
$resolvedClarificationsStmt->bind_param("i", $userID);
$resolvedClarificationsStmt->execute();
$resolvedClarificationsResult = $resolvedClarificationsStmt->get_result();
$resolvedClarificationsCount = $resolvedClarificationsResult ? $resolvedClarificationsResult->fetch_assoc()['count'] : 0;

// Count jobs with pending clarifications (status = 0)
$pendingClarificationsQuery = "SELECT COUNT(DISTINCT j.jobID) as count FROM jobs j 
                              JOIN clarifications c ON j.jobID = c.jobID 
                              JOIN approvals a ON c.approvalID = a.approvalID 
                              WHERE c.clarification_requesterID = ? 
                              AND c.clarification_status = 0 
                              AND a.approval_stage = 'supervisor_in_charge_approval'";
$pendingClarificationsStmt = $conn->prepare($pendingClarificationsQuery);
$pendingClarificationsStmt->bind_param("i", $userID);
$pendingClarificationsStmt->execute();
$pendingClarificationsResult = $pendingClarificationsStmt->get_result();
$pendingClarificationsCount = $pendingClarificationsResult ? $pendingClarificationsResult->fetch_assoc()['count'] : 0;

// Count total jobs handled
$totalJobsQuery = "SELECT COUNT(DISTINCT j.jobID) as count FROM jobs j 
                   JOIN approvals a ON j.jobID = a.jobID 
                   WHERE a.approval_stage = 'supervisor_in_charge_approval'";
$totalResult = $conn->query($totalJobsQuery);
$totalCount = $totalResult ? $totalResult->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Supervisor-in-Charge Dashboard</title>
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
    <style>
      .card-stats {
        transition: all 0.2s ease;
      }
      .card-stats:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      }
      .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
      }
      .quick-action-card {
        transition: all 0.2s ease;
        border-left: 4px solid #dee2e6;
      }
      .quick-action-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-left-color: #0d6efd;
      }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'components/supervisorinchargesidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <div class="main-header-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="dark">
                    <a href="../views/supervisorInChargeDashboard.php" class="logo">
                        <img src="../assets/img/logo_white.png" alt="navbar brand" class="navbar-brand" height="20" />
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar"><i class="gg-menu-right"></i></button>
                        <button class="btn btn-toggle sidenav-toggler"><i class="gg-menu-left"></i></button>
                    </div>
                    <button class="topbar-toggler more"><i class="gg-more-vertical-alt"></i></button>
                </div>
                <!-- End Logo Header -->
            </div>
            <?php include 'components/navbar.php'; ?>
        </div>
        
        <div class="container">
            <div class="page-inner">
                <div class="page-header">
                    <h4 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($_SESSION['fname'] . ' ' . $_SESSION['lname']) ?>!</h4>
                    <p class="text-muted">Supervisor-in-Charge Dashboard</p>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-2">
                        <div class="card card-stats card-round">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-icon">
                                        <div class="stat-icon bg-primary">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    </div>
                                    <div class="col col-stats ml-3 ml-sm-0">
                                        <div class="numbers">
                                            <p class="card-category">Pending Approvals</p>
                                            <h4 class="card-title"><?= $pendingCount ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-stats card-round">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-icon">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-question-circle"></i>
                                        </div>
                                    </div>
                                    <div class="col col-stats ml-3 ml-sm-0">
                                        <div class="numbers">
                                            <p class="card-category">Clarifications to Resolve</p>
                                            <h4 class="card-title"><?= $clarificationsCount ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-stats card-round">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-icon">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-hourglass-half"></i>
                                        </div>
                                    </div>
                                    <div class="col col-stats ml-3 ml-sm-0">
                                        <div class="numbers">
                                            <p class="card-category">Pending Clarifications</p>
                                            <h4 class="card-title"><?= $pendingClarificationsCount ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-stats card-round">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-icon">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                    <div class="col col-stats ml-3 ml-sm-0">
                                        <div class="numbers">
                                            <p class="card-category">Resolved Clarifications</p>
                                            <h4 class="card-title"><?= $resolvedClarificationsCount ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-stats card-round">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-icon">
                                        <div class="stat-icon bg-danger">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                    </div>
                                    <div class="col col-stats ml-3 ml-sm-0">
                                        <div class="numbers">
                                            <p class="card-category">Rejected by OM</p>
                                            <h4 class="card-title"><?= $rejectedCount ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-stats card-round">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-icon">
                                        <div class="stat-icon bg-secondary">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                    </div>
                                    <div class="col col-stats ml-3 ml-sm-0">
                                        <div class="numbers">
                                            <p class="card-category">Total Jobs Handled</p>
                                            <h4 class="card-title"><?= $totalCount ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h5 class="fw-bold mb-3">Quick Actions</h5>
                    </div>
                    <div class="col-md-4">
                        <div class="card quick-action-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-clipboard-check fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="card-title mb-1">Review Job Approvals</h6>
                                        <p class="card-text text-muted small">Review and approve jobs before they go to Operations Manager</p>
                                        <a href="supervisorInChargeApproval.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-arrow-right me-1"></i> Go to Approvals
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card quick-action-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-question-circle fa-2x text-warning"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="card-title mb-1">Resolve Clarifications</h6>
                                        <p class="card-text text-muted small">Respond to clarification requests from Operations Manager</p>
                                        <a href="supervisorInChargeApproval.php" class="btn btn-warning btn-sm">
                                            <i class="fas fa-arrow-right me-1"></i> View Clarifications
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card quick-action-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-eye fa-2x text-info"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="card-title mb-1">View Job Details</h6>
                                        <p class="card-text text-muted small">View detailed information about specific jobs</p>
                                        <a href="jobManagement.php" class="btn btn-info btn-sm">
                                            <i class="fas fa-arrow-right me-1"></i> Manage Jobs
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Your Role:</strong> As a Supervisor-in-Charge, you are responsible for reviewing and approving jobs before they are sent to the Operations Manager for final approval.
                                </div>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Workflow:</strong> Jobs created by supervisors → Your approval → Operations Manager approval → Final approval
                                </div>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Current Status:</strong> You have <?= $pendingCount ?> job(s) pending your approval, <?= $clarificationsCount ?> clarification(s) to resolve, <?= $pendingClarificationsCount ?> job(s) with pending clarifications, <?= $resolvedClarificationsCount ?> resolved clarification(s) waiting for approval, and <?= $rejectedCount ?> job(s) that were rejected by the Operations Manager.
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
<script src="../assets/js/setting-demo2.js"></script>
</body>
</html>
