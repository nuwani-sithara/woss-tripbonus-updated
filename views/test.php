<?php
include '../controllers/jobDetailsController.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Job Details - <?= htmlspecialchars($jobDetails['job']['vessel_name'] ?? 'Job #' . $jobDetails['job']['jobID']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../assets/img/Logo_white.png" type="image/x-icon" />

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        :root {
            --border-color: #e0e0e0;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.05);
            --primary-color: #4361ee;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            background-color: #f8f9fa;
            color: #495057;
        }
        
        .page-header {
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 0.75rem 1.25rem;
            border-radius: 8px 8px 0 0 !important;
        }
        
        .card-header h4 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: #343a40;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }
        
        .info-label {
            font-weight: 500;
            color: #6c757d;
            min-width: 120px;
        }
        
        .info-value {
            color: #212529;
            text-align: right;
            flex: 1;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .trip-card {
            border-left: 3px solid var(--primary-color);
            background-color: white;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .employee-badge {
            background-color: #f0f7ff;
            color: var(--primary-color);
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
            display: inline-block;
        }
        
        .clarification-item {
            border-left: 3px solid var(--warning-color);
            background-color: #fffcf5;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .clarification-item.resolved {
            border-left-color: var(--success-color);
            background-color: #f5fff7;
        }
        
        .special-project-badge {
            background-color: #f0f2ff;
            color: var(--primary-color);
            padding: 0.3rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .empty-state {
            text-align: center;
            padding: 1.5rem;
            color: #adb5bd;
            font-size: 0.9rem;
        }
        
        .empty-state i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }
        
        .back-link {
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .badge-primary {
            background-color: var(--primary-color);
        }
        
        .badge-success {
            background-color: var(--success-color);
        }
        
        .badge-warning {
            background-color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: var(--danger-color);
        }
        
        .badge-info {
            background-color: var(--info-color);
        }
        
        .btn-block {
            font-size: 0.85rem;
            padding: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php 
        // Include appropriate sidebar based on role
        if ($_SESSION['roleID'] == 1) {
            include 'components/sidebar.php';
        } elseif ($_SESSION['roleID'] == 4) {
            include 'components/adminSidebar.php';
        }
        ?>
        
        <div class="main-panel">
            <div class="main-header">
                <div class="main-header-logo">
                    <!-- Logo Header -->
                    <div class="logo-header" data-background-color="dark">
                        <a href="../index.html" class="logo">
                            <img
                                src="../assets/img/Logo_white.png"
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
                    <div class="page-header d-flex justify-content-between align-items-center">
                        <div>
                            <a href="<?= $_SESSION['roleID'] == 1 ? 'supervisorEditJobs.php' : 'approveJobs.php' ?>" class="back-link">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Jobs
                            </a>
                            <h1 class="mb-0">Job Details</h1>
                            <p class="text-muted mb-0">
                                <strong>Job ID:</strong> #<?= $jobDetails['job']['jobID'] ?> | 
                                <strong>Type:</strong> <?= htmlspecialchars($jobDetails['job']['job_type_name'] ?? 'N/A') ?>
                            </p>
                        </div>
                        <span class="status-badge badge-<?= $jobDetails['status_class'] ?>">
                            <?= $jobDetails['status'] ?>
                        </span>
                    </div>

                    <div class="row">
                        <!-- Job Information -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-info-circle mr-2"></i>Job Information</h4>
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <span class="info-label">Vessel Name:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['job']['vessel_name'] ?? 'Not Assigned') ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Job Type:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['job']['job_type_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Start Date:</span>
                                        <span class="info-value"><?= date('M d, Y', strtotime($jobDetails['job']['start_date'])) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">End Date:</span>
                                        <span class="info-value"><?= $jobDetails['job']['end_date'] ? date('M d, Y', strtotime($jobDetails['job']['end_date'])) : 'Not Set' ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Port:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['job']['portname'] ?? 'Not Assigned') ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Boat:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['job']['boat_name'] ?? 'Not Assigned') ?></span>
                                    </div>
                                    <?php if (!empty($jobDetails['job']['comment'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Comments:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['job']['comment']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-row">
                                        <span class="info-label">Created By:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['job']['created_by_fname'] . ' ' . $jobDetails['job']['created_by_lname']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Trip Details -->
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-calendar-alt mr-2"></i>Trip Details</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($jobDetails['trips'])): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-times"></i>
                                            <p>No trip days have been added to this job yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($jobDetails['trips'] as $trip): ?>
                                            <div class="trip-card">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <strong><?= date('M d, Y', strtotime($trip['trip_date'])) ?></strong>
                                                    <span class="badge badge-primary"><?= $trip['employee_count'] ?> Employee<?= $trip['employee_count'] != 1 ? 's' : '' ?></span>
                                                </div>
                                                <?php if (!empty($trip['employee_details'])): ?>
                                                    <div class="mt-1">
                                                        <small class="text-muted">Assigned Employees:</small>
                                                        <div class="mt-1">
                                                            <?php foreach ($trip['employee_details'] as $emp): ?>
                                                                <span class="employee-badge">
                                                                    <?= htmlspecialchars($emp['fname'] . ' ' . $emp['lname']) ?>
                                                                    <?php if (!empty($emp['role_name'])): ?>
                                                                        <small>(<?= htmlspecialchars($emp['role_name']) ?>)</small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <small class="text-muted">No employees assigned</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Clarifications -->
                            <?php if (!empty($jobDetails['clarifications'])): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-question-circle mr-2"></i>Clarifications</h4>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($jobDetails['clarifications'] as $clar): ?>
                                        <div class="clarification-item <?= $clar['clarification_status'] == 2 ? 'resolved' : '' ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong>
                                                    <?php if ($clar['clarification_status'] == 0): ?>
                                                        <i class="fas fa-clock text-warning mr-1"></i>Pending
                                                    <?php elseif ($clar['clarification_status'] == 1): ?>
                                                        <i class="fas fa-hourglass-half text-info mr-1"></i>Pending Approval
                                                    <?php else: ?>
                                                        <i class="fas fa-check-circle text-success mr-1"></i>Resolved
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($clar['requested_by_fname'] . ' ' . $clar['requested_by_lname']) ?>
                                                </strong>
                                                <small class="text-muted">ID: <?= $clar['clarification_id'] ?></small>
                                            </div>
                                            <div class="mb-1">
                                                <small class="text-muted">Request:</small>
                                                <p class="mb-1"><?= htmlspecialchars($clar['clarification_request_comment']) ?></p>
                                            </div>
                                            <?php if (!empty($clar['clarification_resolved_comment'])): ?>
                                                <div class="mb-1">
                                                    <small class="text-muted">Resolution:</small>
                                                    <p class="mb-1"><?= htmlspecialchars($clar['clarification_resolved_comment']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sidebar -->
                        <div class="col-lg-4">
                            <!-- Special Projects -->
                            <?php if (!empty($jobDetails['special_projects'])): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-star mr-2"></i>Special Projects</h4>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($jobDetails['special_projects'] as $sp): ?>
                                        <span class="special-project-badge">
                                            <?= htmlspecialchars($sp['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Approval Information -->
                            <?php if ($jobDetails['approval']): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-check-double mr-2"></i>Approval Information</h4>
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <span class="info-label">Status:</span>
                                        <span class="info-value">
                                            <?php if ($jobDetails['approval']['approval_status'] == 1): ?>
                                                <span class="badge badge-success">Approved</span>
                                            <?php elseif ($jobDetails['approval']['approval_status'] == 2): ?>
                                                <span class="badge badge-danger">Rejected</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Approved By:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['approval']['approved_by_fname'] . ' ' . $jobDetails['approval']['approved_by_lname']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Date:</span>
                                        <span class="info-value"><?= date('M d, Y H:i', strtotime($jobDetails['approval']['approval_date'])) ?></span>
                                    </div>
                                    <?php if (!empty($jobDetails['approval']['comment'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Comment:</span>
                                        <span class="info-value"><?= htmlspecialchars($jobDetails['approval']['comment']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Quick Actions -->
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-tools mr-2"></i>Quick Actions</h4>
                                </div>
                                <div class="card-body">
                                    <?php if ($_SESSION['roleID'] == 1 && $jobDetails['status'] == 'Draft'): ?>
                                    <button class="btn btn-primary btn-block mb-2" onclick="window.location.href='supervisorEditJobs.php?edit_job=<?= $jobDetails['job']['jobID'] ?>'">
                                        <i class="fas fa-edit mr-1"></i> Edit Job
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-secondary btn-block" onclick="window.print()">
                                        <i class="fas fa-print mr-1"></i> Print Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'components/footer.php'; ?>
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

    <!-- Atlantis JS -->
    <script src="../assets/js/atlantis.min.js"></script>
</body>
</html>