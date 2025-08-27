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
      <a href="../views/paymentverification.php" class="logo">
        <img
          src="../assets/img/app-logo.png"
          alt="navbar brand"
          class="navbar-brand"
          height="130"
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
        <!-- <li class="nav-item <?php if($current_page == 'accountantdashboard.php') echo 'active'; ?>">
          <a href="../views/accountantDashboard.php">
            <i class="fas fa-home"></i>
            <p>Dashboard</p>
          </a>
        </li> -->
        <li class="nav-item <?php if($current_page == 'paymentverification.php') echo 'active'; ?>">
          <a href="../views/paymentverification.php">
            <i class="fas fa-money-bill-wave"></i>
            <p>Payment Verification</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'exportpayrollreport.php') echo 'active'; ?>">
          <a href="../views/exportpayrollreport.php">
            <i class="fas fa-file-export"></i>
            <p>Payroll Export</p>
          </a>
        </li>
        <li class="nav-item <?php if($current_page == 'accountantjoballowancebreakdown.php') echo 'active'; ?>">
          <a href="../views/accountantjoballowancebreakdown.php">
            <i class="fas fa-calendar-alt"></i>
            <p>Job Allowance Reoprt</p>
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>
<!-- End Sidebar --> 