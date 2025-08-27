<?php
session_start();
include '../config/dbConnect.php';
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID']) || $_SESSION['roleID'] != 4) { // 4 = Operation Manager
    header("Location: ../index.php?error=access_denied");
    exit();
}
$roles = [];
$rates = [];

// Fetch roles
$sql = "SELECT roleID, role_name FROM roles";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}
// Fetch rates
$sql = "SELECT rateID, rate_name FROM rates";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rates[] = $row;
    }
}

// Get counts for stats cards
include_once __DIR__ . '/../controllers/getSystemUsersController.php';
include_once __DIR__ . '/../controllers/getEmployeesController.php';
$systemUsersCount = getSystemUsersCount();
$employeesCount = getEmployeesCount();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>User Management - SubseaOps</title>
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
              <h3 class="fw-bold mb-3">User Management</h3>
              <!-- <ul class="breadcrumbs mb-3">
                <li class="nav-home">
                  <a href="#">
                    <i class="icon-home"></i>
                  </a>
                </li>
                <li class="separator">
                  <i class="icon-arrow-right"></i>
                </li>
                <li class="nav-item">
                  <a href="#">Tables</a>
                </li>
                <li class="separator">
                  <i class="icon-arrow-right"></i>
                </li>
                <li class="nav-item">
                  <a href="#">Datatables</a>
                </li>
              </ul> -->
            </div>
            <div class="mb-3">
            <div class="row">
                <div class="col-md-3">
                  <div class="card card-stats card-round">
                    <div class="card-body">
                      <div class="row align-items-center">
                        <div class="col-icon">
                          <div class="icon-big text-center icon-primary bubble-shadow-small">
                            <i class="fa fa-users"></i>
                          </div>
                        </div>
                        <div class="col col-stats ml-3 ml-sm-0">
                          <div class="numbers">
                            <p class="card-category">System Users</p>
                            <h4 class="card-title" id="exportToExcel" style="cursor: pointer; color: #007bff;">
                              <a href="systemUsers.php" style="color: #007bff; text-decoration: none;">View All</a>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card card-stats card-round">
                    <div class="card-body">
                      <div class="row align-items-center">
                        <div class="col-icon">
                          <div class="icon-big text-center icon-success bubble-shadow-small">
                            <i class="fa fa-user-tie"></i>
                          </div>
                        </div>
                        <div class="col col-stats ml-3 ml-sm-0">
                          <div class="numbers">
                            <p class="card-category">Employees</p>
                            <h4 class="card-title" id="exportToPDF" style="cursor: pointer; color: #28a745;">
                              <a href="employees.php" style="color: #28a745; text-decoration: none;">View All</a>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card card-stats card-round">
                    <div class="card-body">
                      <div class="row align-items-center">
                        <div class="col-icon">
                          <div class="icon-big text-center icon-info bubble-shadow-small">
                            <i class="fa fa-users"></i>
                          </div>
                        </div>
                        <div class="col col-stats ml-3 ml-sm-0">
                          <div class="numbers">
                            <p class="card-category">System Users Count</p>
                            <h4 class="card-title" style="color: #17a2b8;">
                              <?= $systemUsersCount ?>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card card-stats card-round">
                    <div class="card-body">
                      <div class="row align-items-center">
                        <div class="col-icon">
                          <div class="icon-big text-center icon-warning bubble-shadow-small">
                            <i class="fa fa-user-tie"></i>
                          </div>
                        </div>
                        <div class="col col-stats ml-3 ml-sm-0">
                          <div class="numbers">
                            <p class="card-category">Employees Count</p>
                            <h4 class="card-title" style="color: #ffc107;">
                              <?= $employeesCount ?>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <br>
            <div class="row">
              
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex align-items-center">
                      <h4 class="card-title">Add User</h4>
                      <button
                        class="btn btn-primary btn-round ms-auto"
                        data-bs-toggle="modal"
                        data-bs-target="#addRowModal"
                      >
                        <i class="fa fa-plus"></i>
                        Add User
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <!-- Modal -->
                    <div
                      class="modal fade"
                      id="addRowModal"
                      tabindex="-1"
                      role="dialog"
                      aria-hidden="true"
                    >
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header border-0">
                            <h5 class="modal-title">
                              <span class="fw-mediumbold"> New</span>
                              <span class="fw-light"> User </span>
                            </h5>
                            <button
                              type="button"
                              class="close"
                              data-dismiss="modal"
                              aria-label="Close"
                            >
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <form id="addUserForm">
                              <div class="form-group">
                                  <label for="addEno">Employee Number (Optional)</label>
                                  <input type="text" class="form-control" id="addEno" name="eno">
                              </div>
                              <div class="form-group">
                                <label for="addEmail">Email</label>
                                <input type="email" class="form-control" id="addEmail" name="email" required>
                              </div>
                              <div class="form-group">
                                <label for="addUsername">Username</label>
                                <input type="text" class="form-control" id="addUsername" name="username" required>
                              </div>
                              <div class="form-group">
                                <label for="addFname">First Name</label>
                                <input type="text" class="form-control" id="addFname" name="fname" required>
                              </div>
                              <div class="form-group">
                                <label for="addLname">Last Name</label>
                                <input type="text" class="form-control" id="addLname" name="lname" required>
                              </div>
                              <div class="form-group">
                                <label for="addPassword">Password</label>
                                <input type="text" class="form-control" id="password" name="password" required>
                              </div>
                              <div class="form-group">
                                <label for="addRoleID">Role</label>
                                <select class="form-control" id="addRoleID" name="roleID" required>
                                  <option value="">Select Role</option>
                                  <?php foreach ($roles as $role): ?>
                                    <option value="<?= htmlspecialchars($role['roleID']) ?>">
                                      <?= htmlspecialchars($role['role_name']) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="form-group">
                                <label for="addRateID">Rate</label>
                                <select class="form-control" id="addRateID" name="rateID">
                                  <option value="">Select Rate</option>
                                  <?php foreach ($rates as $rate): ?>
                                    <option value="<?= htmlspecialchars($rate['rateID']) ?>">
                                      <?= htmlspecialchars($rate['rate_name']) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                            </form>
                          </div>
                          <div class="modal-footer border-0">
                            <button
                              type="button"
                              id="addUserButton"
                              class="btn btn-primary"
                            >
                              Add
                            </button>
                            <button
                              type="button"
                              class="btn btn-danger"
                              data-dismiss="modal"
                            >
                              Close
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="table-responsive">
                      <table id="user-table" class="display table table-striped table-hover">
                        <thead>
                          <tr>
                            <th>User ID</th>
                            <th>Employee No</th> <!-- New column -->
                            <th>Email</th>
                            <th>Username</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Role ID</th>
                            <th>Rate</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            include_once __DIR__ . '/../controllers/getUsersController.php';
                            $users = getAllUsers();
                            foreach ($users as $user) {
                              echo '<tr>';
                              echo '<td>' . htmlspecialchars($user['userID']) . '</td>';
                              echo '<td>' . htmlspecialchars($user['eno'] ?? '') . '</td>'; // New column
                              echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                              echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                              echo '<td>' . htmlspecialchars($user['fname']) . '</td>';
                              echo '<td>' . htmlspecialchars($user['lname']) . '</td>';
                              echo '<td>' . htmlspecialchars($user['roleID']) . '</td>';
                              echo '<td data-rateid="' . htmlspecialchars($user['rateID']) . '">' . htmlspecialchars($user['rate_name']) . '</td>';
                              echo '<td>';
                              echo '<button class="btn btn-sm btn-warning btn-edit" data-userid="' . htmlspecialchars($user['userID']) . '"><i class="fa fa-edit"></i> Modify</button> ';
                              echo '<button class="btn btn-sm btn-danger btn-delete" data-userid="' . htmlspecialchars($user['userID']) . '"><i class="fa fa-trash"></i> Delete</button>';
                              echo '</td>';
                              echo '</tr>';
                            }
                          ?>
                        </tbody>
                      </table>
                    </div>
                    <!-- Edit User Modal -->
                    <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header border-0">
                            <h5 class="modal-title">Edit User</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <form id="editUserForm">
                              <input type="hidden" id="editUserID" name="userID">
                              <!-- Inside the editUserForm -->
                              <div class="form-group">
                                  <label for="editEno">Employee Number</label>
                                  <input type="text" class="form-control" id="editEno" name="eno">
                              </div>
                              <div class="form-group">
                                <label for="editEmail">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                              </div>
                              <div class="form-group">
                                <label for="editUsername">Username</label>
                                <input type="text" class="form-control" id="editUsername" name="username" required>
                              </div>
                              <div class="form-group">
                                <label for="editFname">First Name</label>
                                <input type="text" class="form-control" id="editFname" name="fname" required>
                              </div>
                              <div class="form-group">
                                <label for="editLname">Last Name</label>
                                <input type="text" class="form-control" id="editLname" name="lname" required>
                              </div>
                              <div class="form-group">
                                <label for="editPassword">Password (Leave blank to keep current)</label>
                                <input type="password" class="form-control" id="editPassword" name="password">
                              </div>
                              <div class="form-group">
                                <label for="editRoleID">Role</label>
                                <select class="form-control" id="editRoleID" name="roleID" required>
                                  <option value="">Select Role</option>
                                  <?php foreach ($roles as $role): ?>
                                    <option value="<?= htmlspecialchars($role['roleID']) ?>">
                                      <?= htmlspecialchars($role['role_name']) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="form-group">
                                <label for="editRateID">Rate</label>
                                <select class="form-control" id="editRateID" name="rateID">
                                  <option value="">Select Rate</option>
                                  <?php foreach ($rates as $rate): ?>
                                    <option value="<?= htmlspecialchars($rate['rateID']) ?>">
                                      <?= htmlspecialchars($rate['rate_name']) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                            </form>
                          </div>
                          <div class="modal-footer border-0">
                            <button type="button" class="btn btn-primary" id="saveEditUser">Save Changes</button>
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
    <!--   Core JS Files   -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <!-- Datatables -->
    <script src="../assets/js/plugin/datatables/datatables.min.js"></script>
    <!-- SweetAlert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>
    <!-- Kaiadmin JS -->
    <script src="../assets/js/kaiadmin.min.js"></script>
    <!-- Kaiadmin DEMO methods, don't include it in your project! -->
    <script src="../assets/js/setting-demo2.js"></script>
    <script>
      $(document).ready(function () {
        $('#user-table').DataTable({
          pageLength: 10
        });
        // Get all user data
        $(document).on('click', '.btn-edit', function () {
          var row = $(this).closest('tr');
          var userID = $(this).data('userid');
          var eno = row.find('td:eq(1)').text(); // Get eno value
          var email = row.find('td:eq(2)').text();
          var username = row.find('td:eq(3)').text();
          var fname = row.find('td:eq(4)').text();
          var lname = row.find('td:eq(5)').text();
          var roleID = row.find('td:eq(6)').text();
          var rateID = row.find('td:eq(7)').data('rateid');

          $('#editUserID').val(userID);
          $('#editEno').val(eno); // Set eno value
          $('#editEmail').val(email);
          $('#editUsername').val(username);
          $('#editFname').val(fname);
          $('#editLname').val(lname);
          $('#editRoleID').val(roleID);
          $('#editRateID').val(rateID);

          $('#editUserModal').modal('show');
        });

        // Save Edit User
        $('#saveEditUser').on('click', function (e) {
          e.preventDefault();
          var formData = $('#editUserForm').serialize();
          var userID = $('#editUserID').val();
          var row = $("button[data-userid='" + userID + "']").closest('tr');
          
          $.post('../controllers/editUserController.php', formData, function (response) {
            if (response.success) {
              // Update table row
              row.find('td:eq(0)').text($('#editUserID').val());
              row.find('td:eq(1)').text($('#editEmail').val());
              row.find('td:eq(2)').text($('#editUsername').val());
              row.find('td:eq(3)').text($('#editFname').val());
              row.find('td:eq(4)').text($('#editLname').val());
              row.find('td:eq(5)').text($('#editRoleID').val());
              row.find('td:eq(6)').text($('#editRateID').find('option:selected').text());
              $('#editUserModal').modal('hide');
              swal({
                  title: "Success!",
                  text: "User updated successfully!",
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
          var userID = $(this).data('userid');
          var row = $(this).closest('tr');
          
          swal({
              title: "Are you sure?",
              text: "Once deleted, you will not be able to recover this user!",
              icon: "warning",
              buttons: true,
              dangerMode: true,
          })
          .then((willDelete) => {
              if (willDelete) {
                  $.post('../controllers/deleteUserController.php', { userID: userID }, function (response) {
                      if (response.success) {
                          row.remove();
                          swal({
                              title: "Deleted!",
                              text: "User has been deleted successfully!",
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

        // Add User button click
        $('#addUserButton').on('click', function (e) {
          e.preventDefault();
          var formData = $('#addUserForm').serialize();
          $.post('../controllers/userManageController.php', formData, function (response) {
            if (response.success) {
              // Add new row to table
              var user = response.user;
              var newRow = '<tr>' +
                '<td>' + user.userID + '</td>' +
                '<td>' + (user.eno || '') + '</td>' + // New column
                '<td>' + user.email + '</td>' +
                '<td>' + user.username + '</td>' +
                '<td>' + user.fname + '</td>' +
                '<td>' + user.lname + '</td>' +
                '<td>' + user.roleID + '</td>' +
                '<td data-rateid="' + user.rateID + '">' + user.rate_name + '</td>' +
                '<td>' +
                  '<button class="btn btn-sm btn-warning btn-edit" data-userid="' + user.userID + '"><i class="fa fa-edit"></i> Edit</button> ' +
                  '<button class="btn btn-sm btn-danger btn-delete" data-userid="' + user.userID + '"><i class="fa fa-trash"></i> Delete</button>' +
                '</td>' +
              '</tr>';
              $('#user-table').DataTable().row.add($(newRow)).draw();
              $('#addRowModal').modal('hide');
              $('#addUserForm')[0].reset();
              swal({
                  title: "Success!",
                  text: "User added successfully!",
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
