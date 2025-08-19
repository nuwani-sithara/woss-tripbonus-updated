<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
// DB connection for fetching boats
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


$sql = "SELECT * FROM boats";
$result = $conn->query($sql);
$boats = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $boats[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Boat Management - WOSS Trip Bonus System</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="../assets/img/logo_white.png"
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
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Boat Management</h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <h4 class="card-title">Add Boat</h4>
                                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#addBoatModal">
                                        <i class="fa fa-plus"></i> Add Boat
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Add Boat Modal -->
                                <div class="modal fade" id="addBoatModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">New Boat</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="addBoatForm">
                                                    <div class="form-group">
                                                        <label for="addBoatName">Boat Name</label>
                                                        <input type="text" class="form-control" id="addBoatName" name="boat_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" id="addBoatButton" class="btn btn-primary">Add</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Boat Table -->
                                <div class="table-responsive">
                                    <table id="boat-table" class="display table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Boat ID</th>
                                                <th>Boat Name</th>
                                                <th>Created Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($boats as $boat): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($boat['boatID']) ?></td>
                                                    <td><?= htmlspecialchars($boat['boat_name']) ?></td>
                                                    <td><?= htmlspecialchars($boat['created_date']) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning btn-edit" data-boatid="<?= htmlspecialchars($boat['boatID']) ?>"><i class="fa fa-edit"></i> Modify</button>
                                                        <button class="btn btn-sm btn-danger btn-delete" data-boatid="<?= htmlspecialchars($boat['boatID']) ?>"><i class="fa fa-trash"></i> Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Edit Boat Modal -->
                                <div class="modal fade" id="editBoatModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">Edit Boat</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="editBoatForm">
                                                    <input type="hidden" id="editBoatID" name="boatID">
                                                    <div class="form-group">
                                                        <label for="editBoatName">Boat Name</label>
                                                        <input type="text" class="form-control" id="editBoatName" name="boat_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-primary" id="saveEditBoat">Save Changes</button>
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
    $('#boat-table').DataTable({ pageLength: 10 });
    // Edit button click
    $(document).on('click', '.btn-edit', function () {
        var row = $(this).closest('tr');
        var boatID = $(this).data('boatid');
        var boat_name = row.find('td:eq(1)').text();
        $('#editBoatID').val(boatID);
        $('#editBoatName').val(boat_name);
        $('#editBoatModal').modal('show');
    });
    // Save Edit Boat
    $('#saveEditBoat').on('click', function (e) {
        e.preventDefault();
        var formData = $('#editBoatForm').serialize() + '&action=edit';
        var boatID = $('#editBoatID').val();
        var row = $("button[data-boatid='" + boatID + "']").closest('tr');
        $.post('../controllers/boatManageController.php', formData, function (response) {
            if (response.success) {
                row.find('td:eq(1)').text($('#editBoatName').val());
                $('#editBoatModal').modal('hide');
                swal({
                    title: "Success!",
                    text: "Boat updated successfully!",
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
        var boatID = $(this).data('boatid');
        var row = $(this).closest('tr');
        
        swal({
            title: "Are you sure?",
            text: "Once deleted, you will not be able to recover this boat!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
        .then((willDelete) => {
            if (willDelete) {
                $.post('../controllers/boatManageController.php', { boatID: boatID, action: 'delete' }, function (response) {
                    if (response.success) {
                        row.remove();
                        swal({
                            title: "Deleted!",
                            text: "Boat has been deleted successfully!",
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
    // Add Boat button click
    $('#addBoatButton').on('click', function (e) {
        e.preventDefault();
        var formData = $('#addBoatForm').serialize() + '&action=add';
        $.post('../controllers/boatManageController.php', formData, function (response) {
            if (response.success) {
                var boat = response.boat;
                var newRow = '<tr>' +
                    '<td>' + boat.boatID + '</td>' +
                    '<td>' + boat.boat_name + '</td>' +
                    '<td>' + boat.created_date + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-warning btn-edit" data-boatid="' + boat.boatID + '"><i class="fa fa-edit"></i> Edit</button> ' +
                        '<button class="btn btn-sm btn-danger btn-delete" data-boatid="' + boat.boatID + '"><i class="fa fa-trash"></i> Delete</button>' +
                    '</td>' +
                '</tr>';
                $('#boat-table').DataTable().row.add($(newRow)).draw();
                $('#addBoatModal').modal('hide');
                $('#addBoatForm')[0].reset();
                swal({
                    title: "Success!",
                    text: "Boat added successfully!",
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
