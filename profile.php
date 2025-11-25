<?php
session_start();
require_once('include/config.php');

// employee login check
if (!isset($_SESSION['elogin'])) {
    header('location: index.php');
    exit();
}

$userId = $_SESSION['eid'];
$error = '';
$success = '';

// Count unread notifications
try {
    $unreadSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :userId AND is_read = 0";
    $unreadQuery = $dbh->prepare($unreadSql);
    $unreadQuery->bindParam(':userId', $userId, PDO::PARAM_INT);
    $unreadQuery->execute();
    $unreadResult = $unreadQuery->fetch(PDO::FETCH_ASSOC);
    $unreadCount = $unreadResult['unread_count'];
} catch(PDOException $e) {
    $unreadCount = 0;
}

// Fetch employee data
try {
    $sql = "SELECT * FROM users WHERE id = :userId";
    $query = $dbh->prepare($sql);
    $query->bindParam(':userId', $userId, PDO::PARAM_INT);
    $query->execute();
    $employee = $query->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        session_destroy();
        header('location: index.php');
        exit();
    }
} catch (PDOException $e) {
    $error = 'Database Error: ' . $e->getMessage();
}

// Get messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile | TaskHub</title>
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

    /* Profile Card */
    .profile-section {
      max-width: 1200px;
    }

    .info-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .info-card {
      background: #ffffff;
      padding: 25px;
      border-radius: 16px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .info-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.12);
    }

    .info-card-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      color: #5bc0de;
    }

    .info-card-header .material-icons {
      font-size: 24px;
    }

    .info-card-label {
      font-weight: 600;
      color: #666;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .info-card-value {
      color: #1f2a35;
      font-size: 18px;
      font-weight: 500;
      margin-top: 8px;
      word-break: break-word;
    }

    .account-section {
      background: #ffffff;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #1f2a35;
      font-weight: 600;
      font-size: 20px;
      margin-bottom: 20px;
    }

    .section-title .material-icons {
      color: #5bc0de;
      font-size: 26px;
    }

    .account-info-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .account-info-row:last-child {
      border-bottom: none;
    }

    .account-label {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #666;
      font-weight: 500;
    }

    .account-label .material-icons {
      font-size: 20px;
      color: #5bc0de;
    }

    .account-value {
      color: #1f2a35;
      font-weight: 500;
    }

    .btn-custom {
      background-color: #5bc0de;
      color: white;
      border-radius: 10px;
      font-weight: 500;
      padding: 12px 30px;
      transition: 0.3s ease;
      border: none;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-custom:hover {
      background-color: #4aa8c4;
      color: white;
    }

    .heading-colored {
      color: #1f2a35;
      font-weight: 600;
    }

    .status-badge {
      padding: 8px 16px;
      border-radius: 25px;
      font-size: 14px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .status-badge .material-icons {
      font-size: 16px;
    }

    .status-active {
      background-color: #d4edda;
      color: #155724;
    }

    .status-inactive {
      background-color: #f8d7da;
      color: #721c24;
    }

    @media (max-width: 768px) {
      .info-cards-grid {
        grid-template-columns: 1fr;
      }
      .account-info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
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

      <a href="profile.php" class="trigger active">
        <span class="material-icons">person</span>
        <span>My Profile</span>
      </a>

      <a class="trigger" href="changepassword.php">
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
      <div>My Profile</div>
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
    <h4 class="mb-4 heading-colored">
      <span class="material-icons me-2" style="vertical-align: middle;">person</span> My Profile
    </h4>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="profile-section">
      <!-- Personal Information -->
      <div class="info-cards-grid">
        <div class="info-card">
          <div class="info-card-header">
            <span class="material-icons">person</span>
            <span class="info-card-label">Full Name</span>
          </div>
          <div class="info-card-value"><?php echo htmlspecialchars($employee['full_name']); ?></div>
        </div>

        <div class="info-card">
          <div class="info-card-header">
            <span class="material-icons">email</span>
            <span class="info-card-label">Email Address</span>
          </div>
          <div class="info-card-value"><?php echo htmlspecialchars($employee['email']); ?></div>
        </div>

        <div class="info-card">
          <div class="info-card-header">
            <span class="material-icons">phone</span>
            <span class="info-card-label">Mobile Number</span>
          </div>
          <div class="info-card-value"><?php echo htmlspecialchars($employee['mobile_no']); ?></div>
        </div>

        <div class="info-card">
          <div class="info-card-header">
            <span class="material-icons">wc</span>
            <span class="info-card-label">Gender</span>
          </div>
          <div class="info-card-value"><?php echo htmlspecialchars($employee['gender']); ?></div>
        </div>

        <div class="info-card">
          <div class="info-card-header">
            <span class="material-icons">cake</span>
            <span class="info-card-label">Date of Birth</span>
          </div>
          <div class="info-card-value"><?php echo htmlspecialchars(date('F d, Y', strtotime($employee['dob']))); ?></div>
        </div>

        <div class="info-card">
          <div class="info-card-header">
            <span class="material-icons">location_city</span>
            <span class="info-card-label">City</span>
          </div>
          <div class="info-card-value"><?php echo htmlspecialchars($employee['city']); ?></div>
        </div>

        <div class="info-card">
          <div class="info-card-header">
            <span class="material-icons">home</span>
            <span class="info-card-label">Address</span>
          </div>
          <div class="info-card-value"><?php echo htmlspecialchars($employee['address']); ?></div>
        </div>
      </div>

      <!-- Account Information -->
      <div class="account-section">
        <div class="section-title">
          <span class="material-icons">info</span>
          Account Information
        </div>

        <div class="account-info-row">
          <div class="account-label">
            <span class="material-icons"><?php echo ($employee['status'] === 'Active') ? 'check_circle' : 'cancel'; ?></span>
            Account Status
          </div>
          <div class="account-value">
            <span class="status-badge <?php echo ($employee['status'] === 'Active') ? 'status-active' : 'status-inactive'; ?>">
              <span class="material-icons"><?php echo ($employee['status'] === 'Active') ? 'check_circle' : 'cancel'; ?></span>
              <?php echo htmlspecialchars($employee['status']); ?>
            </span>
          </div>
        </div>

        <div class="account-info-row">
          <div class="account-label">
            <span class="material-icons">event</span>
            Registration Date
          </div>
          <div class="account-value"><?php echo htmlspecialchars(date('F d, Y', strtotime($employee['regdate']))); ?></div>
        </div>
        </div>
      </div>

      <!-- Edit Button -->
        <a href="editinfo.php" class="btn btn-custom">
          <span class="material-icons" style="font-size: 18px;">edit</span>
          Edit Profile
        </a>
    </div>
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
  </script>
</body>
</html>
