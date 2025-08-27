<?php
session_start();
$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Login - WOSS Trip Bonus System</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="assets/img/app-logo1.png" type="image/x-icon" />

    <!-- Fonts and icons -->
    <script src="assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
      WebFont.load({
        google: { families: ["Inter:300,400,500,600,700"] },
        custom: {
          families: [
            "Font Awesome 5 Solid",
            "Font Awesome 5 Regular",
            "Font Awesome 5 Brands",
            "simple-line-icons",
          ],
          urls: ["assets/css/fonts.min.css"],
        },
        active: function () {
          sessionStorage.fonts = true;
        },
      });
    </script>
    <!-- CSS Files -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/plugins.min.css" />
    <link rel="stylesheet" href="assets/css/kaiadmin.min.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <style>
      :root {
        --primary-color:#042249;
        --primary-dark:rgba(0,3,12,255);
        --text-color: #2b3445;
        --light-gray: #f8f9fa;
        --border-radius: 10px;
        --box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
      }
      
      body {
        min-height: 100vh;
        background-color: var(--light-gray);
        background-image: radial-gradient(circle at 85% 15%, rgba(67, 97, 238, 0.08) 0%, rgba(255, 255, 255, 0) 50%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Inter', sans-serif;
        padding: 20px;
      }
      
      .login-container {
        display: flex;
        max-width: 900px;
        width: 100%;
        background: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        overflow: hidden;
      }
      
      .login-illustration {
        flex: 1;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: white;
        text-align: center;
      }
      
      .login-illustration img {
        max-width: 380px;
        margin-bottom: 30px;
      }
      
      .login-illustration h2 {
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 1.5rem;
      }
      
      .login-illustration p {
        opacity: 0.9;
        font-size: 0.9rem;
        line-height: 1.5;
      }
      
      .login-card {
        flex: 1;
        padding: 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }
      
      .login-logo {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
      }
      
      .login-logo img {
        height: 100px;
      }
      
      .login-title {
        font-weight: 700;
        font-size: 1.8rem;
        text-align: center;
        margin-bottom: 8px;
        color: var(--text-color);
      }
      
      .login-subtitle {
        text-align: center;
        color: #64748b;
        margin-bottom: 40px;
        font-size: 0.95rem;
      }
      
      .form-group {
        margin-bottom: 25px;
        position: relative;
      }
      
      .form-control {
        padding: 12px 16px 12px 44px;
        height: 48px;
        border-radius: var(--border-radius);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        font-size: 0.95rem;
      }
      
      .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
      }
      
      .form-group .fa {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1rem;
      }
      
      .login-btn {
        width: 100%;
        height: 48px;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 1rem;
        background: var(--primary-color);
        border: none;
        transition: all 0.3s ease;
        margin-top: 10px;
      }
      
      .login-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
      }
      
      .error-msg {
        color: #fff;
        background: #ef4444;
        border-radius: var(--border-radius);
        padding: 12px 0;
        text-align: center;
        margin-bottom: 25px;
        font-weight: 500;
        font-size: 0.9rem;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
      }
      
      .forgot-password {
        text-align: right;
        margin-top: -15px;
        margin-bottom: 20px;
      }
      
      .forgot-password a {
        color: #64748b;
        font-size: 0.85rem;
        text-decoration: none;
        transition: color 0.2s;
      }
      
      .forgot-password a:hover {
        color: var(--primary-color);
      }
      
      @media (max-width: 768px) {
        .login-container {
          flex-direction: column;
        }
        
        .login-illustration {
          display: none;
        }
        
        .login-card {
          padding: 40px 30px;
        }
      }
      
      @media (max-width: 480px) {
        .login-card {
          padding: 30px 20px;
        }
        
        .login-title {
          font-size: 1.5rem;
        }
      }
      /* Copyright footer styles */
      .login-footer {
        width: 100%;
        text-align: center;
        color: #64748b;
        font-size: 0.95rem;
        margin-top: 30px;
        position: fixed;
        left: 0;
        bottom: 10px;
        z-index: 10;
        background: transparent;
        pointer-events: none;
      }
    </style>
</head>
<body>
  <script>
    // Dynamically set a random background image for the login page
    (function() {
      var images = [
        'assets/img/woss-bg1.jpg',
        'assets/img/woss-bg2.jpg',
        'assets/img/woss-bg3.jpg',
        'assets/img/woss-bg4.jpg'
      ];
      var randomImage = images[Math.floor(Math.random() * images.length)];
      document.body.style.backgroundImage =
        "radial-gradient(circle at 85% 15%, rgba(67, 97, 238, 0.08) 0%, rgba(255, 255, 255, 0) 50%), url('" + randomImage + "')";
      document.body.style.backgroundSize = 'cover';
      document.body.style.backgroundRepeat = 'no-repeat';
      document.body.style.backgroundPosition = 'center';
    })();
  </script>
  <div class="login-container">
    <div class="login-illustration">
      <img src="assets/img/app-logo.png" alt="WOSS Logo">
      <h2>Welcome Back</h2>
      <p>Access your SubseaOps account to manage your trips and bonuses.</p>
    </div>
    <div class="login-card">
      <!-- <div class="login-logo">
        <img src="assets/img/logo_Outlined.png" alt="WOSS Logo">
      </div> -->
      <div class="login-title">Sign In</div>
      <div class="login-subtitle">Enter your credentials to access your account</div>
      
      <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      
      <form method="POST" action="controllers/loginController.php" autocomplete="off">
        <div class="form-group">
          <i class="fa fa-user"></i>
          <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus />
        </div>
        <div class="form-group">
          <i class="fa fa-lock"></i>
          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required />
        </div>
        <div class="form-group" style="display: flex; align-items: center; margin-bottom: 10px;">
          <input type="checkbox" id="remember" name="remember" style="margin-right: 8px; width: 16px; height: 16px;">
          <label for="remember" style="margin: 0; font-size: 0.95rem; color: #64748b; cursor: pointer;">Remember Me</label>
        </div>
        <!-- <div class="forgot-password">
          <a href="#">Forgot password?</a>
        </div> -->
        <button type="submit" class="btn btn-primary login-btn">Sign In</button>
      </form>
    </div>
  </div>
  <div class="login-footer">
    <!-- &copy; <?php echo date("Y"); ?> WOSS Trip Bonus System. All rights reserved. -->
    SubseaOps - 2025 - Version v1.0
  </div>
</body>
</html>