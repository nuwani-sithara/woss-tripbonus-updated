<!-- Sidebar -->
<?php 
// session_start();
// Add this to ensure sessions persist after redirect
// session_regenerate_id(true);
$current_page = basename($_SERVER['PHP_SELF']);
$settings_pages = [
  'usermanage.php','rolesmanage.php','ratesmanage.php','jobcategorymanage.php','boatmanage.php','vesselmanage.php','portmanage.php','systemusers.php','employees.php'
];
$settings_active = in_array($current_page, $settings_pages);
?>

<div class="sidebar" data-background-color="dark">
  <div class="sidebar-logo">
    <!-- Logo Header -->
    <div class="logo-header" data-background-color="dark">
      <a href="../views/admindashboard.php" class="logo">
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
        <li class="nav-item <?php if($current_page == 'admindashboard.php') echo 'active'; ?>">
          <a href="../views/admindashboard.php">
            <i class="fas fa-home"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'approvejobs.php') echo 'active'; ?>">
          <a href="../views/approvejobs.php">
            <i class="fas fa-check-circle"></i>
            <p>Job Approvals</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'operationmanagerjobs.php') echo 'active'; ?>">
          <a href="../views/operationmanagerjobs.php">
            <i class="fas fa-tasks"></i>
            <p>Manage Jobs</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'monthlypayments.php') echo 'active'; ?>">
          <a href="../views/monthlypayments.php">
            <i class="fas fa-calendar-check"></i>
            <p>Publish Jobs</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'paymentstatus.php') echo 'active'; ?>">
          <a href="../views/paymentstatus.php">
            <i class="fas fa-clipboard-check"></i>
            <p>Payment Status</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'joballowancebreakdown.php') echo 'active'; ?>">
          <a href="../views/joballowancebreakdown.php">
            <i class="fas fa-chart-pie"></i>
            <p>Job Allowance Report</p>
          </a>
        </li>
        <li class="nav-item dropdown <?php if($settings_active || $current_page == "systemusers.php" || $current_page == "employees.php") echo 'active'; ?>">
          <a data-toggle="collapse" href="#settingsDropdown" aria-expanded="<?php echo $settings_active ? 'true' : 'false'; ?>" class="dropdown-toggle">
            <i class="fas fa-cogs"></i>
            <p>Settings</p>
            <span class="caret"></span>
          </a>
          <div class="collapse<?php if($settings_active) echo ' show'; ?>" id="settingsDropdown">
            <ul class="nav nav-collapse">
              <li class="nav-item <?php if($current_page == 'usermanage.php' || $current_page == "systemusers.php" || $current_page == "employees.php") echo 'active'; ?>">
                <a href="../views/usermanage.php">
                  <span class="sub-item">User Management</span>
                </a>
              </li>
              <li class="nav-item <?php if($current_page == 'rolesmanage.php') echo 'active'; ?>">
                <a href="../views/rolesmanage.php">
                  <span class="sub-item">Role Defining</span>
                </a>
              </li>
              <li class="nav-item <?php if($current_page == 'ratesmanage.php') echo 'active'; ?>">
                <a href="../views/ratesmanage.php">
                  <span class="sub-item">Rates Defining</span>
                </a>
              </li>
              <li class="nav-item <?php if($current_page == 'jobcategorymanage.php') echo 'active'; ?>">
                <a href="../views/jobcategorymanage.php">
                  <span class="sub-item">Job Categories</span>
                </a>
              </li>
              <li class="nav-item <?php if($current_page == 'boatmanage.php') echo 'active'; ?>">
                <a href="../views/boatmanage.php">
                  <span class="sub-item">Boat Management</span>
                </a>
              </li>
              <li class="nav-item <?php if($current_page == 'vesselmanage.php') echo 'active'; ?>">
                <a href="../views/vesselmanage.php">
                  <span class="sub-item">Vessel Management</span>
                </a>
              </li>
              <li class="nav-item <?php if($current_page == 'portmanage.php') echo 'active'; ?>">
                <a href="../views/portmanage.php">
                  <span class="sub-item">Port Management</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </div>
  </div>
</div>
<!-- End Sidebar --> 