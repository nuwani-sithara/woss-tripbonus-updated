<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
$host = "localhost";
$user = "tripwosscp_usr";
$password = "aQDOUs-e#3He(xtz";
$database = "tripwosscp_dbs";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$roles = [];
$sql = "SELECT * FROM roles";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Roles Management - WOSS Trip Bonus System</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport"/>
    <link rel="icon" href="../assets/img/app-logo1.png" type="image/x-icon"/>
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
</head>
<body>
<div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <div class="main-header-logo">
                <div class="logo-header" data-background-color="dark">
                    <a href="../index.html" class="logo">
                        <img src="../assets/img/app-logo1.png" alt="navbar brand" class="navbar-brand" height="20"/>
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar"><i class="gg-menu-right"></i></button>
                        <button class="btn btn-toggle sidenav-toggler"><i class="gg-menu-left"></i></button>
                    </div>
                    <button class="topbar-toggler more"><i class="gg-more-vertical-alt"></i></button>
                </div>
            </div>
            <?php include 'components/navbar.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Roles Management</h3>
                    <!-- <ul class="breadcrumbs mb-3">
                        <li class="nav-home"><a href="#"><i class="icon-home"></i></a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Tables</a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Roles</a></li>
                    </ul> -->
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <h4 class="card-title">Add Role</h4>
                                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                                        <i class="fa fa-plus"></i> Add Role
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Add Role Modal -->
                                <div class="modal fade" id="addRoleModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title"><span class="fw-mediumbold">New</span> <span class="fw-light">Role</span></h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="addRoleForm">
                                                    <div class="form-group">
                                                        <label for="role_name">Role Name</label>
                                                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" id="addRoleButton" class="btn btn-primary">Add</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table id="roles-table" class="display table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Role ID</th>
                                                <th>Role Name</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($roles as $role): ?>
                                                <tr data-roleid="<?= htmlspecialchars($role['roleID']) ?>">
                                                    <td><?= htmlspecialchars($role['roleID']) ?></td>
                                                    <td><?= htmlspecialchars($role['role_name']) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning btn-edit" data-roleid="<?= htmlspecialchars($role['roleID']) ?>" data-rolename="<?= htmlspecialchars($role['role_name']) ?>"><i class="fa fa-edit"></i> Modify</button>
                                                        <button class="btn btn-sm btn-danger btn-delete" data-roleid="<?= htmlspecialchars($role['roleID']) ?>"><i class="fa fa-trash"></i> Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Edit Role Modal -->
                                <div class="modal fade" id="editRoleModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">Edit Role</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="editRoleForm">
                                                    <input type="hidden" id="editRoleID" name="roleID">
                                                    <div class="form-group">
                                                        <label for="editRoleName">Role Name</label>
                                                        <input type="text" class="form-control" id="editRoleName" name="role_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-primary" id="saveEditRole">Save Changes</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Edit Modal -->
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
    var table = $('#roles-table').DataTable({
        pageLength: 10
    });

    // Add Role
    $('#addRoleButton').on('click', function (e) {
        e.preventDefault();
        var formData = $('#addRoleForm').serialize() + '&action=create';
        $.post('../controllers/roleManageController.php', formData, function (response) {
            if (response.success) {
                var role = response.role;
                var newRow = '<tr data-roleid="' + role.roleID + '">' +
                    '<td>' + role.roleID + '</td>' +
                    '<td>' + role.role_name + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-warning btn-edit" data-roleid="' + role.roleID + '" data-rolename="' + role.role_name + '"><i class="fa fa-edit"></i> Edit</button> ' +
                        '<button class="btn btn-sm btn-danger btn-delete" data-roleid="' + role.roleID + '"><i class="fa fa-trash"></i> Delete</button>' +
                    '</td>' +
                '</tr>';
                table.row.add($(newRow)).draw();
                $('#addRoleModal').modal('hide');
                $('#addRoleForm')[0].reset();
                swal({
                    title: "Success!",
                    text: "Role added successfully!",
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

    // Edit button click
    $(document).on('click', '.btn-edit', function () {
        var row = $(this).closest('tr');
        var roleID = $(this).data('roleid');
        var roleName = row.find('td:eq(1)').text();
        $('#editRoleID').val(roleID);
        $('#editRoleName').val(roleName);
        $('#editRoleModal').modal('show');
    });

    // Save Edit Role
    $('#saveEditRole').on('click', function (e) {
        e.preventDefault();
        var formData = $('#editRoleForm').serialize() + '&action=update';
        var roleID = $('#editRoleID').val();
        var row = $('tr[data-roleid="' + roleID + '"]');
        $.post('../controllers/roleManageController.php', formData, function (response) {
            if (response.success) {
                // Update table row
                row.find('td:eq(1)').text($('#editRoleName').val());
                // Update data attributes for edit button
                row.find('.btn-edit').data('rolename', $('#editRoleName').val());
                $('#editRoleModal').modal('hide');
                swal({
                    title: "Success!",
                    text: "Role updated successfully!",
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
        var roleID = $(this).data('roleid');
        var row = $(this).closest('tr');
        
        swal({
            title: "Are you sure?",
            text: "Once deleted, you will not be able to recover this role!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
        .then((willDelete) => {
            if (willDelete) {
                $.post('../controllers/roleManageController.php', { roleID: roleID, action: 'delete' }, function (response) {
                    if (response.success) {
                        table.row(row).remove().draw();
                        swal({
                            title: "Deleted!",
                            text: "Role has been deleted successfully!",
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
});
</script>
</body>
</html>
