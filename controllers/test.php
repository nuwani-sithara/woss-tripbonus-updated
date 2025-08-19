<ul class="dropdown-menu dropdown-user animated fadeIn">
  <li>
    <!-- user box content -->
  </li>
  <li>
    <div class="dropdown-divider"></div>
  </li>
  <?php if ($isLoggedIn) : ?>
    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">Reset Password</a></li>
    <li><a class="dropdown-item" href="../controllers/logout.php">Logout</a></li>
  <?php endif; ?>
</ul>