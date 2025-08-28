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
    <title>Supervisor-in-Charge Approvals - SubseaOps</title>
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
    <style>
      .compact-card {
        border-radius: 6px;
        margin-bottom: 1rem;
        transition: all 0.2s ease;
        border-left: 3px solid #dee2e6;
      }
      .compact-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.08);
      }
      .job-card-pending {
        border-left-color: #0d6efd;
      }
      .job-card-rejected {
        border-left-color: #dc3545;
      }
      .job-card-clarification {
        border-left-color: #ffc107;
      }
      .job-card-resolved {
        border-left-color: #198754;
      }
      .compact-card-header {
        padding: 0.75rem 1rem;
        background-color: #f8fafc;
        border-bottom: 1px solid rgba(0,0,0,0.05);
      }
      .compact-card-body {
        padding: 1rem;
      }
      .compact-card-footer {
        padding: 0.75rem 1rem;
        background-color: #f8fafc;
        border-top: 1px solid rgba(0,0,0,0.05);
      }
      .job-meta {
        font-size: 0.8rem;
        color: #6c757d;
      }
      .job-value {
        font-size: 0.85rem;
        font-weight: 500;
        color: #212529;
      }
      .employee-badge {
        font-size: 0.75rem;
        margin-right: 0.2rem;
        margin-bottom: 0.2rem;
        padding: 0.2rem 0.4rem;
      }
      .action-btn {
        min-width: 80px;
        font-size: 0.8rem;
        padding: 0.3rem 0.5rem;
      }
      .status-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
      }
      .job-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 0.75rem;
        margin-bottom: 0.75rem;
      }
      .trip-card {
        border: 1px solid #e9ecef;
        border-radius: 4px;
        padding: 0.5rem;
        margin-bottom: 0.5rem;
        background-color: #fff;
        font-size: 0.8rem;
      }
      .trip-card:hover {
        background-color: #f8f9fa;
      }
      .special-project-item {
        border-left: 2px solid #0d6efd;
        padding-left: 0.4rem;
        margin-bottom: 0.4rem;
        font-size: 0.8rem;
      }
      .clarification-request {
        background-color: #e3f2fd;
        border-left: 2px solid #2196f3;
        padding: 0.4rem;
        border-radius: 3px;
        margin-top: 0.4rem;
        font-size: 0.85rem;
      }
      .clarification-response {
        background-color: #e8f5e8;
        border-left: 2px solid #4caf50;
        padding: 0.4rem;
        border-radius: 3px;
        margin-top: 0.4rem;
        font-size: 0.85rem;
      }
      .section-title {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 0.5rem;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.4rem;
      }
      .compact-detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.3rem;
        font-size: 0.8rem;
      }
      .detail-label {
        color: #6c757d;
      }
      .detail-value {
        font-weight: 500;
      }
      .two-column-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
      }
      .three-column-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.75rem;
      }
      .btn-xs {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
        border-radius: 0.2rem;
      }
      .data-placeholder {
        color: #6c757d;
        font-style: italic;
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
                        <img src="../assets/img/app-logo1.png" alt="navbar brand" class="navbar-brand" height="20" />
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
                <div class="mb-4">
                    <h5 class="fw-bold mb-3 text-warning"><i class="fas fa-question-circle me-2"></i>Clarifications to Resolve</h5>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i> These clarifications were requested by the Operations Manager and need your response.
                    </div>
                    <div class="row">
                        <?php foreach ($clarificationsToResolve as $clarification): 
                            // Get job details for this clarification
                            $jobDetails = getJobDetailsForClarification($clarification['jobID']);
                        ?>
                        <div class="col-md-12">
                            <div class="card compact-card job-card-clarification">
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <div>
                                            <h6 class="fw-semibold mb-2">Clarification Request from Operations Manager</h6>
                                            <div class="clarification-request">
                                                <strong>Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <form method="post" action="../controllers/supervisorInChargeController.php">
                                                    <input type="hidden" name="clarification_id" value="<?= $clarification['clarification_id'] ?>">
                                                    <div class="mb-2">
                                                        <label for="resolution_comment_<?= $clarification['clarification_id'] ?>" class="form-label small">Your Response:</label>
                                                        <textarea class="form-control form-control-sm" id="resolution_comment_<?= $clarification['clarification_id'] ?>" name="resolution_comment" rows="2" required placeholder="Provide clarification response..."></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check me-1"></i> Resolve Clarification
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Job ID:</span>
                                                <span class="detail-value"><?= htmlspecialchars($clarification['jobID']) ?></span>
                                            </div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Vessel:</span>
                                                <span class="detail-value">
                                                    <?php if (!empty($jobDetails['vessel_name'])): ?>
                                                        <?= htmlspecialchars($jobDetails['vessel_name']) ?>
                                                    <?php else: ?>
                                                        <span class="data-placeholder">Not available</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Job Type:</span>
                                                <span class="detail-value">
                                                    <?php if (!empty($jobDetails['job_type'])): ?>
                                                        <?= htmlspecialchars($jobDetails['job_type']) ?>
                                                    <?php else: ?>
                                                        <span class="data-placeholder">Not available</span>
                                                    <?php endif; ?>
                                                </span>
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
                <div class="mb-4">
                    <h5 class="fw-bold mb-3 text-info"><i class="fas fa-clock me-2"></i>Pending Clarification Responses</h5>
                    <div class="alert alert-info py-2 mb-3">
                        <i class="fas fa-info-circle me-2"></i> These clarifications were requested by you and are waiting for the supervisor's response.
                    </div>
                    <div class="row">
                        <?php foreach ($pendingClarificationResponses as $clarification): 
                            // Get job details for this clarification
                            $jobDetails = getJobDetailsForClarification($clarification['jobID']);
                        ?>
                        <div class="col-md-12">
                            <div class="card compact-card job-card-clarification">
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <div>
                                            <h6 class="fw-semibold mb-2">Your Clarification Request</h6>
                                            <div class="clarification-request">
                                                <strong>Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark">Waiting for Supervisor Response</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Job ID:</span>
                                                <span class="detail-value"><?= htmlspecialchars($clarification['jobID']) ?></span>
                                            </div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Vessel:</span>
                                                <span class="detail-value">
                                                    <?php if (!empty($jobDetails['vessel_name'])): ?>
                                                        <?= htmlspecialchars($jobDetails['vessel_name']) ?>
                                                    <?php else: ?>
                                                        <span class="data-placeholder">Not available</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Job Type:</span>
                                                <span class="detail-value">
                                                    <?php if (!empty($jobDetails['job_type'])): ?>
                                                        <?= htmlspecialchars($jobDetails['job_type']) ?>
                                                    <?php else: ?>
                                                        <span class="data-placeholder">Not available</span>
                                                    <?php endif; ?>
                                                </span>
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
                <div class="mb-4">
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
                            <div class="card compact-card job-card-clarification">
                                <div class="compact-card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h6>
                                        <?php if (!empty($item['job_creator'])): ?>
                                        <small class="text-muted">By: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-warning text-dark status-badge">Clarification Pending</span>
                                </div>
                                
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <!-- Left Column - Job Details -->
                                        <div>
                                            <?php if ($job['comment']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted job-meta">Note</small>
                                                <div class="small"><?= htmlspecialchars($job['comment']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="job-details-grid">
                                                <div>
                                                    <small class="text-muted job-meta">Vessel</small>
                                                    <div class="job-value"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Job Type</small>
                                                    <div class="job-value"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Boat</small>
                                                    <div class="job-value"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Port</small>
                                                    <div class="job-value"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="three-column-grid mb-2">
                                                <div>
                                                    <small class="text-muted">Start</small>
                                                    <div class="job-value"><?= htmlspecialchars($job['start_date']) ?></div>
                                                </div>
                                                <div class="text-center">
                                                    <small class="text-muted">End</small>
                                                    <div class="job-value">
                                                        <?php if (!empty($job['end_date'])): ?>
                                                            <?= htmlspecialchars($job['end_date']) ?>
                                                        <?php else: ?>
                                                            <span class="text-warning">Not closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <!-- <div class="text-end">
                                                    <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-xs">
                                                        <i class="fas fa-eye me-1"></i> Details
                                                    </a>
                                                </div> -->
                                            </div>
                                            
                                            <?php if ($special_projects): ?>
                                            <div>
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
                                        <div>
                                            <h6 class="section-title">Clarification Request</h6>
                                            <div class="clarification-request">
                                                <strong>Your Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark">Waiting for Supervisor Response</span>
                                                <small class="text-muted d-block mt-1">This job cannot proceed until the clarification is resolved by the supervisor.</small>
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
                <div class="mb-4">
                    <h5 class="fw-bold mb-3 text-success"><i class="fas fa-check-circle me-2"></i>Resolved Clarifications for Approval</h5>
                    <div class="alert alert-success py-2 mb-3">
                        <i class="fas fa-info-circle me-2"></i> These clarifications have been resolved by supervisors and need your approval to proceed.
                    </div>
                    <div class="row">
                        <?php foreach ($resolvedClarificationsForApproval as $clarification): 
                            // Get job details for this clarification
                            $jobDetails = getJobDetailsForClarification($clarification['jobID']);
                        ?>
                        <div class="col-md-12">
                            <div class="card compact-card job-card-resolved">
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <div>
                                            <h6 class="fw-semibold mb-2">Clarification Request & Resolution</h6>
                                            <div class="clarification-request">
                                                <strong>Your Request:</strong> <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                            </div>
                                            
                                            <div class="clarification-response mt-2">
                                                <strong>Supervisor's Response:</strong> <?= htmlspecialchars($clarification['clarification_resolved_comment']) ?>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <form method="post" action="../controllers/supervisorInChargeController.php" class="d-inline">
                                                    <input type="hidden" name="clarification_approval_id" value="<?= $clarification['clarification_id'] ?>">
                                                    <button type="submit" name="clarification_approval_action" value="approve" class="btn btn-success btn-sm me-1">
                                                        <i class="fas fa-check me-1"></i> Approve
                                                    </button>
                                                    <button type="submit" name="clarification_approval_action" value="reject" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-times me-1"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Job ID:</span>
                                                <span class="detail-value"><?= htmlspecialchars($clarification['jobID']) ?></span>
                                            </div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Vessel:</span>
                                                <span class="detail-value">
                                                    <?php if (!empty($jobDetails['vessel_name'])): ?>
                                                        <?= htmlspecialchars($jobDetails['vessel_name']) ?>
                                                    <?php else: ?>
                                                        <span class="data-placeholder">Not available</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="compact-detail-row">
                                                <span class="detail-label">Job Type:</span>
                                                <span class="detail-value">
                                                    <?php if (!empty($jobDetails['job_type'])): ?>
                                                        <?= htmlspecialchars($jobDetails['job_type']) ?>
                                                    <?php else: ?>
                                                        <span class="data-placeholder">Not available</span>
                                                    <?php endif; ?>
                                                </span>
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
                <div class="mb-4">
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
                            <div class="card compact-card job-card-rejected">
                                <div class="compact-card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h6>
                                        <?php if (!empty($item['job_creator'])): ?>
                                        <small class="text-muted">By: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-danger status-badge">Rejected by OM</span>
                                </div>
                                
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <!-- Left Column - Job Details -->
                                        <div>
                                            <?php if ($job['comment']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted job-meta">Note</small>
                                                <div class="small"><?= htmlspecialchars($job['comment']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="job-details-grid">
                                                <div>
                                                    <small class="text-muted job-meta">Vessel</small>
                                                    <div class="job-value"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Job Type</small>
                                                    <div class="job-value"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Boat</small>
                                                    <div class="job-value"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Port</small>
                                                    <div class="job-value"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="three-column-grid mb-2">
                                                <div>
                                                    <small class="text-muted">Start</small>
                                                    <div class="job-value"><?= htmlspecialchars($job['start_date']) ?></div>
                                                </div>
                                                <div class="text-center">
                                                    <small class="text-muted">End</small>
                                                    <div class="job-value">
                                                        <?php if (!empty($job['end_date'])): ?>
                                                            <?= htmlspecialchars($job['end_date']) ?>
                                                        <?php else: ?>
                                                            <span class="text-warning">Not closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <!-- <div class="text-end">
                                                    <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-xs">
                                                        <i class="fas fa-eye me-1"></i> Details
                                                    </a>
                                                </div> -->
                                            </div>
                                            
                                            <?php if ($special_projects): ?>
                                            <div>
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
                                        <div>
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
                                                    <div class="small mt-1">
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
                                
                                <div class="compact-card-footer">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">Rejected on: <?= htmlspecialchars($item['approval_date']) ?></small>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <!-- <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye me-1"></i> Details
                                            </a> -->
                                            <form method="post" action="../controllers/supervisorInChargeController.php" class="d-inline">
                                                <input type="hidden" name="review_jobID" value="<?= $job['jobID'] ?>">
                                                <button type="submit" name="review_action" value="modify" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit me-1"></i> Modify
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
                <div class="mb-4">
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
                            <div class="card compact-card job-card-pending">
                                <div class="compact-card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h6>
                                        <?php if (!empty($item['job_creator'])): ?>
                                        <small class="text-muted">By: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-primary status-badge">Pending Approval</span>
                                </div>
                                
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <!-- Left Column - Job Details -->
                                        <div>
                                            <?php if ($job['comment']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted job-meta">Note</small>
                                                <div class="small"><?= htmlspecialchars($job['comment']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="job-details-grid">
                                                <div>
                                                    <small class="text-muted job-meta">Vessel</small>
                                                    <div class="job-value"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Job Type</small>
                                                    <div class="job-value"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Boat</small>
                                                    <div class="job-value"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div>
                                                    <small class="text-muted job-meta">Port</small>
                                                    <div class="job-value"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="three-column-grid mb-2">
                                                <div>
                                                    <small class="text-muted">Start</small>
                                                    <div class="job-value"><?= htmlspecialchars($job['start_date']) ?></div>
                                                </div>
                                                <div class="text-center">
                                                    <small class="text-muted">End</small>
                                                    <div class="job-value">
                                                        <?php if (!empty($job['end_date'])): ?>
                                                            <?= htmlspecialchars($job['end_date']) ?>
                                                        <?php else: ?>
                                                            <span class="text-warning">Not closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <!-- <div class="text-end">
                                                    <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-xs">
                                                        <i class="fas fa-eye me-1"></i> Details
                                                    </a>
                                                </div> -->
                                            </div>
                                            
                                            <?php if ($special_projects): ?>
                                            <div>
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
                                        <div>
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
                                                    <div class="small mt-1">
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
                                
                                <div class="compact-card-footer">
                                    <?php if (empty($job['end_date'])): ?>
                                        <!-- Ongoing Job - Buttons Disabled -->
                                        <div class="d-flex justify-content-end gap-1">
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
                                    <?php else: ?>
                                        <!-- Completed Job - Buttons Enabled -->
                                        <form method="post" action="../controllers/supervisorInChargeController.php" class="d-flex justify-content-end gap-1">
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