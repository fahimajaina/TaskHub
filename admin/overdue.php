<?php
session_start();
require_once('include/config.php');

// Admin login check
if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

$error = '';
$success = '';

// Task status update
if (isset($_POST['update_status']) && !empty($_POST['taskId'])) {
    $taskId = intval($_POST['taskId']);
    $newStatus = trim($_POST['status']);
    
    try {
        $sql = "UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :taskId";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Task status updated successfully";
        } else {
            $_SESSION['error'] = "Error updating task status";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: overdue.php");
    exit();
}

// Delete task
if (isset($_POST['delete_task']) && !empty($_POST['taskId'])) {
    $taskId = intval($_POST['taskId']);
    
    try {
        $sql = "DELETE FROM tasks WHERE id = :taskId";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Task deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting task";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: overdue.php");
    exit();
}

// Fetch overdue tasks with user information
try {
    $sql = "SELECT t.*, u.full_name, u.email 
            FROM tasks t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE t.status != 'Completed'
            AND t.due_date < CURDATE()
            ORDER BY t.due_date ASC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $tasks = $query->fetchAll(PDO::FETCH_ASSOC);
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

// Function to get status badge class
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

// Function to calculate days overdue
function daysOverdue($dueDate) {
    $due = strtotime($dueDate);
    $today = strtotime('today');
    $diff = $today - $due;
    return floor($diff / (60 * 60 * 24));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Overdue Tasks | TaskHub Admin</title>
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

    .btn-action {
      border: none;
      border-radius: 8px;
      padding: 6px 12px;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-view {
      background-color: #5bc0de;
      color: white;
    }

    .btn-view:hover {
      background-color: #46b8da;
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

    .days-overdue {
      font-size: 12px;
      font-weight: 600;
      color: #dc3545;
    }

    @media (max-width: 640px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.visible { transform: translateX(0); }
      .topbar { left: 0; }
      .main-content { margin-left: 0; padding: 18px; }
    }
  </style>
</head>
<body>

 <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <h3>TaskHub</h3>

    <div class="profile">
      <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
      <p>Admin</p>
    </div>

    <nav class="nav-links" id="navLinks">
      <a href="dashboard.php" class="trigger"><span class="material-icons">dashboard</span> Dashboard</a>

      <!-- Manage Employees -->
      <a class="trigger" data-bs-toggle="collapse" href="#employeeMenu" role="button" aria-expanded="false" aria-controls="employeeMenu">
        <span class="material-icons">group</span>
        <span>Manage Employees</span>
        <span class="ms-auto material-icons">chevron_right</span>
      </a>
      <div class="collapse submenu" id="employeeMenu">
        <a href="addemployee.php">Add Employee</a>
        <a href="manageemployee.php">Manage Employees</a>
      </div>

      <!-- Assign Task -->
      <a class="trigger" href="assigntask.php">
        <span class="material-icons">assignment</span>
        <span>Assign Task</span>
      </a>

      <!-- All Tasks-->
      <a class="trigger active" data-bs-toggle="collapse" href="#allTasksMenu" role="button" aria-expanded="true" aria-controls="allTasksMenu">
        <span class="material-icons">list</span>
        <span>All Tasks</span>
        <span class="ms-auto material-icons">chevron_right</span>
      </a>
      <div class="collapse show submenu" id="allTasksMenu">
        <a href="tasks.php">All Tasks</a>
        <a href="pendingtasks.php">Pending Tasks</a>
        <a href="completedtasks.php">Completed Tasks</a>
        <a href="overdue.php">Overdue Tasks</a>
      </div>

      <a class="trigger" href="logout.php"><span class="material-icons">logout</span> Logout</a>
    </nav>
  </aside>

  <!-- Top Bar -->
  <header class="topbar" id="topbar">
    <div class="brand">
      <button class="hamburger" id="hamburger" aria-label="Toggle sidebar">
        <span class="material-icons">menu</span>
      </button>
      <div>Overdue Tasks</div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <h4 class="mb-4 heading-colored">
      <span class="material-icons me-2" style="vertical-align: middle; color: #dc3545;">cancel</span> Overdue Tasks
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
        <h5 class="mb-0 heading-colored">Tasks Past Due Date</h5>
        <input type="text" class="form-control search-input" style="max-width: 300px;" id="searchInput" placeholder="Search tasks...">
      </div>

      <div class="table-responsive">
        <table class="table align-middle text-center">
          <thead>
            <tr>
              <th>#</th>
              <th>Task Title</th>
              <th>Assigned To</th>
              <th>Description</th>
              <th>Due Date</th>
              <th>Days Overdue</th>
              <th>Status</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!empty($tasks)): 
                  $cnt = 1;
                  foreach($tasks as $task):
                  $daysOver = daysOverdue($task['due_date']);
            ?>
            <tr class="overdue">
              <td><?php echo htmlspecialchars($cnt); ?></td>
              <td><?php echo htmlspecialchars($task['title']); ?></td>
              <td>
                <?php echo htmlspecialchars($task['full_name']); ?><br>
                <small class="text-muted"><?php echo htmlspecialchars($task['email']); ?></small>
              </td>
              <td>
                <div class="task-description" title="<?php echo htmlspecialchars($task['description']); ?>">
                  <?php echo htmlspecialchars($task['description']); ?>
                </div>
              </td>
              <td>
                <?php echo htmlspecialchars(date('Y-m-d', strtotime($task['due_date']))); ?>
                <br><span class="badge bg-danger">Overdue</span>
              </td>
              <td>
                <span class="days-overdue"><?php echo $daysOver; ?> day<?php echo $daysOver != 1 ? 's' : ''; ?></span>
              </td>
              <td>
                <span class="badge <?php echo getStatusBadge($task['status']); ?>">
                  <?php echo htmlspecialchars($task['status']); ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($task['created_at']))); ?></td>
              <td>
                <button class="btn btn-view btn-action me-1" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $task['id']; ?>">
                  <span class="material-icons" style="font-size: 16px; vertical-align: middle;">edit</span>
                </button>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="taskId" value="<?php echo htmlspecialchars($task['id']); ?>">
                  <button type="submit" name="delete_task" 
                          class="btn btn-delete btn-action"
                          onclick="return confirm('Are you sure you want to delete this task?');">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">delete</span>
                  </button>
                </form>
              </td>
            </tr>

            <!-- Status Update Modal -->
            <div class="modal fade" id="statusModal<?php echo $task['id']; ?>" tabindex="-1" aria-labelledby="statusModalLabel<?php echo $task['id']; ?>" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel<?php echo $task['id']; ?>">Update Task Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <form method="POST">
                    <div class="modal-body">
                      <input type="hidden" name="taskId" value="<?php echo htmlspecialchars($task['id']); ?>">
                      <p><strong>Task:</strong> <?php echo htmlspecialchars($task['title']); ?></p>
                      <div class="mb-3">
                        <label for="status<?php echo $task['id']; ?>" class="form-label">Select New Status</label>
                        <select class="form-select" id="status<?php echo $task['id']; ?>" name="status" required>
                          <option value="Pending" <?php echo ($task['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                          <option value="In Progress" <?php echo ($task['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                          <option value="Completed" <?php echo ($task['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                        </select>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" name="update_status" class="btn btn-view">Update Status</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <?php 
                  $cnt++;
                  endforeach;
                else: 
            ?>
            <tr>
              <td colspan="9" class="text-center">
                <div style="padding: 40px 0;">
                  <span class="material-icons" style="font-size: 60px; color: #28a745;">check_circle</span>
                  <p class="mt-3 mb-0 text-success" style="font-size: 18px; font-weight: 600;">Great! No overdue tasks.</p>
                  <p class="text-muted">All tasks are on track!</p>
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

    // Submenu toggle
    function toggleSubmenu(event) {
      event.preventDefault();
      const submenu = document.getElementById('submenu');
      if (submenu.style.display === 'none' || submenu.style.display === '') {
        submenu.style.display = 'block';
      } else {
        submenu.style.display = 'none';
      }
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
      const searchTerm = this.value.toLowerCase();
      const tableRows = document.querySelectorAll('table tbody tr');
      
      tableRows.forEach(row => {
          const title = row.cells[1]?.textContent || '';
          const assignedTo = row.cells[2]?.textContent || '';
          const description = row.cells[3]?.textContent || '';
          const status = row.cells[6]?.textContent || '';
          
          const matchesSearch = title.toLowerCase().includes(searchTerm) ||
                              assignedTo.toLowerCase().includes(searchTerm) ||
                              description.toLowerCase().includes(searchTerm) ||
                              status.toLowerCase().includes(searchTerm);
          
          row.style.display = matchesSearch ? '' : 'none';
      });
    });
  </script>
</body>
</html>
