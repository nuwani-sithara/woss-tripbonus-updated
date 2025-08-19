<?php
session_start();
include '../config/dbConnect.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// // Check if user is logged in and has role_id = 1
// if (!isset($_SESSION['userID']) || 
//     !isset($_SESSION['roleID']) || 
//     $_SESSION['roleID'] != 4) {
//     header("Location: ../index.php?error=access_denied");
//     exit();
// }


?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>WOSS - Trip Bonus System</title>
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

    <style>
      .dropdown-toggle {
        background-color: #fff !important;
        border: 1px solid #ced4da !important;
        color: #495057 !important;
        text-align: left !important;
        padding: 0.375rem 0.75rem !important;
      }

      .dropdown-toggle:hover,
      .dropdown-toggle:focus {
        background-color: #f8f9fa !important;
        border-color: #80bdff !important;
        color: #495057 !important;
      }

      .dropdown-toggle::after {
        float: right;
        margin-top: 8px;
      }

      .dropdown-menu {
        width: 100%;
        padding: 0.5rem 0;
        margin: 0;
        border: 1px solid rgba(0,0,0,.15);
        border-radius: 0.25rem;
      }

      .dropdown-item {
        padding: 0.5rem 1rem;
        color: #212529;
      }

      .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #16181b;
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
              <h3 class="fw-bold mb-3">User Creation Form</h3>
              <ul class="breadcrumbs mb-3">
                <li class="nav-home">
                  <a href="#">
                    <i class="icon-home"></i>
                  </a>
                </li>
                <li class="separator">
                  <i class="icon-arrow-right"></i>
                </li>
                <li class="nav-item">
                  <a href="#">Forms</a>
                </li>
                <li class="separator">
                  <i class="icon-arrow-right"></i>
                </li>
                <li class="nav-item">
                  <a href="#">Basic Form</a>
                </li>
              </ul>
            </div>
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header">
                    <div class="card-title">Fill This Form To Create A New User</div>
                  </div>
                  <form method="POST" action="../controllers/createUserController.php" class="space-y-6" onsubmit="return validateForm()">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                          <label for="email">Email</label>
                          <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            placeholder="Enter email address"
                            required
                          />
                        </div>

                        <div class="form-group">
                          <label for="username">Username</label>
                          <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            placeholder="Enter username"
                            required
                          />
                        </div>

                        <div class="form-group">
                          <label for="password">Password</label>
                          <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Enter password"
                            required
                          />
                        </div>
                      </div>

                      <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                          <label for="fname">First Name</label>
                          <input
                            type="text"
                            class="form-control"
                            id="fname"
                            name="fname"
                            placeholder="Enter first name"
                            required
                          />
                        </div>

                        <div class="form-group">
                          <label for="lname">Last Name</label>
                          <input
                            type="text"
                            class="form-control"
                            id="lname"
                            name="lname"
                            placeholder="Enter last name"
                            required
                          />
                        </div>

                        <div class="form-group">
                          <label for="userRole">User Role</label>
                          <input type="hidden" name="roleID" id="roleID" required>
                          <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle w-100 text-start" type="button" id="userRoleDropdown" data-bs-toggle="dropdown" aria-expanded="false" name="roleID">
                              Select User Role
                            </button>
                            <ul class="dropdown-menu w-100" aria-labelledby="userRoleDropdown">
                            <?php while ($role = mysqli_fetch_assoc($role_result)): ?>
                                <li><a class="dropdown-item" href="#" data-value="<?= $role['roleID'] ?>"><?= htmlspecialchars($role['role_name']) ?></a></li>
                            <?php endwhile; ?>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="card-action">
                    <button class="btn btn-success" name="add_user" type="submit">Submit</button>
                    <button class="btn btn-danger">Cancel</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php include 'components/footer.php'; ?>
      </div>
    </div>

    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

    <!-- Chart Circle -->
    <script src="../assets/js/plugin/chart-circle/circles.min.js"></script>

    <!-- Datatables -->
    <script src="../assets/js/plugin/datatables/datatables.min.js"></script>

    <!-- Bootstrap Notify -->
    <script src="../assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

    <!-- jQuery Vector Maps -->
    <script src="../assets/js/plugin/jsvectormap/jsvectormap.min.js"></script>
    <script src="../assets/js/plugin/jsvectormap/world.js"></script>

    <!-- Sweet Alert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="../assets/js/kaiadmin.min.js"></script>

    <!-- Kaiadmin DEMO methods, don't include it in your project! -->
    <script src="../assets/js/setting-demo.js"></script>
    <script src="../assets/js/demo.js"></script>

    <!-- Initialize dropdowns -->
    <script>
      $(document).ready(function() {
        // Handle dropdown item selection
        $('.dropdown-item').on('click', function(e) {
          e.preventDefault();
          var value = $(this).data('value');
          var text = $(this).text();
          var dropdownButton = $(this).closest('.dropdown').find('.dropdown-toggle');
          dropdownButton.text(text);
          $('#roleID').val(value);
        });
      });
    </script>
  </body>
</html>

