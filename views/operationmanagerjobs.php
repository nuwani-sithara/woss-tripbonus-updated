<?php
//require_once('../controllers/operationManagerJobsController.php');
require_once('../controllers/operationManagerJobsController.php');

if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}

if (!isset($search)) $search = '';
if (!isset($jobs)) $jobs = [];
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Manage Jobs - WOSS Trip Bonus</title>
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
        </div>
        <?php include 'components/navbar.php'; ?>
      </div>
      <div class="container">
        <div class="page-inner">
          <div class="page-header">
            <h3 class="fw-bold mb-3">Manage Jobs</h3>
          </div>
          <form method="get" class="mb-3">
            <div class="input-group">
              <input type="text" name="search" class="form-control" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>">
              <button type="submit" class="btn btn-primary">Search</button>
            </div>
          </form>
          <div class="row mt-4">
            <div class="col-12">
              <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom-0">
                  <div class="d-flex align-items-center">
                    <h4 class="card-title mb-0">Jobs List</h4>
                    <span class="badge bg-primary-soft text-primary ms-auto" id="jobCount"><?php echo count($jobs); ?> Jobs</span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table id="jobs-table" class="display table table-striped table-hover align-middle">
                      <thead>
                        <tr>
                          <th>Job ID</th>
                          <th>Type</th>
                          <th>Boat</th>
                          <th>Port</th>
                          <th>Vessel</th>
                          <th>Special Project</th>
                          <th>Creator</th>
                          <th>Employees</th>
                          <th>Trips</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($jobs as $job): ?>
                          <tr>
                            <td class="fw-semibold text-primary">#<?php echo $job['jobID']; ?></td>
                            <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                            <td>
                              <span class="badge bg-secondary"><i class="fas fa-ship me-1"></i><?php echo htmlspecialchars($job['boat_name'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                              <span class="badge bg-info text-dark"><i class="fas fa-anchor me-1"></i><?php echo htmlspecialchars($job['portname'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                              <span class="badge bg-light text-dark"><i class="fas fa-ship me-1"></i><?php echo htmlspecialchars($job['vessel_name'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                              <?php if (!empty($job['special_project'])): ?>
                                <a href="#" class="special-project-link text-decoration-underline text-info fw-bold"
                                   data-bs-toggle="tooltip" title="View Special Project Details"
                                   data-sp='<?php echo htmlspecialchars(json_encode($job['special_project']), ENT_QUOTES, 'UTF-8'); ?>'>
                                  <?php echo htmlspecialchars($job['special_project']['name']); ?>
                                </a>
                              <?php else: ?>
                                <span class="text-muted">None</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="fw-semibold"><?php echo htmlspecialchars($job['creator_fname'] . ' ' . $job['creator_lname']); ?></span>
                            </td>
                            <td>
                              <span class="text-dark"><?php echo htmlspecialchars(implode(', ', $job['employees'])); ?></span>
                            </td>
                            <td>
                              <?php if (!empty($job['trips'])): ?>
                                <?php foreach ($job['trips'] as $trip): ?>
                                  <a href="#" class="trip-link badge bg-success text-white mb-1"
                                     data-bs-toggle="tooltip" title="View Employees"
                                     data-trip='<?php echo htmlspecialchars(json_encode($trip), ENT_QUOTES, 'UTF-8'); ?>'>
                                    Trip #<?php echo $trip['trip_date']; ?>
                                  </a><br>
                                <?php endforeach; ?>
                              <?php else: ?>
                                <span class="text-muted">No Trips</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="badge bg-<?php echo $job['statusClass']; ?> text-uppercase">
                                <i class="<?php echo $job['statusIcon']; ?> me-1"></i>
                                <?php echo $job['status']; ?>
                              </span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        <?php if (empty($jobs)): ?>
                          <tr>
                            <td colspan="10" class="text-center py-5">
                              <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                              <h5 class="text-muted">No Jobs Found</h5>
                              <p class="text-muted">No jobs match your search criteria.</p>
                            </td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Special Project Modal -->
        <div class="modal fade" id="specialProjectModal" tabindex="-1" aria-labelledby="specialProjectModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="specialProjectModalLabel">Special Project Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <dl class="row">
                  <dt class="col-sm-4">Name</dt>
                  <dd class="col-sm-8" id="spName"></dd>
                  <dt class="col-sm-4">Date</dt>
                  <dd class="col-sm-8" id="spDate"></dd>
                  <dt class="col-sm-4">Vessel ID</dt>
                  <dd class="col-sm-8" id="spVesselID"></dd>
                  <dt class="col-sm-4">Allowance</dt>
                  <dd class="col-sm-8" id="spAllowance"></dd>
                  <dt class="col-sm-4">Evidence</dt>
                  <dd class="col-sm-8" id="spEvidence"></dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
        <!-- Trip Employees Modal -->
        <div class="modal fade" id="tripEmployeesModal" tabindex="-1" aria-labelledby="tripEmployeesModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="tripEmployeesModalLabel">Trip Employees</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <ul class="list-group" id="tripEmployeesList"></ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php include 'components/footer.php'; ?>

   <!-- Core JS Files -->
    <script src="../assets/js/core/jquery.3.2.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery UI -->
    <script src="../assets/js/plugin/jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>
    <script src="../assets/js/plugin/jquery-ui-touch-punch/jquery.ui.touch-punch.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Chart JS -->
    <script src="../assets/js/plugin/chart.js/chart.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

    <!-- Chart Circle -->
    <script src="../assets/js/plugin/chart-circle/circles.min.js"></script>

    <!-- Datatables -->
    <script src="../assets/js/plugin/datatables/datatables.min.js"></script>

    <!-- Bootstrap Notify -->
    <script src="../assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

    <!-- jQuery Vector Maps -->
    <script src="../assets/js/plugin/jqvmap/jquery.vmap.min.js"></script>
    <script src="../assets/js/plugin/jqvmap/maps/jquery.vmap.world.js"></script>

    <!-- Sweet Alert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Atlantis JS -->
    <script src="../assets/js/atlantis.min.js"></script>
  <script>
  $(document).ready(function () {
    $('#jobs-table').DataTable({ pageLength: 10 });

    // Special Project Modal
    $(document).on('click', '.special-project-link', function (e) {
      e.preventDefault();
      var sp = $(this).data('sp');
      $('#spName').text(sp.name || 'N/A');
      $('#spDate').text(sp.date || 'N/A');
      $('#spVesselID').text(sp.vesselID || 'N/A');
      $('#spAllowance').text(sp.allowance || 'N/A');
      $('#spEvidence').text(sp.evidence || 'N/A');
      $('#specialProjectModal').modal('show');
    });

    // Trip Employees Modal
    $(document).on('click', '.trip-link', function (e) {
      e.preventDefault();
      var trip = $(this).data('trip');
      var $list = $('#tripEmployeesList');
      $list.empty();
      if (trip.employees && trip.employees.length > 0) {
        trip.employees.forEach(function(emp) {
          $list.append('<li class="list-group-item">' + emp + '</li>');
        });
      } else {
        $list.append('<li class="list-group-item text-muted">No employees assigned</li>');
      }
      $('#tripEmployeesModal').modal('show');
    });
  });
  </script>
  </body>
</html>
