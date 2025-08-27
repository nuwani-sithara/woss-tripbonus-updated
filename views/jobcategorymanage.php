<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
// No direct DB connection here; all CRUD via AJAX to controllers/jobCategoryManageController.php
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Job Category Management - Kaiadmin Bootstrap 5 Admin Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
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
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Job Category Management</h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <h4 class="card-title">Add Job Category</h4>
                                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#addJobTypeModal">
                                        <i class="fa fa-plus"></i> Add Job Category
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Add Job Type Modal -->
                                <div class="modal fade" id="addJobTypeModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">New Job Category</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="addJobTypeForm">
                                                    <div class="form-group">
                                                        <label for="addTypeName">Job Type Name</label>
                                                        <input type="text" class="form-control" id="addTypeName" name="type_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" id="addJobTypeButton" class="btn btn-primary">Add</button>
                                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Job Type Table -->
                                <div class="table-responsive">
                                    <table id="jobtype-table" class="display table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Job Type ID</th>
                                                <th>Job Type Name</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Populated by JS -->
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Edit Job Type Modal -->
                                <div class="modal fade" id="editJobTypeModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title">Edit Job Category</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="editJobTypeForm">
                                                    <input type="hidden" id="editJobTypeID" name="jobtypeID">
                                                    <div class="form-group">
                                                        <label for="editTypeName">Job Type Name</label>
                                                        <input type="text" class="form-control" id="editTypeName" name="type_name" required>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-primary" id="saveEditJobType">Save Changes</button>
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
    var jobtypeTable = $('#jobtype-table').DataTable({ pageLength: 10 });
    // Load job types
    function loadJobTypes() {
        $.get('../controllers/jobCategoryManageController.php', function (response) {
            if (response.success) {
                jobtypeTable.clear();
                response.jobtypes.forEach(function (jobtype) {
                    var row = [
                        jobtype.jobtypeID,
                        jobtype.type_name,
                        '<button class="btn btn-sm btn-warning btn-edit" data-jobtypeid="' + jobtype.jobtypeID + '"><i class="fa fa-edit"></i> Modify</button> ' +
                        '<button class="btn btn-sm btn-danger btn-delete" data-jobtypeid="' + jobtype.jobtypeID + '"><i class="fa fa-trash"></i> Delete</button>'
                    ];
                    jobtypeTable.row.add(row);
                });
                jobtypeTable.draw();
            } else {
                swal({
                    title: "Error!",
                    text: 'Error loading job types: ' + response.message,
                    icon: "error",
                    button: "OK"
                });
            }
        }, 'json');
    }
    loadJobTypes();
    // Add Job Type
    $('#addJobTypeButton').on('click', function (e) {
        e.preventDefault();
        var formData = $('#addJobTypeForm').serialize() + '&action=add';
        $.post('../controllers/jobCategoryManageController.php', formData, function (response) {
            if (response.success) {
                $('#addJobTypeModal').modal('hide');
                $('#addJobTypeForm')[0].reset();
                loadJobTypes();
                swal({
                    title: "Success!",
                    text: "Job category added successfully!",
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
        var jobtypeID = $(this).data('jobtypeid');
        var type_name = row.find('td:eq(1)').text();
        $('#editJobTypeID').val(jobtypeID);
        $('#editTypeName').val(type_name);
        $('#editJobTypeModal').modal('show');
    });
    // Save Edit Job Type
    $('#saveEditJobType').on('click', function (e) {
        e.preventDefault();
        var formData = $('#editJobTypeForm').serialize() + '&action=edit';
        var jobtypeID = $('#editJobTypeID').val();
        $.post('../controllers/jobCategoryManageController.php', formData, function (response) {
            if (response.success) {
                $('#editJobTypeModal').modal('hide');
                loadJobTypes();
                swal({
                    title: "Success!",
                    text: "Job category updated successfully!",
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
        var jobtypeID = $(this).data('jobtypeid');
        
        swal({
            title: "Are you sure?",
            text: "Once deleted, you will not be able to recover this job category!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
        .then((willDelete) => {
            if (willDelete) {
                $.post('../controllers/jobCategoryManageController.php', { jobtypeID: jobtypeID, action: 'delete' }, function (response) {
                    if (response.success) {
                        loadJobTypes();
                        swal({
                            title: "Deleted!",
                            text: "Job category has been deleted successfully!",
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
