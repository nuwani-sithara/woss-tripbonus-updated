<?php
include_once __DIR__ . '/../controllers/getSystemUsersController.php';
$users = getSystemUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>System Users - SubseaOps</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../assets/img/app-logo1.png" type="image/x-icon" />

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
</head>
<body>
<div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <?php include 'components/navbar.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="page-header d-flex align-items-center">
                    <a href="usermanage.php" class="btn btn-link p-0 me-2" title="Back">
                        <i class="fa fa-arrow-left fa-lg"></i>
                    </a>
                    <h3 class="fw-bold mb-3 mb-0">System Users</h3>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">System Users List</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="system-users-table" class="display table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Role ID</th>
                                        <th>Rate</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['userID']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><?= htmlspecialchars($user['fname']) ?></td>
                                            <td><?= htmlspecialchars($user['lname']) ?></td>
                                            <td><?= htmlspecialchars($user['roleID']) ?></td>
                                            <td><?= htmlspecialchars($user['rate_name']) ?></td>
                                            <td><?= htmlspecialchars($user['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
<script src="../assets/js/kaiadmin.min.js"></script>
<script src="../assets/js/setting-demo2.js"></script>
<script>
$(document).ready(function () {
    $('#system-users-table').DataTable({
        pageLength: 10
    });
});
</script>
</body>
</html>
