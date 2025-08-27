<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
// DB connection for initial page load (for server-side rendering, if needed)
$host = "localhost";
$user = "tripwosscp_usr";
$password = "aQDOUs-e#3He(xtz";
$database = "tripwosscp_dbs";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$rates = [];
$sql = "SELECT * FROM rates";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rates[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Rates Management - SubseaOps</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport"/>
    <link rel="icon" href="../assets/img/app-logo1.png" type="image/x-icon"/>
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
                    <h3 class="fw-bold mb-3">Rates Management</h3>
                    <!-- <ul class="breadcrumbs mb-3">
                        <li class="nav-home"><a href="#"><i class="icon-home"></i></a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Tables</a></li>
                        <li class="separator"><i class="icon-arrow-right"></i></li>
                        <li class="nav-item"><a href="#">Rates</a></li>
                    </ul> -->
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <h4 class="card-title">Add Rate</h4>
                                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#addRateModal">
                                        <i class="fa fa-plus"></i> Add Rate
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Add Rate Modal -->
                                <div class="modal fade" id="addRateModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title"><span class="fw-mediumbold">New</span> <span class="fw-light">Rate</span></h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="addRateForm">
                                                    <div class="form-group">
                                                        <label for="rate_name">Rate Name</label>
                                                        <input type="text" class="form-control" id="rate_name" name="rate_name" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="rate">Rate</label>
                                                        <input type="number" step="0.01" class="form-control" id="rate" name="rate" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="description">Description</label>
                                                        <textarea class="form-control" id="description" name="description"></textarea>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" id="addRateButton" class="btn btn-primary">Add</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table id="rates-table" class="display table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rate ID</th>
                                                <th>Rate Name</th>
                                                <th>Rate</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rates as $rate): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($rate['rateID']) ?></td>
                                                    <td><?= htmlspecialchars($rate['rate_name']) ?></td>
                                                    <td><?= htmlspecialchars($rate['rate']) ?></td>
                                                    <td>
                                                        <!-- Edit/Delete buttons can be added here -->
                                                        <!-- For now, just placeholders -->
                                                        <button class="btn btn-sm btn-warning btn-edit" data-rateid="<?= htmlspecialchars($rate['rateID']) ?>"><i class="fa fa-edit"></i> Modify</button>
                                                        <button class="btn btn-sm btn-danger btn-delete" data-rateid="<?= htmlspecialchars($rate['rateID']) ?>"><i class="fa fa-trash"></i> Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Edit Rate Modal (structure only, JS to be added for functionality) -->
                                <div class="modal fade" id="editRateModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">Edit Rate</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="editRateForm">
                                                    <input type="hidden" id="editRateID" name="rateID">
                                                    <div class="form-group">
                                                        <label for="editRateName">Rate Name</label>
                                                        <input type="text" class="form-control" id="editRateName" name="rate_name" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="editRate">Rate</label>
                                                        <input type="number" step="0.01" class="form-control" id="editRate" name="rate" required>
                                                    </div>
                                                    <!-- <div class="form-group">
                                                        <label for="editDescription">Description</label>
                                                        <textarea class="form-control" id="editDescription" name="description"></textarea>
                                                    </div> -->
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-primary" id="saveEditRate">Save Changes</button>
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
<!--   Core JS Files   -->
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
    var table = $('#rates-table').DataTable({
        pageLength: 10
    });

    // Add Rate
    $('#addRateButton').on('click', function (e) {
        e.preventDefault();
        var formData = $('#addRateForm').serialize() + '&action=create';
        $.post('../controllers/ratesManageController.php', formData, function (response) {
            if (response.success) {
                var rate = response.rate;
                var newRow = '<tr data-rateid="' + rate.rateID + '">' +
                    '<td>' + rate.rateID + '</td>' +
                    '<td>' + rate.rate_name + '</td>' +
                    '<td>' + rate.rate + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-warning btn-edit" data-rateid="' + rate.rateID + '" data-ratename="' + rate.rate_name + '" data-rate="' + rate.rate + '" data-description="' + (rate.description || '') + '"><i class="fa fa-edit"></i> Edit</button> ' +
                        '<button class="btn btn-sm btn-danger btn-delete" data-rateid="' + rate.rateID + '"><i class="fa fa-trash"></i> Delete</button>' +
                    '</td>' +
                '</tr>';
                table.row.add($(newRow)).draw();
                $('#addRateModal').modal('hide');
                $('#addRateForm')[0].reset();
                swal({
                    title: "Success!",
                    text: "Rate added successfully!",
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
        var rateID = $(this).data('rateid');
        var rateName = row.find('td:eq(1)').text();
        var rate = row.find('td:eq(2)').text();
        var description = row.find('td:eq(3)').text();
        $('#editRateID').val(rateID);
        $('#editRateName').val(rateName);
        $('#editRate').val(rate);
        $('#editRateModal').modal('show');
    });

    // Save Edit Rate
    $('#saveEditRate').on('click', function (e) {
        e.preventDefault();
        var formData = $('#editRateForm').serialize() + '&action=update';
        var rateID = $('#editRateID').val();
        var row = $('tr[data-rateid="' + rateID + '"]');
        $.post('../controllers/ratesManageController.php', formData, function (response) {
            if (response.success) {
                // Update table row
                row.find('td:eq(1)').text($('#editRateName').val());
                row.find('td:eq(2)').text($('#editRate').val());
                row.find('td:eq(3)').text($('#editDescription').val());
                // Update data attributes for edit button
                row.find('.btn-edit').data('ratename', $('#editRateName').val());
                row.find('.btn-edit').data('rate', $('#editRate').val());
                $('#editRateModal').modal('hide');
                swal({
                    title: "Success!",
                    text: "Rate updated successfully!",
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
        var rateID = $(this).data('rateid');
        var row = $(this).closest('tr');
        
        swal({
            title: "Are you sure?",
            text: "Once deleted, you will not be able to recover this rate!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
        .then((willDelete) => {
            if (willDelete) {
                $.post('../controllers/ratesManageController.php', { rateID: rateID, action: 'delete' }, function (response) {
                    if (response.success) {
                        table.row(row).remove().draw();
                        swal({
                            title: "Deleted!",
                            text: "Rate has been deleted successfully!",
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