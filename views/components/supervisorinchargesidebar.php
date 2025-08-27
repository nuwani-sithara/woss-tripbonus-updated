<!-- Sidebar -->
<?php 
// session_start();
// Add this to ensure sessions persist after redirect
// session_regenerate_id(true);
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" data-background-color="dark">
  <div class="sidebar-logo">
    <!-- Logo Header -->
    <div class="logo-header" data-background-color="dark">
      <a href="../views/supervisorinchargedashboard.php" class="logo">
        <img
          src="../assets/img/app-logo.png"
          alt="navbar brand"
          class="navbar-brand"
          height="120"
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
  <div class="sidebar-wrapper scrollbar scrollbar-inner">
    <div class="sidebar-content">
      <ul class="nav nav-secondary">
        <li class="nav-item <?php if($current_page == 'supervisorinchargedashboard.php') echo 'active'; ?>">
          <a href="../views/supervisorinchargedashboard.php">
            <i class="fas fa-home"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'supervisorinchargeapproval.php') echo 'active'; ?>">
          <a href="../views/supervisorinchargeapproval.php">
            <i class="fas fa-plus-circle"></i>
            <p>Approvals</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'supervisorinchargejobs.php') echo 'active'; ?>">
          <a href="../views/supervisorinchargejobs.php">
            <i class="fas fa-tasks"></i>
            <p>Jobs Overview</p>
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>
<!-- End Sidebar --> 