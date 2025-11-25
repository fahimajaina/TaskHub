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

// Fetch pending tasks assigned to logged in employee
try {
    $sql = "SELECT t.*, u.full_name, u.email 
            FROM tasks t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE t.user_id = :userId
            AND t.status = 'Pending'
            ORDER BY t.due_date ASC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':userId', $userId, PDO::PARAM_INT);
    $query->execute();
    $tasks = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread notifications
    $unreadSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :userId AND is_read = 0";
    $unreadQuery = $dbh->prepare($unreadSql);
    $unreadQuery->bindParam(':userId', $userId, PDO::PARAM_INT);
    $unreadQuery->execute();
    $unreadResult = $unreadQuery->fetch(PDO::FETCH_ASSOC);
    $unreadCount = $unreadResult['unread_count'];
} catch(PDOException $e) {
    $error = "Error fetching tasks: " . $e->getMessage();
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

// Function for status badge class
function getStatusBadge($status) {
    switch($status) {
        case 'Completed':
            return 'bg-success';
        case 'In Progress':
            return 'bg-warning';
        case 'Pending':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

// Function to check overdue
function isOverdue($dueDate) {
    if (empty($dueDate)) {
        return false;
    }
    return strtotime($dueDate) < strtotime('today');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pending Tasks | TaskHub</title>
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

    .search-input {
      border-radius: 10px;
      border: 1px solid #d9d9d9;
      padding: 8px 16px;
    }

    .search-input:focus {
      border-color: #5bc0de;
      box-shadow: 0 0 0 0.2rem rgba(91, 192, 222, 0.25);
    }

    .table thead th {
      background-color: #2e3b48;
      color: #ffffff;
      font-weight: 600;
      border: none;
      padding: 12px;
    }

    .table tbody tr {
      transition: background-color 0.2s;
    }

    .table tbody tr:hover {
      background-color: #f8f8ed;
    }

    .table tbody tr.overdue {
      background-color: #ffe5e5;
    }

    .table tbody tr.overdue:hover {
      background-color: #ffd5d5;
    }

    .table td, .table th {
      vertical-align: middle;
      padding: 12px;
    }

    .heading-colored {
      color: #1f2a35;
      font-weight: 600;
    }

    .task-description {
      max-width: 300px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .alert-info-custom {
      background-color: #e2e3e5;
      border-color: #d6d8db;
      color: #383d41;
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 24px;
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
      <div>Pending Tasks</div>
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
      <span class="material-icons me-2" style="vertical-align: middle; color: #6c757d;">pending</span> Pending Tasks
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
        <h5 class="mb-0 heading-colored">Tasks Awaiting Action</h5>
        <input type="text" class="form-control search-input" style="max-width: 300px;" id="searchInput" placeholder="Search tasks...">
      </div>

      <div class="table-responsive">
        <table class="table align-middle text-center">
          <thead>
            <tr>
              <th>#</th>
              <th>Task Title</th>
              <th>Description</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!empty($tasks)): 
                  $cnt = 1;
                  foreach($tasks as $task):
                  $overdue = isOverdue($task['due_date']);
            ?>
            <tr <?php echo $overdue ? 'class="overdue"' : ''; ?>>
              <td><?php echo htmlspecialchars($cnt); ?></td>
              <td><?php echo htmlspecialchars($task['title']); ?></td>
              <td>
                <div class="task-description" title="<?php echo htmlspecialchars($task['description']); ?>">
                  <?php echo htmlspecialchars($task['description']); ?>
                </div>
              </td>
              <td>
                <?php 
                  if (!empty($task['due_date'])) {
                    echo htmlspecialchars(date('Y-m-d', strtotime($task['due_date'])));
                    if ($overdue) {
                      echo '<br><span class="badge bg-danger">Overdue</span>';
                    }
                  } else {
                    echo '<span class="text-muted">No deadline</span>';
                  }
                ?>
              </td>
              <td>
                <span class="badge <?php echo getStatusBadge($task['status']); ?>">
                  <?php echo htmlspecialchars($task['status']); ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($task['created_at']))); ?></td>
            </tr>

            <?php 
                  $cnt++;
                  endforeach;
                else: 
            ?>
            <tr>
              <td colspan="6" class="text-center">
                <div style="padding: 40px 0;">
                  <span class="material-icons" style="font-size: 60px; color: #28a745;">task_alt</span>
                  <p class="mt-3 mb-0" style="font-size: 18px; font-weight: 600; color: #1f2a35;">No pending tasks</p>
                  <p class="text-muted">All tasks are either in progress or completed!</p>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <p class="text-muted mt-3">
        Showing <?php echo !empty($tasks) ? '1 to ' . count($tasks) . ' of ' . count($tasks) : '0'; ?> entries
      </p>
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

    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
      const searchTerm = this.value.toLowerCase();
      const tableRows = document.querySelectorAll('table tbody tr');
      
      tableRows.forEach(row => {
          const title = row.cells[1]?.textContent || '';
          const description = row.cells[2]?.textContent || '';
          const status = row.cells[4]?.textContent || '';
          
          const matchesSearch = title.toLowerCase().includes(searchTerm) ||
                              description.toLowerCase().includes(searchTerm) ||
                              status.toLowerCase().includes(searchTerm);
          
          row.style.display = matchesSearch ? '' : 'none';
      });
    });
  </script>
</body>
</html>