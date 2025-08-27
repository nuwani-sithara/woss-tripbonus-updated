<?php
// session_start();
include '../config/dbConnect.php';
include '../controllers/supervisorEditJobsController.php';

// Check if user is logged in and has role_id = 1 (supervisor)
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// --- Clarification jobs filter (must be before merging logic) ---
$clarificationJobsToShow = array_filter($clarificationJobs ?? [], function($item) use ($clarificationDetails) {
    $clars = $clarificationDetails[$item['job']['jobID']] ?? [];
    // Show job if it has no clarifications or if none of its clarifications are resolved (status 2)
    return empty($clars) || !array_filter($clars, function($clar) {
        return $clar['clarification_status'] == 2;
    });
});

// --- BEGIN: Merge all jobs into a single array with status ---
$allJobs = [];
foreach ($editableJobs as $item) {
    $item['status'] = 'Editable';
    $allJobs[] = $item;
}
foreach ($readOnlyJobs as $item) {
    $item['status'] = 'Approved';
    $allJobs[] = $item;
}
foreach ($rejectedJobs as $item) {
    $item['status'] = 'Rejected';
    $allJobs[] = $item;
}
foreach ($clarificationJobsToShow as $item) {
    $item['status'] = 'Clarification Needed';
    $allJobs[] = $item;
}
foreach ($pendingApprovalClarificationJobs as $item) {
    $item['status'] = 'Pending Approval';
    $allJobs[] = $item;
}
// Optional: sort by jobID descending (latest first)
usort($allJobs, function($a, $b) {
    return $b['job']['jobID'] <=> $a['job']['jobID'];
});
// --- END: Merge all jobs ---

// --- BEGIN: Filter logic (before pagination) ---
$filter_month = $_GET['filter_month'] ?? '';
$filter_jobtype = $_GET['filter_jobtype'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$filteredJobs = array_filter($allJobs, function($item) use ($filter_month, $filter_jobtype, $filter_status) {
    $match = true;
    // Month filter: match if start or end date is in the selected month
    if ($filter_month) {
        $month = $filter_month;
        $startMonth = substr($item['job']['start_date'], 0, 7);
        $endMonth = substr($item['job']['end_date'], 0, 7);
        if ($startMonth !== $month && $endMonth !== $month) {
            $match = false;
        }
    }
    // Job type filter
    if ($filter_jobtype && (!isset($item['job_type']['jobtypeID']) || $item['job_type']['jobtypeID'] != $filter_jobtype)) {
        $match = false;
    }
    // Status filter
    if ($filter_status && $item['status'] !== $filter_status) {
        $match = false;
    }
    return $match;
});
// --- END: Filter logic ---

// --- BEGIN: Pagination logic ---
$jobsPerPage = 10;
$totalJobs = count($filteredJobs);
$totalPages = ceil($totalJobs / $jobsPerPage);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$start = ($page - 1) * $jobsPerPage;
$paginatedJobs = array_slice(array_values($filteredJobs), $start, $jobsPerPage);
// --- END: Pagination logic ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Supervisor Jobs - SubseaOps</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../assets/img/app-logo1.png" type="image/x-icon" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .job-card {
            transition: all 0.3s ease;
            border-radius: 6px;
            border-left: 3px solid #1F3BB3;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 0.95rem;
            min-height: unset;
        }
        .job-card .card-body {
            padding: 0;
        }
        .job-card.read-only {
            border-left-color: #F3F6F8;
            background-color: #F3F6F8;
        }
        .job-card .badge-status {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 12px;
            margin-right: 8px;
        }
        .job-card .modify-job-btn,
        .job-card .add-day-btn,
        .job-card .delete-job-btn {
            min-width: 60px;
            padding: 3px 10px;
            font-size: 0.9rem;
        }
        .job-card h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .job-card p, .job-card .employee-item {
            font-size: 0.93rem;
        }
        .job-card .employee-item {
            margin-right: 6px;
            margin-bottom: 2px;
        }
        .job-card .badge-primary {
            font-size: 0.8rem;
            padding: 3px 7px;
            border-radius: 3px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 24px;
        }
        .pagination .page-link {
            color: #1F3BB3;
            border: 1px solid #e0e0e0;
            margin: 0 2px;
            padding: 4px 10px;
            font-size: 0.95rem;
        }
        .pagination .active .page-link {
            background: #1F3BB3;
            color: #fff;
        }
        .clarification-item {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .clarification-item:last-child {
            margin-bottom: 0;
        }
        .job-card .vessel-icon {
            color: #1F3BB3;
            margin-right: 5px;
        }
        .job-card .badge-editable {
            background-color: #E1F0FF;
            color: #1F3BB3;
        }
        .job-card .badge-approved {
            background-color: #E4F7F0;
            color: #1DD75B;
        }
        .job-card .employee-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 5px;
        }
        .job-card .employee-item {
            display: inline-flex;
            align-items: center;
            margin-right: 10px;
            margin-bottom: 5px;
        }
        .job-card .vessel-icon {
            color: #1F3BB3;
            margin-right: 5px;
        }
        .job-card .modify-job-btn,
        .job-card .add-day-btn,
        .job-card .delete-job-btn {
            min-width: 80px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        .empty-state-icon {
            font-size: 60px;
            color: #D1D5DB;
            margin-bottom: 20px;
        }
        .modal-lg-custom {
            max-width: 900px;
        }
        .select2-container {
            width: 100% !important;
        }
        .badge-primary {
            background-color: #1F3BB3;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        .special-projects-wrapper {
            margin-top: 15px;
        }

        .special-project-card {
            border-left: 3px solid #1F3BB3;
            border-radius: 5px;
        }

        .special-project-card .card-body {
            padding: 15px;
        }

        .special-project-card h6 {
            color: #1F3BB3;
            font-weight: 600;
        }

        .special-project-card .remove-project {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .special-project-card .form-group {
            margin-bottom: 1rem;
        }

        .special-project-card .form-control {
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        .special-project-card .input-group-append .btn {
            border-radius: 0 4px 4px 0;
        }

        .trip-assignment-card {
            border-left: 3px solid #17a2b8;
            border-radius: 5px;
        }

        .trip-assignment-card .card-body {
            padding: 15px;
        }

        .trip-assignment-card h6 {
            color: #17a2b8;
            font-weight: 600;
        }

        .trip-assignment-card .selectgroup {
            margin-bottom: 10px;
        }

        .trip-assignment-card .selectgroup-item {
            margin-bottom: 5px;
        }

        .trip-assignment-card .selectgroup-input:checked + .selectgroup-button {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }

        .trip-assignment-card .badge-info {
            background-color: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-panel">
          <div class="main-header">
          <div class="main-header-logo">
              <!-- Logo Header -->
              <div class="logo-header" data-background-color="dark">
                <a href="../index.html" class="logo">
                  <img
                    src="../assets/img/app-logo1.png"
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="fw-bold mb-1">Job Management</h1>
                        <p class="text-muted mb-0">Manage and edit your assigned jobs</p>
                    </div>
                </div>

                <!-- Unified Jobs Filter -->
                <form method="GET" class="form-inline mb-3">
                    <div class="input-group" style="gap: 8px;">
                        <input type="text" class="form-control monthpicker" name="filter_month" 
                            value="<?= isset($_GET['filter_month']) ? htmlspecialchars($_GET['filter_month']) : '' ?>" 
                            placeholder="Select Month" autocomplete="off" style="width: 150px;">
                        <select class="form-control" name="filter_jobtype" style="width: 180px;">
                            <option value="">All Job Types</option>
                            <?php foreach ($dropdownOptions['job_types'] as $type): ?>
                                <option value="<?= $type['jobtypeID'] ?>" <?= (isset($_GET['filter_jobtype']) && $_GET['filter_jobtype'] == $type['jobtypeID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-control" name="filter_status" style="width: 180px;">
                            <option value="">All Statuses</option>
                            <option value="Editable" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Editable') ? 'selected' : '' ?>>Editable</option>
                            <option value="Approved" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Approved') ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                            <option value="Clarification Needed" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Clarification Needed') ? 'selected' : '' ?>>Clarification Needed</option>
                            <option value="Pending Approval" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Pending Approval') ? 'selected' : '' ?>>Pending Approval</option>
                        </select>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                        <?php if ((isset($_GET['filter_month']) && $_GET['filter_month']) || (isset($_GET['filter_jobtype']) && $_GET['filter_jobtype']) || (isset($_GET['filter_status']) && $_GET['filter_status'])): ?>
                            <div class="input-group-append">
                                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="btn btn-link">Clear</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success']; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error']; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Unified Job List -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-round">
                            <div class="card-header bg-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">All Jobs</h4>
                                    <span class="badge badge-primary"><?= $totalJobs ?> Jobs</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($paginatedJobs)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-clipboard-list"></i>
                                        </div>
                                        <h3>No Jobs Found</h3>
                                        <p class="text-muted">No jobs to display for the selected filters or page.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($paginatedJobs as $item): ?>
                                            <?php
                                            $status = $item['status'];
                                            $badgeClass = 'badge-secondary';
                                            if ($status === 'Editable') $badgeClass = 'badge-editable';
                                            elseif ($status === 'Approved') $badgeClass = 'badge-approved';
                                            elseif ($status === 'Rejected') $badgeClass = 'badge-danger';
                                            elseif ($status === 'Clarification Needed') $badgeClass = 'badge-warning';
                                            elseif ($status === 'Pending Approval') $badgeClass = 'badge-info';
                                            $readOnly = ($status !== 'Editable');
                                            ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="job-card card h-100 <?= $readOnly ? 'read-only' : '' ?> <?= $status === 'Rejected' ? 'border-danger' : '' ?> <?= $status === 'Pending Approval' ? 'border-info' : '' ?> <?= $status === 'Clarification Needed' ? 'border-warning' : '' ?>" style="<?= $status === 'Rejected' ? 'border-left: 3px solid #dc3545; background: #fff6f6;' : '' ?><?= $status === 'Pending Approval' ? 'border-left: 3px solid #17a2b8; background: #f8fafd;' : '' ?><?= $status === 'Clarification Needed' ? 'border-left: 3px solid #ffc107; background: #fffbe6;' : '' ?>">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <span class="badge badge-status <?= $badgeClass ?>"><?= $status ?></span>
                                                                <span class="text-muted">Job #<?= htmlspecialchars($item['job']['jobID']) ?></span>
                                                            </div>
                                                        </div>
                                                        <h4 class="mb-2">
                                                            <i class="fas fa-ship vessel-icon"></i>
                                                            <?= $item['vessel'] ? htmlspecialchars($item['vessel']['vessel_name']) : 'No Vessel Assigned' ?>
                                                        </h4>
                                                        <div class="mb-2">
                                                            <p class="mb-1"><strong>Job Type:</strong> <?= $item['job_type'] ? htmlspecialchars($item['job_type']['type_name']) : '-' ?></p>
                                                            <p class="mb-1"><strong>Dates:</strong> <?= htmlspecialchars($item['job']['start_date']) ?> to <?= htmlspecialchars($item['job']['end_date']) ?></p>
                                                            <p class="mb-1"><strong>Port:</strong> <?= ($item['port'] && isset($item['port']['portname'])) ? htmlspecialchars($item['port']['portname']) : '-' ?></p>
                                                            <p class="mb-1"><strong>Boat:</strong> <?= $item['boat'] ? htmlspecialchars($item['boat']['boat_name']) : '-' ?></p>
                                                        </div>
                                                        <?php
                                                        // Trip-wise employees display for this job
                                                        $trips = [];
                                                        $tripsRes = $conn->query("SELECT tripID, trip_date FROM trips WHERE jobID = " . intval($item['job']['jobID']) . " ORDER BY trip_date");
                                                        while ($trip = $tripsRes->fetch_assoc()) {
                                                            $tripID = $trip['tripID'];
                                                            $empRes = $conn->query("SELECT e.empID, u.fname, u.lname
                                                                FROM jobassignments ja
                                                                JOIN employees e ON ja.empID = e.empID
                                                                JOIN users u ON e.userID = u.userID
                                                                WHERE ja.tripID = $tripID");
                                                            $employees = [];
                                                            while ($emp = $empRes->fetch_assoc()) {
                                                                $employees[] = $emp;
                                                            }
                                                            $trips[] = [
                                                                'tripID' => $tripID,
                                                                'trip_date' => $trip['trip_date'],
                                                                'employees' => $employees
                                                            ];
                                                        }
                                                        ?>
                                                        <div class="mb-2">
                                                            <p class="mb-1"><strong>Assigned Employees (by Day):</strong></p>
                                                            <?php if (empty($trips)): ?>
                                                                <div class="text-muted">No days/trips added yet.</div>
                                                            <?php else: ?>
                                                                <?php foreach ($trips as $trip): ?>
                                                                    <div class="mb-1">
                                                                        <strong>Day: <?= htmlspecialchars($trip['trip_date']) ?></strong>
                                                                        <div>
                                                                            <?php if (empty($trip['employees'])): ?>
                                                                                <span class="text-muted">No employees assigned</span>
                                                                            <?php else: ?>
                                                                                <?php foreach ($trip['employees'] as $emp): ?>
                                                                                    <span class="employee-item">
                                                                                        <?= htmlspecialchars($emp['fname'] . ' ' . $emp['lname']) ?>
                                                                                    </span>
                                                                                <?php endforeach; ?>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($item['special_projects'])): ?>
                                                            <div class="mb-2">
                                                                <p class="mb-1"><strong>Special Projects:</strong></p>
                                                                <div>
                                                                    <?php foreach ($item['special_projects'] as $sp): ?>
                                                                        <span class="badge badge-primary"><?= htmlspecialchars($sp['name']) ?></span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="mb-2">
                                                                <p class="mb-1"><strong>Special Projects:</strong> No special projects</p>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['job']['comment'])): ?>
                                                            <div class="mb-2">
                                                                <p class="mb-1"><strong>Comments:</strong></p>
                                                                <p class="text-muted"><?= htmlspecialchars($item['job']['comment']) ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($status === 'Editable'): ?>
                                                        <button class="btn btn-primary modify-job-btn mt-1" data-toggle="modal" data-target="#editJobModal" 
                                                            data-jobid="<?= $item['job']['jobID'] ?>"
                                                            data-jobtype="<?= $item['job_type'] ? $item['job_type']['jobtypeID'] : '' ?>"
                                                            data-startdate="<?= htmlspecialchars($item['job']['start_date']) ?>"
                                                            data-enddate="<?= htmlspecialchars($item['job']['end_date']) ?>"
                                                            data-vessel="<?= $item['vessel'] ? $item['vessel']['vesselID'] : '' ?>"
                                                            data-boat="<?= $item['boat'] ? $item['boat']['boatID'] : '' ?>"
                                                            data-port="<?= $item['port'] ? $item['port']['portID'] : '' ?>"
                                                            data-comment="<?= htmlspecialchars($item['job']['comment']) ?>"
                                                            data-employees="<?= htmlspecialchars(implode(',', array_column($item['employees'], 'empID'))) ?>"
                                                            data-specialprojects="<?= htmlspecialchars(json_encode($item['special_projects'])) ?>">
                                                            <i class="fas fa-edit mr-1"></i> Modify
                                                        </button>
                                                        <a href="managejobdays.php?jobID=<?= $item['job']['jobID'] ?>" class="btn btn-success add-day-btn mt-1 ml-2">
                                                            <i class="fas fa-calendar-plus mr-1"></i> Add Day
                                                        </a>
                                                        <button class="btn btn-danger delete-job-btn mt-1 ml-2" data-toggle="modal" data-target="#deleteJobModal" 
                                                            data-jobid="<?= $item['job']['jobID'] ?>"
                                                            data-jobname="<?= $item['vessel'] ? htmlspecialchars($item['vessel']['vessel_name']) : 'Job' ?>">
                                                            <i class="fas fa-trash mr-1"></i> Delete Job
                                                        </button>
                                                        <?php elseif ($status === 'Clarification Needed'): ?>
                                                        <button class="btn btn-warning resolve-clar-btn mt-1" 
                                                            data-jobid="<?= $item['job']['jobID'] ?>"
                                                            data-approvalid="<?= $item['approvalID'] ?? '' ?>"
                                                            data-toggle="modal" data-target="#resolveClarificationModal">
                                                            <i class="fas fa-check-circle mr-1"></i> Resolve Clarification
                                                        </button>
                                                        <?php elseif ($status === 'Rejected'): ?>
                                                        <div class="alert alert-danger mt-2 mb-0 p-1" role="alert" style="font-size:0.92rem;">
                                                            <strong>Rejected by Approver:</strong> Please review the job details and contact the approver for more information if needed.
                                                        </div>
                                                        <?php elseif ($status === 'Pending Approval'): ?>
                                                        <div class="alert alert-info mt-2 mb-0 p-1" role="alert" style="font-size:0.92rem;">
                                                            Clarification submitted. Pending approval from the supervisor-in-charge.
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- See More Details Button -->
                                                        <a href="jobdetails.php?jobID=<?= $item['job']['jobID'] ?>" class="btn btn-info mt-2" style="min-width: 80px; padding: 3px 10px; font-size: 0.9rem;">
                                                            <i class="fas fa-eye mr-1"></i> See More Details
                                                        </a>
                                                        <?php if ($status === 'Clarification Needed' || $status === 'Pending Approval'): ?>
                                                            <?php 
                                                            // Get all clarifications for this job
                                                            $clars = $conn->query("SELECT c.* FROM clarifications c 
                                                                                 JOIN users u ON c.clarification_requesterID = u.userID 
                                                                                 WHERE c.jobID = ".$item['job']['jobID']." 
                                                                                 AND u.roleID = 13 
                                                                                 ORDER BY c.clarification_id DESC");
                                                            if ($clars && $clars->num_rows > 0): ?>
                                                                <?php while ($clar = $clars->fetch_assoc()): ?>
                                                                    <div class="clarification-item mt-2">
                                                                        <p class="mb-1"><strong>Clarification Request from Supervisor-in-Charge:</strong></p>
                                                                        <p class="text-danger font-weight-bold"><?= htmlspecialchars($clar['clarification_request_comment']) ?></p>
                                                                        
                                                                        <?php if ($clar['clarification_status'] == 0): ?>
                                                                            <button class="btn btn-warning resolve-clar-btn mt-2" 
                                                                                data-jobid="<?= $item['job']['jobID'] ?>"
                                                                                data-clarificationid="<?= $clar['clarification_id'] ?>"
                                                                                data-toggle="modal" data-target="#resolveClarificationModal">
                                                                                <i class="fas fa-check-circle mr-1"></i> Resolve Clarification
                                                                            </button>
                                                                        <?php elseif ($clar['clarification_status'] == 1): ?>
                                                                            <div class="mt-2">
                                                                                <p class="mb-1"><strong>Your Resolution:</strong></p>
                                                                                <p class="text-success font-weight-bold"><?= htmlspecialchars($clar['clarification_resolved_comment']) ?></p>
                                                                                <span class="badge badge-info">Waiting for Supervisor-in-Charge Approval</span>
                                                                            </div>
                                                                        <?php elseif ($clar['clarification_status'] == 2): ?>
                                                                            <div class="mt-2">
                                                                                <p class="mb-1"><strong>Your Resolution:</strong></p>
                                                                                <p class="text-success font-weight-bold"><?= htmlspecialchars($clar['clarification_resolved_comment']) ?></p>
                                                                                <span class="badge badge-success">Resolution Approved</span>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endwhile; ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <!-- Pagination Controls -->
                                    <nav aria-label="Job pagination">
                                        <ul class="pagination">
                                            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page-1 ?>" tabindex="-1">Previous</a>
                                            </li>
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item<?= $i == $page ? ' active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page+1 ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Unified Job List -->
            </div>
        </div>
        <?php include 'components/footer.php'; ?>
    </div>
</div>

<!-- Edit Job Modal -->
<div class="modal fade" id="editJobModal" tabindex="-1" role="dialog" aria-labelledby="editJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg-custom" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editJobModalLabel">Edit Job Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editJobForm" method="POST" action="">
                <input type="hidden" name="update_job" value="1">
                <input type="hidden" id="jobID" name="jobID" value="">
                <input type="hidden" name="trip_assignments" id="tripAssignmentsData" value="">
                <input type="hidden" name="standby_attendanceID" id="standbyAttendanceID" value="">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jobType">Job Type</label>
                                <select class="form-control select2" id="jobType" name="jobtypeID">
                                    <option value="">Select Job Type</option>
                                    <?php foreach ($dropdownOptions['job_types'] as $type): ?>
                                        <option value="<?= $type['jobtypeID'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="vessel">Vessel</label>
                                <select class="form-control select2" id="vessel" name="vesselID">
                                    <option value="">Select Vessel</option>
                                    <?php foreach ($dropdownOptions['vessels'] as $vessel): ?>
                                        <option value="<?= $vessel['vesselID'] ?>"><?= htmlspecialchars($vessel['vessel_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="startDate">Start Date</label>
                                <input type="text" class="form-control datepicker" id="startDate" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="endDate">End Date (Optional)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="endDate" name="end_date" placeholder="Leave empty if not completed">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="clearEndDate">
                                            <i class="fas fa-times"></i> Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="port">Port</label>
                                <select class="form-control select2" id="port" name="portID">
                                    <option value="">Select Port</option>
                                    <?php foreach ($dropdownOptions['ports'] as $port): ?>
                                        <option value="<?= $port['portID'] ?>"><?= htmlspecialchars($port['portname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="boat">Boat</label>
                                <select class="form-control select2" id="boat" name="boatID">
                                    <option value="">Select Boat</option>
                                    <?php foreach ($dropdownOptions['boats'] as $boat): ?>
                                        <option value="<?= $boat['boatID'] ?>"><?= htmlspecialchars($boat['boat_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- <div class="form-group">
                        <label for="employees">Assigned Employees</label>
                        <select class="form-control select2" id="employees" multiple="multiple" disabled>
                            <?php foreach ($dropdownOptions['employees'] as $emp): ?>
                                <option value="<?= $emp['empID'] ?>"><?= htmlspecialchars($emp['fname'] . ' ' . $emp['lname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div> -->
                    
                    <!-- Trip-wise Employee Assignment Section -->
                    <div class="form-group">
                        <label>Trip-wise Employee Assignments</label>
                        <div id="tripAssignmentsContainer">
                            <!-- Trip assignments will be loaded here dynamically -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Special Projects</label>
                        <div id="specialProjectsReadonly" class="p-2 bg-light rounded">
                            <!-- Special projects will be displayed here -->
                        </div>
                    </div>

                    <div id="specialProjectsContainer" class="mt-3">
                        <!-- Special projects will be loaded here dynamically -->
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Comments</label>
                        <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Job Modal -->
<div class="modal fade" id="deleteJobModal" tabindex="-1" role="dialog" aria-labelledby="deleteJobModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteJobModalLabel">Delete Job</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="delete_job" value="1">
          <input type="hidden" id="delete_jobID" name="jobID" value="">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Warning:</strong> This action cannot be undone. Deleting this job will also delete:
            <ul class="mt-2 mb-0">
              <li>All trips associated with this job</li>
              <li>All employee assignments for those trips</li>
              <li>All attendance records for those trips</li>
              <li>All special project assignments</li>
              <li>All port and boat assignments</li>
            </ul>
          </div>
          <p>Are you sure you want to delete the job "<span id="delete_job_name"></span>"?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Job</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add this modal before the closing </body> tag -->
<div class="modal fade" id="resolveClarificationModal" tabindex="-1" role="dialog" aria-labelledby="resolveClarificationModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header bg-warning text-white">
          <h5 class="modal-title" id="resolveClarificationModalLabel">Resolve Clarification</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="resolve_clarification" value="1">
          <input type="hidden" id="clar_jobID" name="jobID" value="">
          <input type="hidden" id="clar_clarificationID" name="clarification_id" value="">
          <div class="form-group">
            <label for="clarification_resolved_comment">Resolution Comment</label>
            <textarea class="form-control" id="clarification_resolved_comment" name="clarification_resolved_comment" rows="4" required placeholder="Provide your clarification response..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Submit Resolution</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/core/jquery-3.7.1.min.js"></script>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
<script src="../assets/js/plugin/chart-circle/circles.min.js"></script>
<script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>
<script src="../assets/js/plugin/datatables/datatables.min.js"></script>
<script src="../assets/js/kaiadmin.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">

<script>
$(document).ready(function() {
    // Initialize date picker
    $('.datepicker').flatpickr({
        dateFormat: "Y-m-d",
        allowInput: true
    });
    
    // Handle clear end date button
    $(document).on('click', '#clearEndDate', function() {
        $('#endDate').val('').trigger('change');
    });

    // Initialize select2
    $(".select2").select2({
      dropdownParent: $("#editJobModal"),
      width: "resolve",
      placeholder: "Select options",
      allowClear: true,
    });

    // Function to load trip assignments
    function loadTripAssignments(jobID, modal) {
        $.ajax({
            url: '../controllers/getTripAssignmentsController.php',
            type: 'GET',
            data: { jobID: jobID },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var container = modal.find('#tripAssignmentsContainer');
                    var html = '';
                    
                    if (response.trips && response.trips.length > 0) {
                        response.trips.forEach(function(trip) {
                            html += `
                            <div class="trip-assignment-card card mb-3" data-trip-id="${trip.tripID}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Day - ${trip.trip_date}</h6>
                                        <span class="badge badge-info">${trip.assigned_employees.length} employees</span>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Standby Divers</label>
                                                <div class="selectgroup selectgroup-pills">
                                                    ${response.standby_divers.map(function(diver) {
                                                        var isAssigned = trip.assigned_employees.some(function(emp) {
                                                            return emp.userID == diver.userID;
                                                        });
                                                        return `
                                                        <label class="selectgroup-item">
                                                            <input type="checkbox" name="trip_assignments[${trip.tripID}][divers][]" 
                                                                   value="${diver.userID}" class="selectgroup-input" 
                                                                   ${isAssigned ? 'checked' : ''}>
                                                            <span class="selectgroup-button">${diver.fname} ${diver.lname}</span>
                                                        </label>
                                                        `;
                                                    }).join('')}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Other Divers</label>
                                                <div class="selectgroup selectgroup-pills">
                                                    ${response.other_divers.map(function(diver) {
                                                        var isAssigned = trip.assigned_employees.some(function(emp) {
                                                            return emp.userID == diver.userID;
                                                        });
                                                        return `
                                                        <label class="selectgroup-item">
                                                            <input type="checkbox" name="trip_assignments[${trip.tripID}][otherDivers][]" 
                                                                   value="${diver.userID}" class="selectgroup-input" 
                                                                   ${isAssigned ? 'checked' : ''}>
                                                            <span class="selectgroup-button">${diver.fname} ${diver.lname}</span>
                                                        </label>
                                                        `;
                                                    }).join('')}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">Currently assigned: ${trip.assigned_employees.map(function(emp) {
                                            return emp.fname + ' ' + emp.lname;
                                        }).join(', ')}</small>
                                    </div>
                                </div>
                            </div>
                            `;
                        });
                    } else {
                        html = '<div class="alert alert-info">No trips found for this job.</div>';
                    }
                    
                    container.html(html);
                } else {
                    console.error('Error loading trip assignments:', response.message);
                    modal.find('#tripAssignmentsContainer').html('<div class="alert alert-danger">Error loading trip assignments.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                modal.find('#tripAssignmentsContainer').html('<div class="alert alert-danger">Error loading trip assignments.</div>');
            }
        });
    }

    // Explicitly bind click event to modify buttons
    $(document).on("click", ".modify-job-btn", function () {
        var button = $(this);
        var modal = $('#editJobModal');
        var jobID = button.data('jobid');
        
        // Populate form fields
        modal.find('#jobID').val(jobID);
        modal.find('#jobType').val(button.data('jobtype')).trigger('change');
        modal.find('#vessel').val(button.data('vessel')).trigger('change');
        modal.find('#startDate').val(button.data('startdate'));
        modal.find('#endDate').val(button.data('enddate'));
        modal.find('#port').val(button.data('port')).trigger('change');
        modal.find('#boat').val(button.data('boat')).trigger('change');
        modal.find('#comment').val(button.data('comment'));
        
        // Handle multi-select fields
        if (button.data('employees')) {
            var employees = button.data('employees').split(',');
            modal.find('#employees').val(employees).trigger('change');
        }
        
        // Load trip assignments
        loadTripAssignments(jobID, modal);
        
        // Get and set standby_attendanceID
        $.ajax({
            url: '../controllers/getStandbyAttendanceIDController.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    modal.find('#standbyAttendanceID').val(response.standby_attendanceID);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching standby attendance ID:', error);
            }
        });
        
        // Handle special projects display (read-only)
        var specialProjectsReadonly = modal.find('#specialProjectsReadonly');
        specialProjectsReadonly.empty();

        try {
            var specialProjects = JSON.parse(button.attr('data-specialprojects'));
            if (specialProjects && specialProjects.length > 0) {
                var html = '<div class="d-flex flex-wrap">';
                specialProjects.forEach(function(project) {
                    html += `<span class="badge badge-primary mr-2 mb-2">${project.name || 'Special Project'}</span>`;
                });
                html += '</div>';
                specialProjectsReadonly.html(html);
            } else {
                specialProjectsReadonly.html('<span class="text-muted">No special projects assigned</span>');
            }
        } catch (e) {
            console.error("Error parsing special projects:", e);
            specialProjectsReadonly.html('<span class="text-danger">Error loading special projects</span>');
        }
        
        // Set selected special projects in the dropdown
        if (specialProjects && specialProjects.length > 0) {
            var projectIds = specialProjects.map(p => p.spProjectID);
            modal.find('#specialProjects').val(projectIds).trigger('change');
        }
        
        // Handle project removal
        container.on('click', '.remove-project', function() {
            $(this).closest('.special-project-card').remove();
        });

        // Handle new project selection
        modal.find('#specialProjects').on('change', function() {
            var selected = $(this).val();
            var existing = container.find('.special-project-card').map(function() {
                return $(this).data('project-id');
            }).get();
            
            // Find newly added projects
            var added = selected.filter(id => !existing.includes(id.toString()));
            
            if (added.length > 0) {
                var html = '';
                added.forEach(function(id) {
                    var project = <?= json_encode($dropdownOptions['special_projects']) ?>.find(p => p.spProjectID == id);
                    if (project) {
                        html += `
                        <div class="special-project-card card mb-3" data-project-id="${project.spProjectID}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">${project.name}</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-project">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Vessel</label>
                                            <input type="text" class="form-control" name="special_projects[new_${id}][vessel]" placeholder="Enter vessel">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date</label>
                                            <input type="text" class="form-control datepicker" name="special_projects[new_${id}][date]" placeholder="Select date">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Evidence</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="special_projects[new_${id}][evidence]" placeholder="Evidence file path">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary upload-evidence" type="button">
                                                <i class="fas fa-upload"></i> Upload
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="special_projects[new_${id}][spProjectID]" value="${project.spProjectID}">
                                </div>
                            </div>
                        </div>
                        `;
                    }
                });
                
                container.append(html);
                container.find('.datepicker').flatpickr({
                    dateFormat: "Y-m-d",
                    allowInput: true
                });
            }
        });
        
        modal.modal('show');
    });

    // Form submission handler
    $('#editJobForm').on('submit', function(e) {
        // Validate end date is after start date (only if end date is provided)
        var startDate = new Date($('#startDate').val());
        var endDateVal = $('#endDate').val();
        
        if (endDateVal && endDateVal.trim() !== '') {
            var endDate = new Date(endDateVal);
            if (endDate < startDate) {
                alert('End date must be after start date');
                e.preventDefault();
                return false;
            }
        }
        
        // Collect trip assignment data before submitting
        var tripAssignments = {};
        
        // Iterate through each trip assignment card
        $('#tripAssignmentsContainer .trip-assignment-card').each(function() {
            var tripID = $(this).data('trip-id');
            var divers = [];
            var otherDivers = [];
            
            // Collect selected standby divers
            $(this).find('.standby-divers .selectgroup-input:checked').each(function() {
                divers.push($(this).val());
            });
            
            // Collect selected other divers
            $(this).find('.other-divers .selectgroup-input:checked').each(function() {
                otherDivers.push($(this).val());
            });
            
            // Only add to tripAssignments if there are selections
            if (divers.length > 0 || otherDivers.length > 0) {
                tripAssignments[tripID] = {
                    divers: divers,
                    otherDivers: otherDivers
                };
            }
        });
        
        // Set the trip assignments data in the hidden input
        $('#tripAssignmentsData').val(JSON.stringify(tripAssignments));
        
        return true;
    });

    $(document).on('click', '.resolve-clar-btn', function() {
        var jobID = $(this).data('jobid');
        var clarificationID = $(this).data('clarificationid');
        $('#clar_jobID').val(jobID);
        $('#clar_clarificationID').val(clarificationID);
        $('#clarification_resolved_comment').val('');
        $('#resolveClarificationModal').modal('show');
    });

    // Handle delete job modal
    $(document).on('click', '[data-target="#deleteJobModal"]', function() {
        var jobID = $(this).data('jobid');
        var jobName = $(this).data('jobname');
        $('#delete_jobID').val(jobID);
        $('#delete_job_name').text(jobName);
        $('#deleteJobModal').modal('show');
    });

    // Month picker for filters
    $('.monthpicker').flatpickr({
        plugins: [
            new monthSelectPlugin({
                shorthand: true,
                dateFormat: "Y-m",
                altFormat: "F Y"
            })
        ],
        altInput: true,
        allowInput: true
    });
});
</script>
</body>
</html>