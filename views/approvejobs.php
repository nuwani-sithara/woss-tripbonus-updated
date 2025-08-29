<?php
require_once("../controllers/approveJobsController.php");

// session_start();
include '../config/dbConnect.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has role_id = 1
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 4) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Define pending and ongoing jobs arrays
$pendingJobs = array_filter($jobs, function($item) {
    global $conn;
    $jobID = $item['job']['jobID'];
    
    // Skip ongoing jobs (jobs without end_date)
    if (empty($item['job']['end_date'])) {
        return false;
    }
    
    $approvalRes = $conn->query("SELECT approval_status FROM approvals WHERE jobID = $jobID AND approval_stage = 'job_approval' ORDER BY approvalID DESC LIMIT 1");
    if ($approvalRes && $approvalRow = $approvalRes->fetch_assoc()) {
        return $approvalRow['approval_status'] != 1 && $approvalRow['approval_status'] != 3;
    }
    return true;
});

$ongoingJobs = array_filter($jobs, function($item) {
    return empty($item['job']['end_date']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Job Approvals - SubseaOps</title>
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
      :root {
        --compact-padding: 0.5rem;
        --compact-margin: 0.25rem;
        --compact-font-sm: 0.8rem;
        --compact-font-xs: 0.75rem;
      }
      
      .compact-card {
        transition: all 0.2s ease;
        border-left: 3px solid #dee2e6;
        margin-bottom: 1rem;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
      }
      
      .compact-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.06);
        border-left-color: #0d6efd;
      }
      
      .compact-card .card-body {
        padding: 0.75rem;
      }
      
      .compact-card .card-footer {
        padding: 0.75rem;
        background-color: #f8fafc;
        border-top: 1px solid rgba(0,0,0,0.05);
      }
      
      .compact-header {
        padding-bottom: 0.5rem;
        margin-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
      }
      
      .compact-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
      }
      
      .compact-meta {
        font-size: var(--compact-font-sm);
        color: #6c757d;
        line-height: 1.3;
      }
      
      .compact-badge {
        font-size: var(--compact-font-xs);
        padding: 0.2rem 0.5rem;
      }
      
      .compact-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 0.5rem;
        margin-bottom: 0.5rem;
      }
      
      .compact-grid-item {
        margin-bottom: 0.25rem;
      }
      
      .compact-grid-label {
        font-size: var(--compact-font-xs);
        color: #6c757d;
        margin-bottom: 0.1rem;
      }
      
      .compact-grid-value {
        font-size: var(--compact-font-sm);
        font-weight: 500;
        color: #495057;
      }
      
      .compact-duration {
        background-color: #f8f9fa;
        border-radius: 4px;
        padding: 0.4rem 0.5rem;
        margin-bottom: 0.5rem;
      }
      
      .compact-duration-item {
        font-size: var(--compact-font-sm);
      }
      
      .compact-employee-badge {
        font-size: var(--compact-font-xs);
        margin-right: 0.2rem;
        margin-bottom: 0.2rem;
        padding: 0.2rem 0.4rem;
      }
      
      .compact-action-btn {
        min-width: 80px;
        font-size: var(--compact-font-sm);
        padding: 0.3rem 0.5rem;
      }
      
      .compact-section-title {
        font-size: var(--compact-font-sm);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 0.5rem;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.25rem;
      }
      
      .compact-trip-card {
        border: 1px solid #e9ecef;
        border-radius: 4px;
        padding: 0.5rem;
        margin-bottom: 0.5rem;
        background-color: #fff;
      }
      
      .compact-trip-card:hover {
        background-color: #f8f9fa;
      }
      
      .compact-project-item {
        border-left: 2px solid #0d6efd;
        padding-left: 0.4rem;
        margin-bottom: 0.4rem;
        font-size: var(--compact-font-sm);
      }
      
      .compact-evidence-link {
        font-size: var(--compact-font-xs);
      }
      
      .compact-clarification-table {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        overflow: hidden;
        background-color: #fff;
        font-size: var(--compact-font-sm);
      }
      
      .compact-clarification-header {
        display: flex;
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        font-size: var(--compact-font-sm);
      }
      
      .compact-clarification-row {
        display: flex;
        border-bottom: 1px solid #dee2e6;
        transition: background-color 0.2s ease;
      }
      
      .compact-clarification-row:hover {
        background-color: #f8f9fa;
      }
      
      .compact-clarification-row:last-child {
        border-bottom: none;
      }
      
      .compact-clarification-col {
        flex: 1;
        padding: 0.5rem;
        display: flex;
        align-items: center;
        word-wrap: break-word;
        min-width: 0;
      }
      
      .compact-clarification-col:not(:last-child) {
        border-right: 1px solid #dee2e6;
      }
      
      .compact-alert {
        padding: 0.5rem;
        margin-bottom: 0.75rem;
        font-size: var(--compact-font-sm);
      }
      
      .compact-form .form-control {
        padding: 0.3rem 0.5rem;
        font-size: var(--compact-font-sm);
      }
      
      .compact-form label {
        font-size: var(--compact-font-xs);
        margin-bottom: 0.2rem;
      }
      
      .compact-modal .modal-header {
        padding: 0.75rem;
      }
      
      .compact-modal .modal-body {
        padding: 0.75rem;
      }
      
      .compact-modal .modal-footer {
        padding: 0.75rem;
      }
      
      .compact-detail-row {
        display: flex;
        align-items: center;
        margin-bottom: 0.4rem;
        font-size: var(--compact-font-sm);
      }
      
      .compact-detail-label {
        font-weight: 600;
        color: #6c757d;
        min-width: 70px;
        margin-right: 0.4rem;
        font-size: var(--compact-font-xs);
      }
      
      .compact-detail-value {
        color: #495057;
        font-weight: 500;
      }
      
      .compact-section {
        margin-bottom: 1rem;
      }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <div class="main-header-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="dark">
                    <a href="../views/admindashboard.php" class="logo">
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
                <div class="page-header d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Job Approvals</h4>
                    <div class="badge bg-light text-dark compact-badge"><?= count($pendingJobs) ?> Pending</div>
                </div>
                
                <div class="alert alert-info compact-alert">
                    <i class="fas fa-info-circle me-2"></i> Review and verify completed jobs submitted by employees.
                </div>
                
                <!-- Pending Clarification Approval Section -->
                <?php if (!empty($jobsWithPendingClarificationApproval)): ?>
                <div class="compact-section">
                    <h5 class="fw-bold mb-2 text-primary"><i class="fas fa-question-circle me-1"></i>Pending Clarification Responses</h5>
                    <div class="alert alert-info compact-alert mb-2">
                        <i class="fas fa-info-circle me-1"></i> These clarifications have been resolved and need your approval.
                    </div>
                    <div class="row">
                        <?php 
                        // Group clarifications by jobID
                        $pendingClarificationsByJob = [];
                        foreach ($jobsWithPendingClarificationApproval as $item) {
                            $jobID = $item['clarification']['jobID'];
                            if (!isset($pendingClarificationsByJob[$jobID])) {
                                $pendingClarificationsByJob[$jobID] = [
                                    'job_data' => $item,
                                    'clarifications' => []
                                ];
                            }
                            $pendingClarificationsByJob[$jobID]['clarifications'][] = $item['clarification'];
                        }
                        
                        foreach ($pendingClarificationsByJob as $jobID => $jobData): 
                            $item = $jobData['job_data'];
                            $clarifications = $jobData['clarifications'];
                            $boat = $item['boat'];
                            $employees = $item['employees'];
                            $port = $item['port'];
                            $special_projects = $item['special_projects'];
                        ?>
                        <div class="col-md-12">
                            <div class="card compact-card border-primary">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Left Column - Job Details -->
                                        <div class="col-md-4">
                                            <div class="compact-header">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <h5 class="compact-title mb-0">Job #<?= htmlspecialchars($jobID) ?></h5>
                                                    <span class="badge bg-primary text-white compact-badge">Response Pending</span>
                                                </div>
                                                
                                                <div class="compact-detail-row">
                                                    <span class="compact-detail-label">Vessel:</span>
                                                    <span class="compact-detail-value"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></span>
                                                </div>
                                                <div class="compact-detail-row">
                                                    <span class="compact-detail-label">Job Type:</span>
                                                    <span class="compact-detail-value"><?= htmlspecialchars($item['job_type'] ?? '-') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Right Column - Clarification Table -->
                                        <div class="col-md-8">
                                            <h6 class="fw-semibold mb-2">Pending Clarification Requests:</h6>
                                            <div class="compact-clarification-table">
                                                <div class="compact-clarification-header">
                                                    <div class="compact-clarification-col">Request ID</div>
                                                    <div class="compact-clarification-col">Request</div>
                                                    <div class="compact-clarification-col">Response</div>
                                                </div>
                                                <?php foreach ($clarifications as $index => $clarification): ?>
                                                <div class="compact-clarification-row">
                                                    <div class="compact-clarification-col">
                                                        <span class="badge bg-primary compact-badge"><?= $clarification['clarification_id'] ?></span>
                                                    </div>
                                                    <div class="compact-clarification-col">
                                                        <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                                    </div>
                                                    <div class="compact-clarification-col">
                                                        <?php if (!empty($clarification['clarification_resolved_comment'])): ?>
                                                            <span class="text-success"><?= htmlspecialchars($clarification['clarification_resolved_comment']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Pending Response</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0 pt-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <!-- See More Details Button -->
                                        <a href="jobdetails.php?jobID=<?= $jobID ?>" class="btn btn-info btn-sm compact-action-btn">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </a>
                                        
                                        <form method="post" action="../controllers/approveJobsController.php" class="d-flex gap-1">
                                            <input type="hidden" name="clarification_pending_id" value="<?= $clarifications[0]['clarification_id'] ?>">
                                            <input type="hidden" name="clarification_action" value="">
                                            <button type="button" name="clarification_action_btn" value="1" class="btn btn-success btn-sm compact-action-btn">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" name="clarification_action_btn" value="3" class="btn btn-danger btn-sm compact-action-btn">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Clarification Jobs Section -->
                <?php if (!empty($jobsWithClarifications)): ?>
                <div class="compact-section">
                    <h5 class="fw-bold mb-2 text-warning"><i class="fas fa-exclamation-circle me-1"></i>Jobs with Pending Clarifications</h5>
                    <div class="alert alert-warning compact-alert mb-2">
                        <i class="fas fa-info-circle me-1"></i> These jobs have clarification requests waiting for supervisor response.
                    </div>
                    <div class="row">
                        <?php 
                        // Group clarifications by jobID
                        $clarificationsByJob = [];
                        foreach ($jobsWithClarifications as $item) {
                            $jobID = $item['clarification']['jobID'];
                            if (!isset($clarificationsByJob[$jobID])) {
                                $clarificationsByJob[$jobID] = [
                                    'job_data' => $item,
                                    'clarifications' => []
                                ];
                            }
                            $clarificationsByJob[$jobID]['clarifications'][] = $item['clarification'];
                        }
                        
                        foreach ($clarificationsByJob as $jobID => $jobData): 
                            $item = $jobData['job_data'];
                            $clarifications = $jobData['clarifications'];
                            $boat = $item['boat'];
                            $employees = $item['employees'];
                            $port = $item['port'];
                            $special_projects = $item['special_projects'];
                        ?>
                        <div class="col-md-12">
                            <div class="card compact-card border-warning">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Left Column - Job Details -->
                                        <div class="col-md-4">
                                            <div class="compact-header">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <h5 class="compact-title mb-0">Job #<?= htmlspecialchars($jobID) ?></h5>
                                                    <span class="badge bg-warning text-dark compact-badge">Clarification Needed</span>
                                                </div>
                                                
                                                <div class="compact-detail-row">
                                                    <span class="compact-detail-label">Vessel:</span>
                                                    <span class="compact-detail-value"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></span>
                                                </div>
                                                <div class="compact-detail-row">
                                                    <span class="compact-detail-label">Job Type:</span>
                                                    <span class="compact-detail-value"><?= htmlspecialchars($item['job_type'] ?? '-') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Right Column - Clarification Table -->
                                        <div class="col-md-8">
                                            <h6 class="fw-semibold mb-2">Clarification Requests:</h6>
                                            <div class="compact-clarification-table">
                                                <div class="compact-clarification-header">
                                                    <div class="compact-clarification-col">Request ID</div>
                                                    <div class="compact-clarification-col">Request</div>
                                                    <div class="compact-clarification-col">Status</div>
                                                </div>
                                                <?php foreach ($clarifications as $index => $clarification): ?>
                                                <div class="compact-clarification-row">
                                                    <div class="compact-clarification-col">
                                                        <span class="badge bg-warning text-dark compact-badge"><?= $clarification['clarification_id'] ?></span>
                                                    </div>
                                                    <div class="compact-clarification-col">
                                                        <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                                    </div>
                                                    <div class="compact-clarification-col">
                                                        <span class="badge bg-warning text-dark compact-badge">Waiting for Response</span>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- See More Details Button -->
                                <div class="card-footer bg-white border-top-0 pt-0">
                                    <div class="d-flex justify-content-start">
                                        <a href="jobdetails.php?jobID=<?= $jobID ?>" class="btn btn-info btn-sm compact-action-btn">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Main Job Approval Section -->
                
                <?php if (!empty($pendingJobs)): ?>
                <div class="compact-section">
                    <h5 class="fw-bold mb-2"><i class="fas fa-tasks me-1"></i>Pending Job Approvals</h5>
                    <div class="row">
                        <?php foreach ($pendingJobs as $item): 
                            $job = $item['job'];
                            // Check if this job has any pending clarifications (status = 0 or 1)
                            $hasPendingClarifications = false;
                            if (!empty($jobsWithClarifications)) {
                                foreach ($jobsWithClarifications as $clarifyItem) {
                                    if ($clarifyItem['clarification']['jobID'] == $job['jobID'] && 
                                        in_array($clarifyItem['clarification']['clarification_status'], [0, 1])) {
                                        $hasPendingClarifications = true;
                                        break;
                                    }
                                }
                            }
                            // Skip jobs that have pending clarifications (status = 0 or 1)
                            if ($hasPendingClarifications) {
                                continue;
                            }
                            $boat = $item['boat'];
                            $port = $item['port'];
                            $special_projects = $item['special_projects'];
                            $vessel_name = $item['vessel_name'];
                            $job_type = $item['job_type'];
                            $job_creator = $item['job_creator'];
                            $trips = $item['trips'];
                        ?>
                        <div class="col-md-12">
                            <div class="card compact-card">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Left Column - Job Details -->
                                        <div class="col-md-5 border-end pe-2">
                                            <div class="compact-header">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <h5 class="compact-title mb-0">Job #<?= htmlspecialchars($job['jobID']) ?></h5>
                                                    <?php if (empty($job['end_date'])): ?>
                                                        <span class="badge bg-info compact-badge">Ongoing</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary compact-badge">Pending Approval</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($item['job_creator'])): ?>
                                                <div class="compact-meta">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    Created by: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($job['comment']): ?>
                                                <div class="mt-1">
                                                    <div class="compact-meta">Note</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($job['comment']) ?></div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="compact-grid mt-2">
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Vessel</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Job Type</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Boat</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Port</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="compact-duration mt-1">
                                                <div class="d-flex justify-content-between compact-duration-item">
                                                    <div>
                                                        <div class="compact-grid-label">Start</div>
                                                        <div class="compact-grid-value"><?= htmlspecialchars($job['start_date']) ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="compact-grid-label">End</div>
                                                        <div class="compact-grid-value">
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
                                                <div class="compact-grid-label">Special Projects</div>
                                                <div class="mt-1">
                                                    <?php foreach ($special_projects as $sp): ?>
                                                    <div class="compact-project-item">
                                                        <div class="compact-grid-value"><?= htmlspecialchars($sp['name'] ?? 'Special Project') ?></div>
                                                        <?php if (!empty($sp['evidence'])): ?>
                                                        <a href="../uploads/evidence/<?= htmlspecialchars($sp['evidence']) ?>" 
                                                           target="_blank" 
                                                           class="compact-evidence-link text-primary d-inline-block mt-1">
                                                            <i class="fas fa-paperclip me-1"></i> Evidence
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2 d-flex gap-1">
                                                <button type="button" class="btn btn-outline-primary btn-sm compact-action-btn" data-bs-toggle="modal" data-bs-target="#specialProjectModal" data-jobid="<?= $job['jobID'] ?>">
                                                    <i class="fas fa-plus"></i> Add Project
                                                </button>
                                                <?php if (!empty($special_projects)): ?>
                                                <button type="button" class="btn btn-outline-info btn-sm compact-action-btn" data-bs-toggle="modal" data-bs-target="#viewSpecialProjectModal" data-sp='<?= json_encode($special_projects[0]) ?>'>
                                                    <i class="fas fa-eye"></i> View Project
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Right Column - Trips -->
                                        <div class="col-md-7 ps-2">
                                            <h6 class="compact-section-title">Trips / Days</h6>
                                            
                                            <?php if (empty($item['trips'])): ?>
                                            <div class="alert alert-warning compact-alert mb-0">
                                                <i class="fas fa-exclamation-triangle me-1"></i> No days/trips added yet.
                                            </div>
                                            <?php else: ?>
                                                <?php foreach ($item['trips'] as $tripItem): ?>
                                                <div class="compact-trip-card">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <strong class="compact-grid-value"><?= htmlspecialchars($tripItem['trip']['trip_date']) ?></strong>
                                                        <?php if (isset($tripItem['attendance']['attendance_status'])): ?>
                                                            <?php
                                                                $status = $tripItem['attendance']['attendance_status'];
                                                                $statusText = $status == 1 ? 'Verified' : ($status == 3 ? 'Rejected' : 'Pending');
                                                                $statusClass = $status == 1 ? 'bg-success text-white' : ($status == 3 ? 'bg-danger text-white' : 'bg-warning text-dark');
                                                            ?>
                                                            <span class="badge <?= $statusClass ?> compact-badge"><?= $statusText ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($tripItem['employees'])): ?>
                                                    <div class="compact-meta mt-1">
                                                        <div>Team Members:</div>
                                                        <div class="mt-1">
                                                            <?php foreach ($tripItem['employees'] as $emp): ?>
                                                            <span class="badge bg-light text-dark compact-employee-badge">
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
                                
                                <?php
                                $firstAttendanceID = null;
                                foreach ($trips as $tripItem) {
                                    if (!empty($tripItem['attendance']['job_attendanceID'])) {
                                        $firstAttendanceID = $tripItem['attendance']['job_attendanceID'];
                                        break;
                                    }
                                }
                                ?>
                                
                                <?php if ($firstAttendanceID): ?>
                                <div class="card-footer">
                                    <?php if (empty($job['end_date'])): ?>
                                        <!-- Ongoing Job - Buttons Disabled -->
                                        <div class="d-flex justify-content-end gap-1">
                                            <button type="button" class="btn btn-success btn-sm compact-action-btn" disabled>
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm compact-action-btn" disabled>
                                                <i class="fas fa-question me-1"></i> Clarify
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm compact-action-btn" disabled>
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </div>
                                        <div class="text-center mt-2">
                                            <span class="badge bg-info text-white compact-badge">
                                                <i class="fas fa-clock me-1"></i> Ongoing Job
                                            </span>
                                            <small class="text-muted d-block mt-1">Job must be completed before approval</small>
                                        </div>
                                    <?php else: ?>
                                        <!-- Completed Job - Buttons Enabled -->
                                        <form method="post" action="../controllers/approveJobsController.php" class="d-flex justify-content-end gap-1">
                                            <input type="hidden" name="job_attendanceID" value="<?= $firstAttendanceID ?>">
                                            <input type="hidden" name="action" value="">
                                            <button type="button" name="action_btn" value="1" class="btn btn-success btn-sm compact-action-btn">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm compact-action-btn btn-clarify">
                                                <i class="fas fa-question me-1"></i> Clarify
                                            </button>
                                            <button type="button" name="action_btn" value="3" class="btn btn-danger btn-sm compact-action-btn">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- See More Details Button -->
                                    <div class="d-flex justify-content-start mt-2">
                                        <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-sm compact-action-btn">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-success text-center py-3 my-3">
                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                    <h5 class="fw-bold">No Pending Approvals</h5>
                    <p class="text-muted mb-0">All jobs have been reviewed and approved.</p>
                </div>
                <?php endif; ?>
                
                <!-- Ongoing Jobs Section -->
                
                <?php if (!empty($ongoingJobs)): ?>
                <div class="compact-section">
                    <h5 class="fw-bold mb-2 text-info"><i class="fas fa-clock me-1"></i>Ongoing Jobs</h5>
                    <div class="row">
                        <?php foreach ($ongoingJobs as $item): 
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
                            <div class="card compact-card border-info">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Left Column - Job Details -->
                                        <div class="col-md-5 border-end pe-2">
                                            <div class="compact-header">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <h5 class="compact-title mb-0">Job #<?= htmlspecialchars($job['jobID']) ?></h5>
                                                    <span class="badge bg-info compact-badge">Ongoing</span>
                                                </div>
                                                
                                                <?php if (!empty($item['job_creator'])): ?>
                                                <div class="compact-meta">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    Created by: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($job['comment']): ?>
                                                <div class="mt-1">
                                                    <div class="compact-meta">Note</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($job['comment']) ?></div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="compact-grid mt-2">
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Vessel</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($item['vessel_name'] ?? '-') ?></div>
                                                </div>
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Job Type</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($item['job_type'] ?? '-') ?></div>
                                                </div>
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Boat</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($boat['boat_name'] ?? '-') ?></div>
                                                </div>
                                                <div class="compact-grid-item">
                                                    <div class="compact-grid-label">Port</div>
                                                    <div class="compact-grid-value"><?= htmlspecialchars($port['portname'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="compact-duration mt-1">
                                                <div class="d-flex justify-content-between compact-duration-item">
                                                    <div>
                                                        <div class="compact-grid-label">Start</div>
                                                        <div class="compact-grid-value"><?= htmlspecialchars($job['start_date']) ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="compact-grid-label">End</div>
                                                        <div class="compact-grid-value">
                                                            <span class="text-warning">Not closed</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($special_projects): ?>
                                            <div class="mt-2">
                                                <div class="compact-grid-label">Special Projects</div>
                                                <div class="mt-1">
                                                    <?php foreach ($special_projects as $sp): ?>
                                                    <div class="compact-project-item">
                                                        <div class="compact-grid-value"><?= htmlspecialchars($sp['name'] ?? 'Special Project') ?></div>
                                                        <?php if (!empty($sp['evidence'])): ?>
                                                        <a href="../uploads/evidence/<?= htmlspecialchars($sp['evidence']) ?>" 
                                                           target="_blank" 
                                                           class="compact-evidence-link text-primary d-inline-block mt-1">
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
                                        <div class="col-md-7 ps-2">
                                            <h6 class="compact-section-title">Trips / Days</h6>
                                            
                                            <?php if (empty($item['trips'])): ?>
                                            <div class="alert alert-warning compact-alert mb-0">
                                                <i class="fas fa-exclamation-triangle me-1"></i> No days/trips added yet.
                                            </div>
                                            <?php else: ?>
                                                <?php foreach ($item['trips'] as $tripItem): ?>
                                                <div class="compact-trip-card">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <strong class="compact-grid-value"><?= htmlspecialchars($tripItem['trip']['trip_date']) ?></strong>
                                                        <?php if (isset($tripItem['attendance']['attendance_status'])): ?>
                                                            <?php
                                                                $status = $tripItem['attendance']['attendance_status'];
                                                                $statusText = $status == 1 ? 'Verified' : ($status == 3 ? 'Rejected' : 'Pending');
                                                                $statusClass = $status == 1 ? 'bg-success text-white' : ($status == 3 ? 'bg-danger text-white' : 'bg-warning text-dark');
                                                            ?>
                                                            <span class="badge <?= $statusClass ?> compact-badge"><?= $statusText ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($tripItem['employees'])): ?>
                                                    <div class="compact-meta mt-1">
                                                        <div>Team Members:</div>
                                                        <div class="mt-1">
                                                            <?php foreach ($tripItem['employees'] as $emp): ?>
                                                            <span class="badge bg-light text-dark compact-employee-badge">
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
                                                <div class="d-flex justify-content-end mt-2">
                                                <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-sm compact-action-btn">
                                                    <i class="fas fa-eye me-1"></i> Details
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
            </div>
        </div>
        
        <?php include 'components/footer.php'; ?>
    </div>
</div>

<!-- Clarification Modal -->
<div class="modal fade compact-modal" id="clarificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Request Clarification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="clarificationForm" method="post" action="../controllers/approveJobsController.php">
                <div class="modal-body py-2">
                    <input type="hidden" name="job_attendanceID" id="modal_job_attendanceID">
                    <input type="hidden" name="action" value="2">
                    <div class="mb-2">
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

<!-- Special Project Modal -->
<div class="modal fade compact-modal" id="specialProjectModal" tabindex="-1" aria-labelledby="specialProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="specialProjectForm" method="POST" action="../controllers/addSpecialProjectController.php" enctype="multipart/form-data" class="compact-form">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="specialProjectModalLabel">Add Special Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <input type="hidden" name="jobID" id="modalJobID">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="spName" class="form-label small">Project Name</label>
                            <input type="text" class="form-control form-control-sm" id="spName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="spVessel" class="form-label small">Vessel</label>
                            <select class="form-control form-control-sm" id="spVessel" name="vesselID" required>
                                <option value="">Select Vessel</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="spDate" class="form-label small">Date</label>
                            <input type="date" class="form-control form-control-sm" id="spDate" name="date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="spAllowance" class="form-label small">Allowance</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="spAllowance" name="allowance" required>
                        </div>
                        <div class="col-md-12">
                            <label for="spEvidence" class="form-label small">Evidence (PDF, XLSX, Word, Mail Trailer)</label>
                            <input type="file" class="form-control form-control-sm" id="spEvidence" name="evidence" accept=".pdf, .xlsx, .xls, .doc, .docx, .eml, .msg">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Add Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View/Edit Special Project Modal -->
<div class="modal fade compact-modal" id="viewSpecialProjectModal" tabindex="-1" aria-labelledby="viewSpecialProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editSpecialProjectForm" method="POST" action="../controllers/updateSpecialProjectController.php" enctype="multipart/form-data" class="compact-form">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="viewSpecialProjectModalLabel">Special Project Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <input type="hidden" name="spProjectID" id="editSpProjectID">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="editSpName" class="form-label small">Project Name</label>
                            <input type="text" class="form-control form-control-sm" id="editSpName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editSpVessel" class="form-label small">Vessel</label>
                            <select class="form-control form-control-sm" id="editSpVessel" name="vesselID" required>
                                <option value="">Select Vessel</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editSpDate" class="form-label small">Date</label>
                            <input type="date" class="form-control form-control-sm" id="editSpDate" name="date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editSpAllowance" class="form-label small">Allowance</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="editSpAllowance" name="allowance" required>
                        </div>
                        <div class="col-md-12">
                            <label for="editSpEvidence" class="form-label small">Evidence (PDF, XLSX, Word, Mail Trailer)</label>
                            <input type="file" class="form-control form-control-sm" id="editSpEvidence" name="evidence" accept=".pdf, .xlsx, .xls, .doc, .docx, .eml, .msg">
                            <div id="currentEvidence" class="mt-1 small"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
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
        var jobAttendanceID = $(this).closest('form').find('input[name="job_attendanceID"]').val();
        $('#modal_job_attendanceID').val(jobAttendanceID);
        $('#clarificationModal').modal('show');
    });

    // SweetAlert for Approve/Reject
    $(document).on('click', 'form[action$="approveJobsController.php"] .action-btn', function(e) {
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
                if (form.find('input[name="clarification_action"]').length) {
                    form.find('input[name="clarification_action"]').val(btn.val());
                }
                form[0].submit();
            }
        });
    });

    // Set jobID in modal when button is clicked
    $(document).on('click', '[data-bs-target="#specialProjectModal"]', function() {
        var jobID = $(this).data('jobid');
        $('#modalJobID').val(jobID);
        $('#specialProjectForm')[0].reset();
    });

    // Load vessels for dropdowns
    function loadVessels() {
        console.log('Loading vessels...');
        $.ajax({
            url: '../controllers/getVesselsController.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Vessels response:', response);
                if (response.success) {
                    var vesselOptions = '<option value="">Select Vessel</option>';
                    response.data.forEach(function(vessel) {
                        vesselOptions += '<option value="' + vessel.vesselID + '">' + vessel.vessel_name + '</option>';
                    });
                    $('#spVessel, #editSpVessel').html(vesselOptions);
                    console.log('Loaded', response.data.length, 'vessels');
                } else {
                    console.error('Failed to load vessels:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load vessels:', error);
                console.error('Response:', xhr.responseText);
            }
        });
    }

    // Load vessels when page loads
    loadVessels();

    // Populate special project modal on view
    $(document).on('click', '[data-bs-target="#viewSpecialProjectModal"]', function() {
        var sp = $(this).data('sp');
        if (typeof sp === 'string') sp = JSON.parse(sp);
        $('#editSpProjectID').val(sp.spProjectID);
        $('#editSpName').val(sp.name);
        
        // For edit modal, we need to find the vesselID based on vessel name
        if (sp.vessel_name) {
            $('#editSpVessel option').each(function() {
                if ($(this).text() === sp.vessel_name) {
                    $('#editSpVessel').val($(this).val());
                    return false;
                }
            });
        }
        
        $('#editSpDate').val(sp.date ? sp.date.split('T')[0] : '');
        $('#editSpAllowance').val(sp.allowance);
        if (sp.evidence) {
            var fileName = sp.evidence;
            $('#currentEvidence').html('<a href="../uploads/evidence/' + sp.evidence + '" target="_blank" class="text-primary">Current: ' + fileName + '</a>');
        } else {
            $('#currentEvidence').html('<span class="text-muted">No evidence uploaded</span>');
        }
    });
});
</script>
</body>
</html>