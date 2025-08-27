<!-- Navbar Header -->
<?php
// session_start();
$userName = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : 'user@example.com';
$isLoggedIn = isset($_SESSION['username']);

// Get user's full name
$userFname = isset($_SESSION['fname']) ? $_SESSION['fname'] : '';
$userLname = isset($_SESSION['lname']) ? $_SESSION['lname'] : '';
$userFullName = trim($userFname . ' ' . $userLname);
$displayName = !empty($userFullName) ? $userFullName : $userName;
?>
<nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
  <div class="container-fluid">
    <!-- System Title on the left -->
    <div class="navbar-brand d-none d-lg-flex align-items-center">
      <h4 class="mb-0 text-gray fw-bold">SUBSEA OPS</h4>
    </div>

    <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
      <li class="nav-item topbar-icon dropdown hidden-caret d-flex d-lg-none">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" aria-haspopup="true">
          <i class="fa fa-search"></i>
        </a>
        <ul class="dropdown-menu dropdown-search animated fadeIn">
          <form class="navbar-left navbar-form nav-search">
            <div class="input-group">
              <input type="text" placeholder="Search ..." class="form-control" />
            </div>
          </form>
        </ul>
      </li>
      

      <li class="nav-item topbar-user dropdown hidden-caret">
        <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
          <div class="avatar-sm">
            <img src="../assets/img/profile-icon1.jpg" alt="..." class="avatar-img rounded-circle" />
          </div>
          <span class="profile-username">
            <span class="op-7">Hi,</span>
            <span class="fw-bold"><?php echo htmlspecialchars($displayName); ?></span>
          </span>
        </a>
        <ul class="dropdown-menu dropdown-user animated fadeIn">
          <div class="dropdown-user-scroll scrollbar-outer">
            <li>
              <div class="user-box">
                <div class="avatar-lg">
                  <img src="../assets/img/profile-icon1.jpg" alt="image profile" class="avatar-img rounded" />
                </div>
                <div class="u-text">
                  <h4><?php echo htmlspecialchars($displayName); ?></h4>
                  <p class="text-muted"><?php echo htmlspecialchars($userEmail); ?></p>
                </div>
              </div>
            </li>
            <li>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="../views/profile.php"><i class="fas fa-user-cog me-2"></i> My Profile</a>
              <?php if ($isLoggedIn) : ?>
                <a class="dropdown-item" href="../controllers/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
              <?php endif; ?>
            </li>
          </div>
        </ul>
      </li>
    </ul>
  </div>
</nav>
<!-- End Navbar -->