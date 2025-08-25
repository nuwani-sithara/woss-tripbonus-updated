<?php
require_once("../controllers/supervisorInChargeController.php");

// Check if user is logged in and has role_id = 13 (supervisor-in-charge)
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 13) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Get message if any
$message = isset($_GET['message']) ? $_GET['message'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Supervisor-in-Charge Approvals | Dashboard</title>
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
      .job-card {
        transition: all 0.2s ease;
        border-left: 4px solid #dee2e6;
        margin-bottom: 1.5rem;
        border-radius: 8px;
      }
      .job-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-left-color: #0d6efd;
      }
      .job-header {
        padding-bottom: 0.75rem;
        margin-bottom: 0.75rem;
      }
      .job-meta {
        font-size: 0.85rem;
        color: #6c757d;
      }
      .employee-badge {
        font-size: 0.8rem;
        margin-right: 0.3rem;
        margin-bottom: 0.3rem;
        padding: 0.25rem 0.5rem;
      }
      .action-btn {
        min-width: 90px;
        font-size: 0.85rem;
        padding: 0.4rem 0.65rem;
      }
      .status-badge {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
      }
      .job-duration {
        background-color: #f8f9fa;
        border-radius: 6px;
        padding: 0.5rem 0.75rem;
      }
      .special-project-item {
        border-left: 2px solid #0d6efd;
        padding-left: 0.5rem;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
      }
      .card-body {
        padding: 1.25rem;
      }
      .card-footer {
        padding: 1rem;
        background-color: #f8fafc;
        border-top: 1px solid rgba(0,0,0,0.05);
      }
      .trip-card {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 0.75rem;
        margin-bottom: 0.75rem;
        background-color: #fff;
      }
      .trip-card:hover {
        background-color: #f8f9fa;
      }
      .job-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
      }
      .job-section {
        margin-bottom: 1.5rem;
      }
      .section-title {
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 0.75rem;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.5rem;
      }
      .rejected-job-card {
        border-left-color: #dc3545;
      }
      .rejected-job-card:hover {
        border-left-color: #dc3545;
      }
      .clarification-card {
        border-left-color: #ffc107;
      }
      .clarification-card:hover {
        border-left-color: #ffc107;
      }
      .clarification-item {
        background-color: #f8f9fa;
        border-radius: 6px;
        padding: 0.75rem;
        margin-bottom: 0.75rem;
      }
      .clarification-request {
        background-color: #e3f2fd;
        border-left: 3px solid #2196f3;
        padding: 0.5rem;
        border-radius: 4px;
        margin-top: 0.5rem;
      }
      .clarification-response {
        background-color: #e8f5e8;
        border-left: 3px solid #4caf50;
        padding: 0.5rem;
        border-radius: 4px;
        margin-top: 0.5rem;
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
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold mb-0">Supervisor-in-Charge Approvals</h4>
                    <div class="badge bg-light text-dark"><?= count($jobs) ?> Pending</div>
                </div>
                
                <?php if ($message === 'job_resubmitted'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Job has been resubmitted for approval.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($message === 'clarification_resolved'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Clarification has been resolved successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($message === 'clarification_approved'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Clarification resolution has been approved. Job can now proceed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($message === 'clarification_rejected'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Clarification resolution has been rejected. Supervisor needs to provide better resolution.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-info py-2">
                    <i class="fas fa-info-circle me-2"></i> Review and approve jobs before they are sent to the Operations Manager for final approval.
                </div>
                
                <!-- Clarifications to Resolve Section (from Operations Manager) -->
                <?php if (!empty($clarificationsToResolve)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-warning"><i class="fas fa-question-circle me-2"></i>Clarifications to Resolve</h5>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i> These clarifications were requested by the Operations Manager and need your response.
                    </div>
                    <div class="row">
                        <?php foreach ($clarificationsToResolve as $clarification): ?>
                        <div class="col-md-12">
                            <div class="card job-card clarification-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="fw-semibold mb-2">Clarification Request from Operations Manager</h6>
                                            <div class="clarification-request">
                                                <strong>Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <form method="post" action="../controllers/supervisorInChargeController.php">
                                                    <input type="hidden" name="clarification_id" value="<?= $clarification['clarification_id'] ?>">
                                                    <div class="mb-3">
                                                        <label for="resolution_comment_<?= $clarification['clarification_id'] ?>" class="form-label">Your Response:</label>
                                                        <textarea class="form-control" id="resolution_comment_<?= $clarification['clarification_id'] ?>" name="resolution_comment" rows="3" required placeholder="Provide clarification response..."></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check me-1"></i> Resolve Clarification
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="job-details-compact">
                                                <div class="detail-row">
                                                    <span class="detail-label">Job ID:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['jobID']) ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Vessel:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['vessel_name'] ?? '-') ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Job Type:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['job_type'] ?? '-') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pending Clarification Responses Section (from Supervisor) -->
                <?php if (!empty($pendingClarificationResponses)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-info"><i class="fas fa-clock me-2"></i>Pending Clarification Responses</h5>
                    <div class="alert alert-info py-2 mb-3">
                        <i class="fas fa-info-circle me-2"></i> These clarifications were requested by you and are waiting for the supervisor's response.
                    </div>
                    <div class="row">
                        <?php foreach ($pendingClarificationResponses as $clarification): ?>
                        <div class="col-md-12">
                            <div class="card job-card clarification-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="fw-semibold mb-2">Your Clarification Request</h6>
                                            <div class="clarification-request">
                                                <strong>Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <span class="badge bg-warning text-dark">Waiting for Supervisor Response</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="job-details-compact">
                                                <div class="detail-row">
                                                    <span class="detail-label">Job ID:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['jobID']) ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Vessel:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['vessel_name'] ?? '-') ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Job Type:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['job_type'] ?? '-') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Jobs with Pending Clarifications Section -->
                <?php if (!empty($jobsWithPendingClarifications)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Jobs with Pending Clarifications</h5>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-info-circle me-2"></i> These jobs have clarification requests that are waiting for supervisor response. Jobs cannot proceed until clarifications are resolved.
                    </div>
                    <div class="row">
                        <?php foreach ($jobsWithPendingClarifications as $item): 
                            $job = $item['job'];
                            $boat = $item['boat'];
                            $port = $item['port'];
                            $special_projects = $item['special_projects'];
                            $vessel_name = $item['vessel_name'];
                            $job_type = $item['job_type'];
                            $job_creator = $item['job_creator'];
                            $trips = $item['trips'];
                            $clarification = $item['clarification'];
                        ?>
                        <div class="col-md-12">
                            <div class="card job-card clarification-card">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Left Column - Job Details -->
                                        <div class="col-md-5 border-end pe-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h5>
                                                <span class="badge bg-warning text-dark status-badge">Clarification Pending</span>
                                            </div>
                                            
                                            <?php if (!empty($item['job_creator'])): ?>
                                            <div class="text-muted small mb-2">
                                                <i class="fas fa-user-tie me-1"></i>
                                                Created by: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($job['comment']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted job-meta">Note</small>
                                                <div class="small"><?= htmlspecialchars($job['comment']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="job-details-grid mt-2">
                                                <div>
                                                    <small class="text-muted job-meta">Vessel</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Job Type</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Boat</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Port</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="job-duration mt-2">
                                                <div class="d-flex justify-content-between small">
                                                    <div>
                                                        <small class="text-muted">Start</small>
                                                        <div class="fw-semibold"><?= htmlspecialchars($job['start_date']) ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted">End</small>
                                                        <div class="fw-semibold">
                                                            <?php if (!empty($job['end_date'])): ?>
                                                                <?= htmlspecialchars($job['end_date']) ?>
                                                            <?php else: ?>
                                                                <span class="text-warning">Not closed</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($special_projects): ?>
                                            <div class="mt-2">
                                                <small class="text-muted job-meta">Special Projects</small>
                                                <div class="mt-1">
                                                    <?php foreach ($special_projects as $sp): ?>
                                                    <div class="special-project-item">
                                                        <div class="fw-semibold"><?= htmlspecialchars($sp['name'] ?? 'Special Project') ?></div>
                                                        <?php if (!empty($sp['evidence'])): ?>
                                                        <a href="../uploads/evidence/<?= htmlspecialchars($sp['evidence']) ?>" 
                                                           target="_blank" 
                                                           class="evidence-link text-primary d-inline-block mt-1">
                                                            <i class="fas fa-paperclip me-1"></i> Evidence
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Right Column - Clarification Details -->
                                        <div class="col-md-7 ps-3">
                                            <h6 class="section-title">Clarification Request</h6>
                                            <div class="clarification-request">
                                                <strong>Your Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <span class="badge bg-warning text-dark">Waiting for Supervisor Response</span>
                                                <small class="text-muted d-block mt-1">This job cannot proceed until the clarification is resolved by the supervisor.</small>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i> View Job Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Resolved Clarifications for Approval Section -->
                <?php if (!empty($resolvedClarificationsForApproval)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-success"><i class="fas fa-check-circle me-2"></i>Resolved Clarifications for Approval</h5>
                    <div class="alert alert-success py-2 mb-3">
                        <i class="fas fa-info-circle me-2"></i> These clarifications have been resolved by supervisors and need your approval to proceed.
                    </div>
                    <div class="row">
                        <?php foreach ($resolvedClarificationsForApproval as $clarification): ?>
                        <div class="col-md-12">
                            <div class="card job-card clarification-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="fw-semibold mb-2">Clarification Request & Resolution</h6>
                                            <div class="clarification-request">
                                                <strong>Your Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="clarification-response mt-2">
                                                <strong>Supervisor's Response:</strong> <?= htmlspecialchars($clarification['clarification_resolved_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <form method="post" action="../controllers/supervisorInChargeController.php" class="d-inline">
                                                    <input type="hidden" name="clarification_approval_id" value="<?= $clarification['clarification_id'] ?>">
                                                    <button type="submit" name="clarification_approval_action" value="approve" class="btn btn-success btn-sm me-2">
                                                        <i class="fas fa-check me-1"></i> Approve Resolution
                                                    </button>
                                                    <button type="submit" name="clarification_approval_action" value="reject" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-times me-1"></i> Reject Resolution
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="job-details-compact">
                                                <div class="detail-row">
                                                    <span class="detail-label">Job ID:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['jobID']) ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Vessel:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['vessel_name'] ?? '-') ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Job Type:</span>
                                                    <span class="detail-value"><?= htmlspecialchars($clarification['job_type'] ?? '-') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rejected Jobs Section (Jobs rejected by Operations Manager) -->
                <?php if (!empty($rejectedJobs)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-danger"><i class="fas fa-times-circle me-2"></i>Jobs Rejected by Operations Manager</h5>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i> These jobs were rejected by the Operations Manager and need your review.
                    </div>
                    <div class="row">
                        <?php foreach ($rejectedJobs as $item): 
                            $job = $item['job'];
                            $boat = $item['boat'];
                            $port = $item['port'];
                            $special_projects = $item['special_projects'];
                            $vessel_name = $item['vessel_name'];
                            $job_type = $item['job_type'];
                            $job_creator = $item['job_creator'];
                            $trips = $item['trips'];
                        ?>
                        <div class="col-md-12">
                            <div class="card job-card rejected-job-card">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Left Column - Job Details -->
                                        <div class="col-md-5 border-end pe-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h5>
                                                <span class="badge bg-danger status-badge">Rejected by OM</span>
                                            </div>
                                            
                                            <?php if (!empty($item['job_creator'])): ?>
                                            <div class="text-muted small mb-2">
                                                <i class="fas fa-user-tie me-1"></i>
                                                Created by: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($job['comment']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted job-meta">Note</small>
                                                <div class="small"><?= htmlspecialchars($job['comment']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="job-details-grid mt-2">
                                                <div>
                                                    <small class="text-muted job-meta">Vessel</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Job Type</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Boat</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Port</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="job-duration mt-2">
                                                <div class="d-flex justify-content-between small">
                                                    <div>
                                                        <small class="text-muted">Start</small>
                                                        <div class="fw-semibold"><?= htmlspecialchars($job['start_date']) ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted">End</small>
                                                        <div class="fw-semibold">
                                                            <?php if (!empty($job['end_date'])): ?>
                                                                <?= htmlspecialchars($job['end_date']) ?>
                                                            <?php else: ?>
                                                                <span class="text-warning">Not closed</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($special_projects): ?>
                                            <div class="mt-2">
                                                <small class="text-muted job-meta">Special Projects</small>
                                                <div class="mt-1">
                                                    <?php foreach ($special_projects as $sp): ?>
                                                    <div class="special-project-item">
                                                        <div class="fw-semibold"><?= htmlspecialchars($sp['name'] ?? 'Special Project') ?></div>
                                                        <?php if (!empty($sp['evidence'])): ?>
                                                        <a href="../uploads/evidence/<?= htmlspecialchars($sp['evidence']) ?>" 
                                                           target="_blank" 
                                                           class="evidence-link text-primary d-inline-block mt-1">
                                                            <i class="fas fa-paperclip me-1"></i> Evidence
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Right Column - Trips -->
                                        <div class="col-md-7 ps-3">
                                            <h6 class="section-title">Trips / Days</h6>
                                            
                                            <?php if (empty($item['trips'])): ?>
                                            <div class="alert alert-warning py-2 small mb-0">
                                                <i class="fas fa-exclamation-triangle me-1"></i> No days/trips added yet.
                                            </div>
                                            <?php else: ?>
                                                <?php foreach ($item['trips'] as $tripItem): ?>
                                                <div class="trip-card">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <strong><?= htmlspecialchars($tripItem['trip']['trip_date']) ?></strong>
                                                        <?php if (isset($tripItem['attendance']['attendance_status'])): ?>
                                                            <?php
                                                                $status = $tripItem['attendance']['attendance_status'];
                                                                $statusText = $status == 1 ? 'Verified' : ($status == 3 ? 'Rejected' : 'Pending');
                                                                $statusClass = $status == 1 ? 'bg-success text-white' : ($status == 3 ? 'bg-danger text-white' : 'bg-warning text-dark');
                                                            ?>
                                                            <span class="badge <?= $statusClass ?> status-badge"><?= $statusText ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($tripItem['employees'])): ?>
                                                    <div class="small mt-2">
                                                        <div class="text-muted">Team Members:</div>
                                                        <div class="mt-1">
                                                            <?php foreach ($tripItem['employees'] as $emp): ?>
                                                            <span class="badge bg-light text-dark employee-badge">
                                                                <i class="fas fa-user me-1"></i>
                                                                <?= htmlspecialchars($emp['fname'] . ' ' . $emp['lname']) ?>
                                                            </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">Rejected on: <?= htmlspecialchars($item['approval_date']) ?></small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </a>
                                            <form method="post" action="../controllers/supervisorInChargeController.php" class="d-inline">
                                                <input type="hidden" name="review_jobID" value="<?= $job['jobID'] ?>">
                                                <button type="submit" name="review_action" value="modify" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit me-1"></i> Modify Job
                                                </button>
                                                <button type="submit" name="review_action" value="resubmit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-redo me-1"></i> Resubmit
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pending Approval Section -->
                <?php if (!empty($jobs)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-clock me-2"></i>Pending Your Approval</h5>
                    <div class="row">
                        <?php foreach ($jobs as $item): 
                            $job = $item['job'];
                            $boat = $item['boat'];
                            $port = $item['port'];
                            $special_projects = $item['special_projects'];
                            $vessel_name = $item['vessel_name'];
                            $job_type = $item['job_type'];
                            $job_creator = $item['job_creator'];
                            $trips = $item['trips'];
                        ?>
                        <div class="col-md-12">
                            <div class="card job-card">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Left Column - Job Details -->
                                        <div class="col-md-5 border-end pe-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h5>
                                                <span class="badge bg-primary status-badge">Pending Approval</span>
                                            </div>
                                            
                                            <?php if (!empty($item['job_creator'])): ?>
                                            <div class="text-muted small mb-2">
                                                <i class="fas fa-user-tie me-1"></i>
                                                Created by: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($job['comment']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted job-meta">Note</small>
                                                <div class="small"><?= htmlspecialchars($job['comment']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="job-details-grid mt-2">
                                                <div>
                                                    <small class="text-muted job-meta">Vessel</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Job Type</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Boat</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Port</small>
                                                    <div class="fw-semibold"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="job-duration mt-2">
                                                <div class="d-flex justify-content-between small">
                                                    <div>
                                                        <small class="text-muted">Start</small>
                                                        <div class="fw-semibold"><?= htmlspecialchars($job['start_date']) ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted">End</small>
                                                        <div class="fw-semibold">
                                                            <?php if (!empty($job['end_date'])): ?>
                                                                <?= htmlspecialchars($job['end_date']) ?>
                                                            <?php else: ?>
                                                                <span class="text-warning">Not closed</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($special_projects): ?>
                                            <div class="mt-2">
                                                <small class="text-muted job-meta">Special Projects</small>
                                                <div class="mt-1">
                                                    <?php foreach ($special_projects as $sp): ?>
                                                    <div class="special-project-item">
                                                        <div class="fw-semibold"><?= htmlspecialchars($sp['name'] ?? 'Special Project') ?></div>
                                                        <?php if (!empty($sp['evidence'])): ?>
                                                        <a href="../uploads/evidence/<?= htmlspecialchars($sp['evidence']) ?>" 
                                                           target="_blank" 
                                                           class="evidence-link text-primary d-inline-block mt-1">
                                                            <i class="fas fa-paperclip me-1"></i> Evidence
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Right Column - Trips -->
                                        <div class="col-md-7 ps-3">
                                            <h6 class="section-title">Trips / Days</h6>
                                            
                                            <?php if (empty($item['trips'])): ?>
                                            <div class="alert alert-warning py-2 small mb-0">
                                                <i class="fas fa-exclamation-triangle me-1"></i> No days/trips added yet.
                                            </div>
                                            <?php else: ?>
                                                <?php foreach ($item['trips'] as $tripItem): ?>
                                                <div class="trip-card">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <strong><?= htmlspecialchars($tripItem['trip']['trip_date']) ?></strong>
                                                        <?php if (isset($tripItem['attendance']['attendance_status'])): ?>
                                                            <?php
                                                                $status = $tripItem['attendance']['attendance_status'];
                                                                $statusText = $status == 1 ? 'Verified' : ($status == 3 ? 'Rejected' : 'Pending');
                                                                $statusClass = $status == 1 ? 'bg-success text-white' : ($status == 3 ? 'bg-danger text-white' : 'bg-warning text-dark');
                                                            ?>
                                                            <span class="badge <?= $statusClass ?> status-badge"><?= $statusText ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($tripItem['employees'])): ?>
                                                    <div class="small mt-2">
                                                        <div class="text-muted">Team Members:</div>
                                                        <div class="mt-1">
                                                            <?php foreach ($tripItem['employees'] as $emp): ?>
                                                            <span class="badge bg-light text-dark employee-badge">
                                                                <i class="fas fa-user me-1"></i>
                                                                <?= htmlspecialchars($emp['fname'] . ' ' . $emp['lname']) ?>
                                                            </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- <?php
                                $firstAttendanceID = null;
                                foreach ($trips as $tripItem) {
                                    if (!empty($tripItem['attendance']['job_attendanceID'])) {
                                        $firstAttendanceID = $tripItem['attendance']['job_attendanceID'];
                                        break;
                                    }
                                }
                                ?> -->
                                
                                    <div class="card-footer">
                                    <?php if (empty($job['end_date'])): ?>
                                        <!-- Ongoing Job - Buttons Disabled -->
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-success btn-sm action-btn" disabled>
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm action-btn" disabled>
                                                <i class="fas fa-question me-1"></i> Clarify
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm action-btn" disabled>
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </div>
                                        <div class="text-center mt-2">
                                            <span class="badge bg-info text-white">
                                                <i class="fas fa-clock me-1"></i> Ongoing Job
                                            </span>
                                            <small class="text-muted d-block mt-1">Job must be completed (end date added) before approval</small>
                                        </div>
                                    <?php else: ?>
                                        <!-- Completed Job - Buttons Enabled -->
                                        <form method="post" action="../controllers/supervisorInChargeController.php" class="d-flex justify-content-end gap-2">
                                            <input type="hidden" name="jobID" value="<?= $job['jobID'] ?>">
                                            <input type="hidden" name="action" value="">
                                            <button type="button" name="action_btn" value="1" class="btn btn-success btn-sm action-btn">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm action-btn btn-clarify">
                                                <i class="fas fa-question me-1"></i> Clarify
                                            </button>
                                            <button type="button" name="action_btn" value="3" class="btn btn-danger btn-sm action-btn">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- See More Details Button -->
                                    <div class="d-flex justify-content-start mt-2">
                                        <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye me-1"></i> See More Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-success text-center py-4 my-4">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                    <h4 class="fw-bold">No Pending Approvals</h4>
                    <p class="text-muted">All jobs have been reviewed and approved.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php include 'components/footer.php'; ?>
    </div>
</div>

<!-- Clarification Modal -->
<div class="modal fade" id="clarificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Request Clarification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="clarificationForm" method="post" action="../controllers/supervisorInChargeController.php">
                <div class="modal-body py-2">
                    <input type="hidden" name="jobID" id="modal_jobID">
                    <input type="hidden" name="action" value="2">
                    <div class="mb-3">
                        <label for="clarification_comment" class="form-label small">Clarification Request</label>
                        <textarea class="form-control form-control-sm" id="clarification_comment" name="clarification_comment" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/core/jquery-3.7.1.min.js"></script>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
<script src="../assets/js/plugin/datatables/datatables.min.js"></script>
<script src="../assets/js/kaiadmin.min.js"></script>
<script src="../assets/js/setting-demo2.js"></script>
<script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

<script>
$(document).ready(function() {
    // Handle clarify button click
    $(document).on('click', '.btn-clarify', function(e) {
        e.preventDefault();
        var jobID = $(this).closest('form').find('input[name="jobID"]').val();
        $('#modal_jobID').val(jobID);
        $('#clarificationModal').modal('show');
    });

    // SweetAlert for Approve/Reject
    $(document).on('click', 'form[action$="supervisorInChargeController.php"] .action-btn', function(e) {
        var btn = $(this);
        var form = btn.closest('form');
        var isApprove = btn.hasClass('btn-success');
        var isReject = btn.hasClass('btn-danger');
        if (!isApprove && !isReject) return;
        
        e.preventDefault();
        var actionText = isApprove ? 'approve this job' : 'reject this job';
        var confirmButtonText = isApprove ? 'Yes, Approve' : 'Yes, Reject';
        
        swal({
            title: 'Are you sure?',
            text: 'Do you want to ' + actionText + '?',
            icon: 'warning',
            buttons: {
                cancel: {
                    text: 'Cancel',
                    visible: true,
                    className: 'btn btn-default'
                },
                confirm: {
                    text: confirmButtonText,
                    visible: true,
                    className: isApprove ? 'btn btn-success' : 'btn btn-danger'
                }
            },
            dangerMode: isReject
        }).then(function(willDo) {
            if (willDo) {
                if (form.find('input[name="action"]').length) {
                    form.find('input[name="action"]').val(btn.val());
                }
                form[0].submit();
            }
        });
    });
});
</script>
</body>
</html>
