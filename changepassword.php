<?php
session_start();
include('include/config.php');

// employee login check
if (!isset($_SESSION['eid']) || empty($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';
$eid = $_SESSION['eid'];

//Count unread notifications
try {
    $unreadSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :userId AND is_read = 0";
    $unreadQuery = $dbh->prepare($unreadSql);
    $unreadQuery->bindParam(':userId', $eid, PDO::PARAM_INT);
    $unreadQuery->execute();
    $unreadResult = $unreadQuery->fetch(PDO::FETCH_ASSOC);
    $unreadCount = $unreadResult['unread_count'];
} catch(PDOException $e) {
    $unreadCount = 0;
}

// Handle form submission
if (isset($_POST['change'])) {
    try {
        // Get user input
        $currentPassword = trim($_POST['current_password']);
        $newPassword = trim($_POST['new_password']);
        $confirmPassword = trim($_POST['confirm_password']);

        // Validate fields are not empty
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All fields are required");
        }

        // Validate new password 
        if (strlen($newPassword) < 8) {
            throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        }

        
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        }

        
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New Password and Confirm Password do not match");
        }

        // Getcurrent password 
        $sql = "SELECT password FROM users WHERE id = :eid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':eid', $eid, PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception("Employee not found");
        }

        // Verify current password
        if (!password_verify($currentPassword, $result['password'])) {
            throw new Exception("Current password is incorrect");
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password 
        $sql = "UPDATE users SET password = :password WHERE id = :eid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $query->bindParam(':eid', $eid, PDO::PARAM_INT);

        if ($query->execute()) {
            $success = "Password changed successfully";
        } else {
            throw new Exception("Error updating password");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password | TaskHub</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f8f8ed;
      margin: 0;
    }

    /* Sidebar */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      width: 240px;
      background-color: #1f2a35;
      color: white;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 20px;
      overflow-y: auto;
      transition: transform .28s ease, width .28s ease;
      z-index: 1050;
    }

    .sidebar.collapsed {
      transform: translateX(-100%);
    }

    .sidebar h3 {
      font-weight: 600;
      margin: 12px 0 24px;
      color: #5bc0de;
      font-size: 20px;
    }

    .profile {
      text-align: center;
      margin-bottom: 8px;
    }

    .profile img {
      width: 78px;
      height: 78px;
      border-radius: 50%;
      margin-bottom: 8px;
    }

    .profile p {
      color: #ddd;
      font-size: 14px;
      margin: 0;
    }

    .nav-links {
      width: 100%;
      padding: 0 12px 24px;
    }

    .nav-links a.trigger {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #ccc;
      text-decoration: none;
      padding: 12px 12px;
      border-radius: 8px;
      transition: background .15s, color .15s;
      width: 100%;
    }

    .nav-links a.trigger:hover,
    .nav-links a.trigger.active {
      background-color: #2e3b48;
      color: #fff;
    }

    .nav-links .material-icons {
      font-size: 20px;
    }

    .nav-links .ms-auto {
      margin-left: auto;
      color: #c6d7df;
    }

    /* Submenu */
    .submenu a {
      display: block;
      color: #d8e6ea;
      text-decoration: none;
      padding: 10px 12px 10px 44px;
      background-color: #26323d;
      border-radius: 6px;
      margin-top: 6px;
      font-size: 14px;
    }
    .submenu a:hover { background-color: #2e3b48; color: #fff; }

    /* Top Bar */
    .topbar {
      position: fixed;
      top: 0;
      left: 240px;
      right: 0;
      height: 60px;
      background-color: #1f2a35;
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 18px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.08);
      transition: left .28s ease;
      z-index: 1040;
    }

    .topbar.collapsed {
      left: 0;
    }

    .topbar .brand {
      display:flex;
      align-items:center;
      gap:12px;
      color:#5bc0de;
      font-weight:600;
      font-size:18px;
    }

    .hamburger {
      background: none;
      border: none;
      color: #fff;
      font-size: 28px;
      display: inline-flex;
      align-items:center;
      justify-content:center;
      padding: 6px;
    }

    .notification-icon {
      position: relative;
      cursor: pointer;
      padding: 8px;
      border-radius: 50%;
      transition: background-color 0.2s;
    }

    .notification-icon:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }

    .notification-badge {
      position: absolute;
      top: 4px;
      right: 4px;
      background-color: #dc3545;
      color: white;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 10px;
      font-weight: 600;
      min-width: 18px;
      text-align: center;
    }

    /* Main Content */
    .main-content {
      margin-left: 240px;
      margin-top: 60px;
      padding: 32px;
      transition: margin-left .28s ease;
      min-height: calc(100vh - 60px);
    }

    .main-content.collapsed {
      margin-left: 0;
    }

    /* Form */
    .form-section {
      background: #ffffff;
      padding: 40px 30px;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      max-width: 600px;
      margin: 0 auto;
    }

    .form-label {
      font-weight: 500;
      margin-bottom: 6px;
      color: #333;
    }

    .form-control {
      border-radius: 10px;
      border: 1px solid #d9d9d9;
      padding: 10px 12px;
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    .form-control:focus {
      border-color: #5bc0de;
      box-shadow: 0 0 0 0.2rem rgba(91, 192, 222, 0.25);
    }

    .btn-custom {
      background-color: #5bc0de;
      color: white;
      border-radius: 10px;
      font-weight: 500;
      padding: 12px;
      transition: 0.3s ease;
      border: none;
    }

    .btn-custom:hover {
      background-color: #4aa8c4;
      color: white;
    }

    .heading-colored {
      color: #1f2a35;
      font-weight: 600;
    }

    .password-hint {
      font-size: 13px;
      color: #dc3545;
      margin-top: 5px;
    }

    @media (max-width: 640px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.visible { transform: translateX(0); }
      .topbar { left: 0; }
      .main-content { margin-left: 0; padding: 80px 18px 18px; }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <h3>TaskHub</h3>

    <div class="profile">
      <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
      <p><?php echo htmlspecialchars($_SESSION['ename']); ?></p>
    </div>

    <nav class="nav-links" id="navLinks">
      <a href="dashboard.php" class="trigger"><span class="material-icons">dashboard</span> Dashboard</a>

      <a href="mytasks.php" class="trigger">
        <span class="material-icons">task</span>
        <span>My Tasks</span>
      </a>

      <a href="profile.php" class="trigger">
        <span class="material-icons">person</span>
        <span>My Profile</span>
      </a>

      <a class="trigger active" href="changepassword.php">
        <span class="material-icons">lock</span>
        <span>Change Password</span>
      </a>

      <a class="trigger" href="logout.php"><span class="material-icons">logout</span> Logout</a>
    </nav>
  </aside>

  <!-- Top Bar -->
  <header class="topbar" id="topbar">
    <div class="brand">
      <button class="hamburger" id="hamburger" aria-label="Toggle sidebar">
        <span class="material-icons">menu</span>
      </button>
      <div>Change Password</div>
    </div>
    <div class="notification-icon" onclick="window.location.href='notifications.php'">
      <span class="material-icons">notifications</span>
      <?php if ($unreadCount > 0): ?>
      <span class="notification-badge"><?php echo $unreadCount; ?></span>
      <?php endif; ?>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <h4 class="mb-4 heading-colored text-center">
      <span class="material-icons me-2" style="vertical-align: middle;">lock</span> Change Password
    </h4>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert" style="max-width: 600px; margin: 0 auto 20px;">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert" style="max-width: 600px; margin: 0 auto 20px;">
       <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <form class="form-section" method="POST" id="changePasswordForm">
      <h5 class="mb-4 heading-colored">Update Your Password</h5>

      <div class="mb-3">
        <label for="current_password" class="form-label">Current Password</label>
        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="off">
      </div>

      <div class="mb-3">
        <label for="new_password" class="form-label">New Password</label>
        <input type="password" class="form-control" id="new_password" name="new_password" required autocomplete="off">
        <p class="password-hint">Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number</p>
      </div>

      <div class="mb-4">
        <label for="confirm_password" class="form-label">Confirm New Password</label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="off">
      </div>

      <div class="d-grid">
        <button type="submit" name="change" class="btn btn-custom">
          Change Password
        </button>
      </div>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const topbar = document.getElementById('topbar');
    const mainContent = document.getElementById('mainContent');
    const hamburger = document.getElementById('hamburger');

    function toggleSidebar() {
      const isHidden = sidebar.classList.toggle('collapsed');
      topbar.classList.toggle('collapsed', isHidden);
      mainContent.classList.toggle('collapsed', isHidden);
    }

    hamburger.addEventListener('click', () => {
      if (window.innerWidth <= 640) {
        sidebar.classList.toggle('visible');
      } else {
        toggleSidebar();
      }
    });

    // js validation
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Check if all fields are filled
        if (!currentPassword || !newPassword || !confirmPassword) {
            alert("All fields are required");
            e.preventDefault();
            return false;
        }

        // Check if passwords match
        if (newPassword !== confirmPassword) {
            alert("New Password and Confirm Password do not match");
            e.preventDefault();
            return false;
        }

        return true;
    });
  </script>
</body>
</html>
