<?php
session_start();
require_once('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['elogin'])) {
    header('location: index.php');
    exit();
}

$userId = $_SESSION['eid'];
$error = '';
$success = '';

// Mark notification as read
if (isset($_POST['mark_read']) && !empty($_POST['notificationId'])) {
    $notificationId = intval($_POST['notificationId']);
    
    try {
        // Verify notification belongs to logged-in employee
        $checkSql = "SELECT id FROM notifications WHERE id = :notificationId AND user_id = :userId";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
        $checkStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = :notificationId";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Notification marked as read";
            } else {
                $_SESSION['error'] = "Error updating notification";
            }
        } else {
            $_SESSION['error'] = "Unauthorized access to notification";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: notifications.php");
    exit();
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    try {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :userId AND is_read = 0";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "All notifications marked as read";
        } else {
            $_SESSION['error'] = "Error updating notifications";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: notifications.php");
    exit();
}

// Delete notification
if (isset($_POST['delete_notification']) && !empty($_POST['notificationId'])) {
    $notificationId = intval($_POST['notificationId']);
    
    try {
        // Verify notification belongs to logged-in employee
        $checkSql = "SELECT id FROM notifications WHERE id = :notificationId AND user_id = :userId";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
        $checkStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $sql = "DELETE FROM notifications WHERE id = :notificationId";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Notification deleted successfully";
            } else {
                $_SESSION['error'] = "Error deleting notification";
            }
        } else {
            $_SESSION['error'] = "Unauthorized access to notification";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: notifications.php");
    exit();
}

// Fetch notifications for logged-in employee
try {
    $sql = "SELECT * FROM notifications WHERE user_id = :userId ORDER BY created_at DESC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':userId', $userId, PDO::PARAM_INT);
    $query->execute();
    $notifications = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread notifications
    $unreadSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :userId AND is_read = 0";
    $unreadQuery = $dbh->prepare($unreadSql);
    $unreadQuery->bindParam(':userId', $userId, PDO::PARAM_INT);
    $unreadQuery->execute();
    $unreadResult = $unreadQuery->fetch(PDO::FETCH_ASSOC);
    $unreadCount = $unreadResult['unread_count'];
} catch(PDOException $e) {
    $error = "Error fetching notifications: " . $e->getMessage();
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

// Function to get time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notifications | TaskHub</title>
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

    /* Card Styles */
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      background: #ffffff;
    }

    .card h4 {
      color: #1f2a35;
      font-weight: 600;
    }

    .heading-colored {
      color: #1f2a35;
      font-weight: 600;
    }

    .notification-item {
      padding: 16px;
      border-bottom: 1px solid #e9ecef;
      transition: background-color 0.2s;
      display: flex;
      align-items: start;
      gap: 16px;
    }

    .notification-item:last-child {
      border-bottom: none;
    }

    .notification-item:hover {
      background-color: #f8f8ed;
    }

    .notification-item.unread {
      background-color: #e7f6fd;
    }

    .notification-item.unread:hover {
      background-color: #d4f1fc;
    }

    .notification-icon-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #5bc0de;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .notification-icon-circle .material-icons {
      color: white;
      font-size: 20px;
    }

    .notification-content {
      flex-grow: 1;
    }

    .notification-message {
      margin: 0 0 8px 0;
      color: #1f2a35;
      font-size: 14px;
    }

    .notification-time {
      font-size: 12px;
      color: #6c757d;
    }

    .notification-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }

    .btn-action {
      border: none;
      border-radius: 8px;
      padding: 6px 12px;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-mark-read {
      background-color: #28a745;
      color: white;
    }

    .btn-mark-read:hover {
      background-color: #218838;
      color: white;
    }

    .btn-delete {
      background-color: #dc3545;
      color: white;
    }

    .btn-delete:hover {
      background-color: #c82333;
      color: white;
    }

    .btn-mark-all {
      background-color: #5bc0de;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      transition: all 0.2s;
    }

    .btn-mark-all:hover {
      background-color: #46b8da;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }

    .empty-state .material-icons {
      font-size: 80px;
      color: #dee2e6;
      margin-bottom: 16px;
    }

    @media (max-width: 640px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.visible { transform: translateX(0); }
      .topbar { left: 0; }
      .main-content { margin-left: 0; padding: 80px 18px 18px; }
      
      .notification-actions {
        flex-direction: column;
      }
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
      <div>Notifications</div>
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
      <span class="material-icons me-2" style="vertical-align: middle;">notifications</span> Notifications
    </h4>

    <div class="card p-4">
      <?php if($error): ?>
      <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
          <?php echo htmlspecialchars($error); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <?php if($success): ?>
      <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
          <?php echo htmlspecialchars($success); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 heading-colored">Your Messages</h5>
        <?php if ($unreadCount > 0): ?>
        <form method="POST" style="display: inline;">
          <button type="submit" name="mark_all_read" class="btn btn-mark-all">
            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">done_all</span>
            Mark All as Read
          </button>
        </form>
        <?php endif; ?>
      </div>

      <?php if(!empty($notifications)): ?>
        <?php foreach($notifications as $notification): ?>
        <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
          <div class="notification-icon-circle">
            <span class="material-icons">mail</span>
          </div>
          <div class="notification-content">
            <p class="notification-message">
              <?php echo htmlspecialchars($notification['message']); ?>
              <?php if ($notification['is_read'] == 0): ?>
              <span class="badge bg-primary ms-2">New</span>
              <?php endif; ?>
            </p>
            <p class="notification-time">
              <span class="material-icons" style="font-size: 14px; vertical-align: middle;">schedule</span>
              <?php echo timeAgo($notification['created_at']); ?>
            </p>
          </div>
          <div class="notification-actions">
            <?php if ($notification['is_read'] == 0): ?>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="notificationId" value="<?php echo htmlspecialchars($notification['id']); ?>">
              <button type="submit" name="mark_read" class="btn btn-mark-read btn-action" title="Mark as read">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">done</span>
              </button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="notificationId" value="<?php echo htmlspecialchars($notification['id']); ?>">
              <button type="submit" name="delete_notification" 
                      class="btn btn-delete btn-action"
                      onclick="return confirm('Are you sure you want to delete this notification?');"
                      title="Delete">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">delete</span>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>

        <p class="text-muted mt-3 mb-0">
          Showing <?php echo count($notifications); ?> notification<?php echo count($notifications) != 1 ? 's' : ''; ?>
          <?php if ($unreadCount > 0): ?>
          <span class="ms-2">(<?php echo $unreadCount; ?> unread)</span>
          <?php endif; ?>
        </p>
      <?php else: ?>
        <div class="empty-state">
          <div class="material-icons">notifications_none</div>
          <h5 class="text-muted">No notifications yet</h5>
          <p class="text-muted">You'll see messages and updates here</p>
        </div>
      <?php endif; ?>
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
