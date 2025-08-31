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
      .evidence-link {
        font-size: 0.8rem;
      }
      .clarification-table {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        overflow: hidden;
        background-color: #fff;
        font-size: 0.85rem;
      }
      .clarification-header {
        display: flex;
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        font-size: 0.85rem;
      }
      .clarification-row {
        display: flex;
        border-bottom: 1px solid #dee2e6;
        transition: background-color 0.2s ease;
      }
      .clarification-row:hover {
        background-color: #f8f9fa;
      }
      .clarification-row:last-child {
        border-bottom: none;
      }
      .clarification-col {
        flex: 1;
        padding: 0.6rem;
        display: flex;
        align-items: center;
        word-wrap: break-word;
        min-width: 0;
      }
      .clarification-col:not(:last-child) {
        border-right: 1px solid #dee2e6;
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
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold mb-0">Job Approvals</h4>
                    <div class="badge bg-light text-dark"><?= count($pendingJobs) ?> Pending</div>
                </div>
                
                <div class="alert alert-info py-2">
                    <i class="fas fa-info-circle me-2"></i> Review and verify completed jobs submitted by employees.
                </div>
                
                <!-- Pending Clarification Approval Section -->
                <?php if (!empty($jobsWithPendingClarificationApproval)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-question-circle me-2"></i>Pending Clarification Responses</h5>
                    <div class="alert alert-info py-2 mb-3">
                        <i class="fas fa-info-circle me-2"></i> These clarifications have been resolved by supervisor-in-charge and need your approval to proceed.
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
                            <div class="card compact-card job-card-pending">
                                <div class="compact-card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">Job #<?= htmlspecialchars($jobID) ?></h6>
                                        <span class="badge bg-primary text-white status-badge">Response Pending</span>
                                    </div>
                                </div>
                                
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <!-- Left Column - Job Details -->
                                        <div>
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
                                        </div>
                                        
                                        <!-- Right Column - Clarification Table -->
                                        <div>
                                            <h6 class="section-title">Pending Clarification Requests</h6>
                                            <div class="clarification-table">
                                                <div class="clarification-header">
                                                    <div class="clarification-col">Request ID</div>
                                                    <div class="clarification-col">Request</div>
                                                    <div class="clarification-col">Response</div>
                                                </div>
                                                <?php foreach ($clarifications as $index => $clarification): ?>
                                                <div class="clarification-row">
                                                    <div class="clarification-col">
                                                        <span class="badge bg-primary"><?= $clarification['clarification_id'] ?></span>
                                                    </div>
                                                    <div class="clarification-col">
                                                        <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                                    </div>
                                                    <div class="clarification-col">
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
                                <div class="compact-card-footer">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <!-- See More Details Button -->
                                        <a href="jobdetails.php?jobID=<?= $jobID ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye me-1"></i> See More Details
                                        </a>
                                        
                                        <form method="post" action="../controllers/approveJobsController.php" class="d-flex gap-2">
                                            <input type="hidden" name="clarification_pending_id" value="<?= $clarifications[0]['clarification_id'] ?>">
                                            <input type="hidden" name="clarification_action" value="">
                                            <button type="button" name="clarification_action_btn" value="1" class="btn btn-success btn-sm action-btn">
                                                <i class="fas fa-check me-1"></i> Approve Resolution
                                            </button>
                                            <button type="button" name="clarification_action_btn" value="3" class="btn btn-danger btn-sm action-btn">
                                                <i class="fas fa-times me-1"></i> Reject Resolution
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
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-warning"><i class="fas fa-exclamation-circle me-2"></i>Jobs with Pending Clarifications</h5>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-info-circle me-2"></i> These jobs have clarification requests that are waiting for supervisor-in-charge response. Jobs cannot proceed until clarifications are resolved.
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
                            <div class="card compact-card job-card-clarification">
                                <div class="compact-card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">Job #<?= htmlspecialchars($jobID) ?></h6>
                                        <span class="badge bg-warning text-dark status-badge">Clarification Needed</span>
                                    </div>
                                </div>
                                
                                <div class="compact-card-body">
                                    <div class="two-column-grid">
                                        <!-- Left Column - Job Details -->
                                        <div>
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
                                        </div>
                                        
                                        <!-- Right Column - Clarification Table -->
                                        <div>
                                            <h6 class="section-title">Clarification Requests</h6>
                                            <div class="clarification-table">
                                                <div class="clarification-header">
                                                    <div class="clarification-col">Request ID</div>
                                                    <div class="clarification-col">Request</div>
                                                    <div class="clarification-col">Status</div>
                                                </div>
                                                <?php foreach ($clarifications as $index => $clarification): ?>
                                                <div class="clarification-row">
                                                    <div class="clarification-col">
                                                        <span class="badge bg-warning text-dark"><?= $clarification['clarification_id'] ?></span>
                                                    </div>
                                                    <div class="clarification-col">
                                                        <?= htmlspecialchars($clarification['clarification_request_comment']) ?>
                                                    </div>
                                                    <div class="clarification-col">
                                                        <span class="badge bg-warning text-dark">Waiting for Supervisor-in-Charge Response</span>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- See More Details Button -->
                                <div class="compact-card-footer">
                                    <div class="d-flex justify-content-start">
                                        <a href="jobdetails.php?jobID=<?= $jobID ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye me-1"></i> See More Details
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
                <div class="job-section">
                    <h5 class="fw-bold mb-3"><i class="fas fa-tasks me-2"></i>Pending Job Approvals</h5>
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
                            <div class="card compact-card job-card-pending">
                                <div class="compact-card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h6>
                                        <?php if (!empty($item['job_creator'])): ?>
                                        <small class="text-muted">By: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (empty($job['end_date'])): ?>
                                        <span class="badge bg-info status-badge">Ongoing</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary status-badge">Pending Approval</span>
                                    <?php endif; ?>
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
                                            
                                            <div class="d-flex gap-2 mt-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#specialProjectModal" data-jobid="<?= $job['jobID'] ?>">
                                                    <i class="fas fa-plus"></i> Add Project
                                                </button>
                                                <?php if (!empty($special_projects)): ?>
                                                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewSpecialProjectModal" data-sp='<?= json_encode($special_projects[0]) ?>'>
                                                    <i class="fas fa-eye"></i> View Project
                                                </button>
                                                <?php endif; ?>
                                            </div>
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
                                        <div class="text-center mt-2">
                                            <span class="badge bg-info text-white">
                                                <i class="fas fa-clock me-1"></i> Ongoing Job
                                            </span>
                                            <small class="text-muted d-block mt-1">Job must be completed (end date added) before approval</small>
                                        </div>
                                    <?php else: ?>
                                        <!-- Completed Job - Buttons Enabled -->
                                        <form method="post" action="../controllers/approveJobsController.php" class="d-flex justify-content-end gap-1">
                                            <input type="hidden" name="job_attendanceID" value="<?= $firstAttendanceID ?>">
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
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-success text-center py-4 my-4">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                    <h4 class="fw-bold">No Pending Approvals</h4>
                    <p class="text-muted">All jobs have been reviewed and approved, or are waiting for clarification resolution.</p>
                </div>
                <?php endif; ?>
                
                <!-- Ongoing Jobs Section -->
                
                <?php if (!empty($ongoingJobs)): ?>
                <div class="job-section">
                    <h5 class="fw-bold mb-3 text-info"><i class="fas fa-clock me-2"></i>Ongoing Jobs</h5>
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
                            <div class="card compact-card job-card-pending">
                                <div class="compact-card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">Job #<?= htmlspecialchars($job['jobID']) ?></h6>
                                        <?php if (!empty($item['job_creator'])): ?>
                                        <small class="text-muted">By: <?= htmlspecialchars($item['job_creator']['fname'] . ' ' . $item['job_creator']['lname']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-info status-badge">Ongoing</span>
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
                                                        <span class="text-warning">Not closed</span>
                                                    </div>
                                                </div>
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
                                                <div class="d-flex justify-content-end mt-2">
                                                <a href="jobdetails.php?jobID=<?= $job['jobID'] ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i> See More Details
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
<div class="modal fade" id="clarificationModal" tabindex="-1" aria-hidden="true">
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

<!-- Special Project Modal -->
<div class="modal fade" id="specialProjectModal" tabindex="-1" aria-labelledby="specialProjectModalLabel" aria-hidden="true">
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
<div class="modal fade" id="viewSpecialProjectModal" tabindex="-1" aria-labelledby="viewSpecialProjectModalLabel" aria-hidden="true">
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
    })
});
</script>
</body>
</html>