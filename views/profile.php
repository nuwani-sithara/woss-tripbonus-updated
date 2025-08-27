<?php
session_start();
include '../config/dbConnect.php';

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Get user details and role
$userID = $_SESSION['userID'];
$sql = "SELECT u.*, r.role_name FROM users u 
        LEFT JOIN roles r ON u.roleID = r.roleID 
        WHERE u.userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: ../index.php?error=user_not_found");
    exit();
}

// Determine which sidebar to include based on roleID
$sidebar = 'components/adminSidebar.php'; // default
switch ($user['roleID']) {
    case 1: // Admin
        $sidebar = 'components/sidebar.php';
        break;
    case 3: // Operations Officer
        $sidebar = 'components/attendanceVerifierSidebar.php';
        break;
    case 4: // Operations Manager
        $sidebar = 'components/adminSidebar.php';
        break;
    case 5: // Accountant
        $sidebar = 'components/accountantSidebar.php';
        break;
    case 7: // Director
        $sidebar = 'components/directorSidebar.php';
        break;
    default:
        $sidebar = 'components/defaultSidebar.php';
        break;
}

// Initialize variables
$updateSuccess = false;
$updateError = false;
$errorMessage = '';

// Get user details
$userID = $_SESSION['userID'];
$sql = "SELECT * FROM users WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: ../index.php?error=user_not_found");
    exit();
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $updateError = true;
        $errorMessage = "All password fields are required";
    } 
    elseif ($newPassword !== $confirmPassword) {
        $updateError = true;
        $errorMessage = "New passwords do not match";
    }
    elseif (strlen($newPassword) < 8) {
        $updateError = true;
        $errorMessage = "Password must be at least 8 characters long";
    }
    // Verify current password (plain text comparison)
    elseif ($currentPassword !== $user['password']) {
        $updateError = true;
        $errorMessage = "Current password is incorrect";
    }
    // If validation passes, update password (store as plain text)
    else {
        $updateSql = "UPDATE users SET password = ? WHERE userID = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $newPassword, $userID);
        
        if ($updateStmt->execute()) {
            $updateSuccess = true;
        } else {
            $updateError = true;
            $errorMessage = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Profile - SubseaOps</title>
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
      .profile-header {
        background: linear-gradient(135deg, #2e59d9 0%, rgb(96, 121, 198) 100%);
        color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
      }
      
      .profile-picture {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
      }
      
      .profile-details-card {
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
      }
      
      .password-form {
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1.5rem;
      }
      
      .form-label {
        font-weight: 600;
      }
    </style>
  </head>
  <body>
    <div class="wrapper">
    <?php include $sidebar; ?>
      <div class="main-panel">
        <div class="main-header">
          <div class="main-header-logo">
            <!-- Logo Header -->
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
            <!-- End Logo Header -->
          </div>
          <?php include 'components/navbar.php'; ?>
        </div>

        <div class="container">
          <div class="page-inner">
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
              <div>
                <h3 class="fw-bold mb-3">My Profile</h3>
                <h6 class="op-7 mb-2">Manage your account details and password</h6>
              </div>
            </div>
            
            <?php if ($updateSuccess): ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success!</strong> Your password has been updated.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php elseif ($updateError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
              <div class="col-md-4">
                <div class="profile-header text-center">
                  <img src="../assets/img/profile-icon1.jpg" alt="Profile Picture" class="profile-picture mb-3">
                  <h4><?php echo htmlspecialchars($user['fname'] . ' ' . htmlspecialchars($user['lname'])); ?></h4>
                  <p class="text-white mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                  <p class="text-white mb-0"><?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                
                <div class="card profile-details-card mb-4">
                  <div class="card-body">
                    <h5 class="card-title mb-4">Personal Details</h5>
                    <div class="mb-3">
                      <label class="form-label text-muted">First Name</label>
                      <p class="form-control-static"><?php echo htmlspecialchars($user['fname']); ?></p>
                    </div>
                    <div class="mb-3">
                      <label class="form-label text-muted">Last Name</label>
                      <p class="form-control-static"><?php echo htmlspecialchars($user['lname']); ?></p>
                    </div>
                    <div class="mb-3">
                      <label class="form-label text-muted">Email</label>
                      <p class="form-control-static"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="mb-3">
                      <label class="form-label text-muted">Username</label>
                      <p class="form-control-static"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    <div class="mb-3">
                      <label class="form-label text-muted">Account Created</label>
                      <p class="form-control-static"><?php echo htmlspecialchars($user['created_at']); ?></p>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-md-8">
                <div class="card profile-details-card mb-4">
                  <div class="card-body">
                    <h5 class="card-title mb-4">Update Password</h5>
                    <form method="POST" class="password-form">
                      <div class="mb-3">
                          <label for="current_password" class="form-label">Current Password</label>
                          <div class="input-group">
                              <input type="password" class="form-control" id="current_password" name="current_password" required>
                              <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                  <i class="fas fa-eye"></i>
                              </button>
                          </div>
                      </div>
                      <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                      </div>
                      <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                      </div>
                      <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                    </form>
                  </div>
                </div>
                
                <div class="card profile-details-card">
                  <div class="card-body">
                    <h5 class="card-title mb-4">Account Security</h5>
                    <div class="alert alert-warning">
                      <i class="fas fa-shield-alt me-2"></i>
                      For security reasons, we don't store your password in plain text. If you forget your password, please contact the system administrator.
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="mb-1">Last Login</h6>
                        <p class="text-muted small mb-0"><?php echo date('Y-m-d H:i:s'); ?></p>
                      </div>
                      <!--<div>-->
                      <!--  <button class="btn btn-outline-danger">Deactivate Account</button>-->
                      <!--</div>-->
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
    
    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Sweet Alert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="../assets/js/kaiadmin.min.js"></script>
    <script src="../assets/js/demo.js"></script>
    <script>
      $(document).ready(function() {
        // Password match validation
        $('#new_password, #confirm_password').on('keyup', function() {
          if ($('#new_password').val() != $('#confirm_password').val()) {
            $('#confirm_password').addClass('is-invalid');
          } else {
            $('#confirm_password').removeClass('is-invalid');
          }
        });
      });

      // Password visibility toggle
      document.getElementById('toggleCurrentPassword').addEventListener('click', function() {
          const input = document.getElementById('current_password');
          const icon = this.querySelector('i');
          if (input.type === 'password') {
              input.type = 'text';
              icon.classList.replace('fa-eye', 'fa-eye-slash');
          } else {
              input.type = 'password';
              icon.classList.replace('fa-eye-slash', 'fa-eye');
          }
      });
    </script>
  </body>
</html>