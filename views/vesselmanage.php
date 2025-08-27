<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
// DB connection for fetching vessels
$host = "localhost";
$user = "tripwosscp_usr";
$password = "aQDOUs-e#3He(xtz";
$database = "tripwosscp_dbs";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in and has role_id = 1
// if (!isset($_SESSION['userID']) || 
//     !isset($_SESSION['roleID']) || 
//     $_SESSION['roleID'] != 4) {
//     header("Location: ../index.php?error=access_denied");
//     exit();
// }


$sql = "SELECT * FROM vessels";
$result = $conn->query($sql);
$vessels = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vessels[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Vessel Management - SubseaOps</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="../assets/img/app-logo1.png"
      type="image/x-icon"
    />

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

    <!-- CSS Just for demo purpose, don't include it in your project -->
    <link rel="stylesheet" href="../assets/css/demo.css" />
  </head>
  <body>
<div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>
    <div class="main-panel">
          <div class="main-header">
          <div class="main-header-logo">
              <!-- Logo Header -->
              <div class="logo-header" data-background-color="dark">
                <a href="admindashboard.php" class="logo">
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
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Vessel Management</h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <h4 class="card-title">Add Vessel</h4>
                                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#addVesselModal">
                                        <i class="fa fa-plus"></i> Add Vessel
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Add Vessel Modal -->
                                <div class="modal fade" id="addVesselModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">New Vessel</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="addVesselForm">
                                                    <div class="form-group">
                                                        <label for="addVesselName">Vessel Name</label>
                                                        <input type="text" class="form-control" id="addVesselName" name="vessel_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" id="addVesselButton" class="btn btn-primary">Add</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Vessel Table -->
                                <div class="table-responsive">
                                    <table id="vessel-table" class="display table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Vessel ID</th>
                                                <th>Vessel Name</th>
                                                <th>Created Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vessels as $vessel): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($vessel['vesselID']) ?></td>
                                                    <td><?= htmlspecialchars($vessel['vessel_name']) ?></td>
                                                    <td><?= htmlspecialchars($vessel['create_date']) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning btn-edit" data-vesselid="<?= htmlspecialchars($vessel['vesselID']) ?>"><i class="fa fa-edit"></i> Modify</button>
                                                        <!-- <button class="btn btn-sm btn-info btn-copy" data-vesselid="<?= htmlspecialchars($vessel['vesselID']) ?>"><i class="fa fa-copy"></i> Copy</button> -->
                                                        <button class="btn btn-sm btn-danger btn-delete" data-vesselid="<?= htmlspecialchars($vessel['vesselID']) ?>"><i class="fa fa-trash"></i> Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Edit Vessel Modal -->
                                <div class="modal fade" id="editVesselModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">Edit Vessel</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="editVesselForm">
                                                    <input type="hidden" id="editVesselID" name="vesselID">
                                                    <div class="form-group">
                                                        <label for="editVesselName">Vessel Name</label>
                                                        <input type="text" class="form-control" id="editVesselName" name="vessel_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-primary" id="saveEditVessel">Save Changes</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
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
<script src="../assets/js/plugin/datatables/datatables.min.js"></script>
<script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>
<script src="../assets/js/kaiadmin.min.js"></script>
<script src="../assets/js/setting-demo2.js"></script>
<script>
$(document).ready(function () {
    $('#vessel-table').DataTable({ pageLength: 10 });
    
    // Edit button click
    $(document).on('click', '.btn-edit', function () {
        var row = $(this).closest('tr');
        var vesselID = $(this).data('vesselid');
        var vessel_name = row.find('td:eq(1)').text();
        $('#editVesselID').val(vesselID);
        $('#editVesselName').val(vessel_name);
        $('#editVesselModal').modal('show');
    });
    
    // Copy button click
    $(document).on('click', '.btn-copy', function () {
        var row = $(this).closest('tr');
        var vessel_name = row.find('td:eq(1)').text();
        $('#addVesselName').val(vessel_name + ' (Copy)');
        $('#addVesselModal').modal('show');
    });
    
    // Save Edit Vessel
    $('#saveEditVessel').on('click', function (e) {
        e.preventDefault();
        var formData = $('#editVesselForm').serialize() + '&action=edit';
        var vesselID = $('#editVesselID').val();
        var row = $("button[data-vesselid='" + vesselID + "']").closest('tr');
        $.post('../controllers/vesselManageController.php', formData, function (response) {
            if (response.success) {
                row.find('td:eq(1)').text($('#editVesselName').val());
                $('#editVesselModal').modal('hide');
                swal({
                    title: "Success!",
                    text: "Vessel updated successfully!",
                    icon: "success",
                    button: "OK"
                });
            } else {
                swal({
                    title: "Error!",
                    text: response.message,
                    icon: "error",
                    button: "OK"
                });
            }
        }, 'json');
    });
    
    // Delete button click
    $(document).on('click', '.btn-delete', function () {
        var vesselID = $(this).data('vesselid');
        var row = $(this).closest('tr');
        
        swal({
            title: "Are you sure?",
            text: "Once deleted, you will not be able to recover this vessel!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
        .then((willDelete) => {
            if (willDelete) {
                $.post('../controllers/vesselManageController.php', { vesselID: vesselID, action: 'delete' }, function (response) {
                    if (response.success) {
                        row.remove();
                        swal({
                            title: "Deleted!",
                            text: "Vessel has been deleted successfully!",
                            icon: "success",
                            button: "OK"
                        });
                    } else {
                        swal({
                            title: "Error!",
                            text: response.message,
                            icon: "error",
                            button: "OK"
                        });
                    }
                }, 'json');
            }
        });
    });
    
    // Add Vessel button click
    $('#addVesselButton').on('click', function (e) {
        e.preventDefault();
        var formData = $('#addVesselForm').serialize() + '&action=add';
        $.post('../controllers/vesselManageController.php', formData, function (response) {
            if (response.success) {
                var vessel = response.vessel;
                var newRow = '<tr>' +
                    '<td>' + vessel.vesselID + '</td>' +
                    '<td>' + vessel.vessel_name + '</td>' +
                    '<td>' + vessel.create_date + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-warning btn-edit" data-vesselid="' + vessel.vesselID + '"><i class="fa fa-edit"></i> Edit</button> ' +
                        '<button class="btn btn-sm btn-info btn-copy" data-vesselid="' + vessel.vesselID + '"><i class="fa fa-copy"></i> Copy</button> ' +
                        '<button class="btn btn-sm btn-danger btn-delete" data-vesselid="' + vessel.vesselID + '"><i class="fa fa-trash"></i> Delete</button>' +
                    '</td>' +
                '</tr>';
                $('#vessel-table').DataTable().row.add($(newRow)).draw();
                $('#addVesselModal').modal('hide');
                $('#addVesselForm')[0].reset();
                swal({
                    title: "Success!",
                    text: "Vessel added successfully!",
                    icon: "success",
                    button: "OK"
                });
            } else {
                swal({
                    title: "Error!",
                    text: response.message,
                    icon: "error",
                    button: "OK"
                });
            }
        }, 'json');
    });
});
</script>
</body>
</html>
