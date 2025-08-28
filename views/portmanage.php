<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
// DB connection for fetching ports
$host = "localhost";
$user = "subseacp_usr";
$password = "-OOaO[?uv65Fz0kE";
$database = "subseacp_dbs";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM ports";
$result = $conn->query($sql);
$ports = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ports[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Port Management - SubseaOps</title>
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
                    <h3 class="fw-bold mb-3">Port Management</h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <h4 class="card-title">Add Port</h4>
                                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#addPortModal">
                                        <i class="fa fa-plus"></i> Add Port
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Add Port Modal -->
                                <div class="modal fade" id="addPortModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">New Port</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="addPortForm">
                                                    <div class="form-group">
                                                        <label for="addPortName">Port Name</label>
                                                        <input type="text" class="form-control" id="addPortName" name="portname" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" id="addPortButton" class="btn btn-primary">Add</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Port Table -->
                                <div class="table-responsive">
                                    <table id="port-table" class="display table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Port ID</th>
                                                <th>Port Name</th>
                                                <th>Created Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ports as $port): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($port['portID']) ?></td>
                                                    <td><?= htmlspecialchars($port['portname']) ?></td>
                                                    <td><?= htmlspecialchars($port['created_date']) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning btn-edit" data-portid="<?= htmlspecialchars($port['portID']) ?>"><i class="fa fa-edit"></i> Modify</button>
                                                        <button class="btn btn-sm btn-danger btn-delete" data-portid="<?= htmlspecialchars($port['portID']) ?>"><i class="fa fa-trash"></i> Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Edit Port Modal -->
                                <div class="modal fade" id="editPortModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">Edit Port</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="editPortForm">
                                                    <input type="hidden" id="editPortID" name="portID">
                                                    <div class="form-group">
                                                        <label for="editPortName">Port Name</label>
                                                        <input type="text" class="form-control" id="editPortName" name="portname" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-primary" id="saveEditPort">Save Changes</button>
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
    $('#port-table').DataTable({ pageLength: 10 });
    // Edit button click
    $(document).on('click', '.btn-edit', function () {
        var row = $(this).closest('tr');
        var portID = $(this).data('portid');
        var portname = row.find('td:eq(1)').text();
        $('#editPortID').val(portID);
        $('#editPortName').val(portname);
        $('#editPortModal').modal('show');
    });
    // Save Edit Port
    $('#saveEditPort').on('click', function (e) {
        e.preventDefault();
        var formData = $('#editPortForm').serialize() + '&action=edit';
        var portID = $('#editPortID').val();
        var row = $("button[data-portid='" + portID + "']").closest('tr');
        $.post('../controllers/portManageController.php', formData, function (response) {
            if (response.success) {
                row.find('td:eq(1)').text($('#editPortName').val());
                $('#editPortModal').modal('hide');
                swal({
                    title: "Success!",
                    text: "Port updated successfully!",
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
        var portID = $(this).data('portid');
        var row = $(this).closest('tr');
        
        swal({
            title: "Are you sure?",
            text: "Once deleted, you will not be able to recover this port!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
        .then((willDelete) => {
            if (willDelete) {
                $.post('../controllers/portManageController.php', { portID: portID, action: 'delete' }, function (response) {
                    if (response.success) {
                        row.remove();
                        swal({
                            title: "Deleted!",
                            text: "Port has been deleted successfully!",
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
    // Add Port button click
    $('#addPortButton').on('click', function (e) {
        e.preventDefault();
        var formData = $('#addPortForm').serialize() + '&action=add';
        $.post('../controllers/portManageController.php', formData, function (response) {
            if (response.success) {
                var port = response.port;
                var newRow = '<tr>' +
                    '<td>' + port.portID + '</td>' +
                    '<td>' + port.portname + '</td>' +
                    '<td>' + port.created_date + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-warning btn-edit" data-portid="' + port.portID + '"><i class="fa fa-edit"></i> Edit</button> ' +
                        '<button class="btn btn-sm btn-danger btn-delete" data-portid="' + port.portID + '"><i class="fa fa-trash"></i> Delete</button>' +
                    '</td>' +
                '</tr>';
                $('#port-table').DataTable().row.add($(newRow)).draw();
                $('#addPortModal').modal('hide');
                $('#addPortForm')[0].reset();
                swal({
                    title: "Success!",
                    text: "Port added successfully!",
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
