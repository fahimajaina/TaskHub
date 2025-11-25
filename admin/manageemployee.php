<?php
session_start();
require_once('include/config.php');

// admin login check
if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

$error = '';
$success = '';

// Handle employee status change 
if (isset($_POST['toggle_status']) && !empty($_POST['userId'])) {
    $userId = intval($_POST['userId']);
    try {
        // Get current status
        $checkSql = "SELECT status FROM users WHERE id = :userId";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $checkStmt->execute();
        $currentStatus = $checkStmt->fetchColumn();
        
        // Toggle status
        $newStatus = ($currentStatus === 'Active') ? 'Inactive' : 'Active';
        
        $sql = "UPDATE users SET status = :newStatus WHERE id = :userId";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':newStatus', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Employee status updated successfully";
        } else {
            $_SESSION['error'] = "Error updating employee status";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: manageemployee.php");
    exit();
}

// Handle employee deletion
if (isset($_POST['delete_employee']) && !empty($_POST['userId'])) {
    $userId = intval($_POST['userId']);
    try {
        $sql = "DELETE FROM users WHERE id = :userId";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Employee deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting employee";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: manageemployee.php");
    exit();
}

// Fetch all employees
try {
    $sql = "SELECT * FROM users ORDER BY id DESC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $employees = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching employees: " . $e->getMessage();
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
  <title>Manage Employees | TaskHub</title>
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

    .table td, .table th {
      vertical-align: middle;
      padding: 12px;
    }

    .btn-action {
      border-radius: 8px;
      padding: 6px 14px;
      font-size: 14px;
      font-weight: 500;
      border: none;
      transition: all 0.3s;
    }

    .btn-edit {
      background-color: #5bc0de;
      color: white;
    }

    .btn-edit:hover {
      background-color: #4aa8c4;
      color: white;
    }

    .btn-toggle {
      background-color: #ffc107;
      color: #1f2a35;
    }

    .btn-toggle:hover {
      background-color: #e0a800;
    }

    .btn-delete {
      background-color: #dc3545;
      color: white;
    }

    .btn-delete:hover {
      background-color: #c82333;
    }

    .heading-colored {
      color: #1f2a35;
      font-weight: 600;
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
      <p>Admin</p>
    </div>

    <nav class="nav-links" id="navLinks">
      <a href="dashboard.php" class="trigger"><span class="material-icons">dashboard</span> Dashboard</a>

      <!-- Manage Employees -->
      <a class="trigger active" data-bs-toggle="collapse" href="#employeeMenu" role="button" aria-expanded="true" aria-controls="employeeMenu">
        <span class="material-icons">group</span>
        <span>Manage Employees</span>
        <span class="ms-auto material-icons">chevron_right</span>
      </a>
      <div class="collapse show submenu" id="employeeMenu">
        <a href="addemployee.php">Add Employee</a>
        <a href="manageemployee.php">Manage Employees</a>
      </div>

      <!-- Assign Task -->
      <a class="trigger" href="assigntask.php">
        <span class="material-icons">assignment</span>
        <span>Assign Task</span>
      </a>

      <!-- All Tasks-->
      <a class="trigger" data-bs-toggle="collapse" href="#allTasksMenu" role="button" aria-expanded="false" aria-controls="allTasksMenu">
        <span class="material-icons">list</span>
        <span>All Tasks</span>
        <span class="ms-auto material-icons">chevron_right</span>
      </a>
      <div class="collapse submenu" id="allTasksMenu">
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
      <div>Manage Employees</div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <h4 class="mb-4 heading-colored">
      <span class="material-icons me-2" style="vertical-align: middle;">people</span> Manage Employees
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
        <h5 class="mb-0 heading-colored">Employees List</h5>
        <input type="text" class="form-control search-input" style="max-width: 300px;" id="searchInput" placeholder="Search employees...">
      </div>

      <div class="table-responsive">
        <table class="table align-middle text-center">
          <thead>
            <tr>
              <th>#</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Mobile</th>
              <th>Gender</th>
              <th>City</th>
              <th>Status</th>
              <th>Reg Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!empty($employees)): 
                  $cnt = 1;
                  foreach($employees as $emp):
            ?>
            <tr>
              <td><?php echo htmlspecialchars($cnt); ?></td>
              <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
              <td><?php echo htmlspecialchars($emp['email']); ?></td>
              <td><?php echo htmlspecialchars($emp['mobile_no']); ?></td>
              <td><?php echo htmlspecialchars($emp['gender']); ?></td>
              <td><?php echo htmlspecialchars($emp['city']); ?></td>
              <td>
                <span class="badge <?php echo $emp['status'] === 'Active' ? 'bg-success' : 'bg-danger'; ?>">
                  <?php echo htmlspecialchars($emp['status']); ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($emp['regdate']))); ?></td>
              <td>
                <a href="editemployee.php?id=<?php echo htmlspecialchars($emp['id']); ?>" class="btn btn-edit btn-action me-1">
                  <span class="material-icons" style="font-size: 16px; vertical-align: middle;">edit</span>
                </a>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="userId" value="<?php echo htmlspecialchars($emp['id']); ?>">
                  <button type="submit" name="toggle_status" 
                          class="btn btn-toggle btn-action me-1"
                          onclick="return confirm('Are you sure you want to <?php echo $emp['status'] === 'Active' ? 'deactivate' : 'activate'; ?> this employee?');">
                    <?php echo $emp['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                  </button>
                </form>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="userId" value="<?php echo htmlspecialchars($emp['id']); ?>">
                  <button type="submit" name="delete_employee" 
                          class="btn btn-delete btn-action"
                          onclick="return confirm('Are you sure you want to delete this employee? This action cannot be undone.');">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">delete</span>
                  </button>
                </form>
              </td>
            </tr>
            <?php 
                  $cnt++;
                  endforeach;
                else: 
            ?>
            <tr>
              <td colspan="9" class="text-center">No employees found</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <p class="text-muted mt-3">
        Showing <?php echo !empty($employees) ? '1 to ' . count($employees) . ' of ' . count($employees) : '0'; ?> entries
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
          const fullName = row.cells[1]?.textContent || '';
          const email = row.cells[2]?.textContent || '';
          const mobile = row.cells[3]?.textContent || '';
          const city = row.cells[5]?.textContent || '';
          
          const matchesSearch = fullName.toLowerCase().includes(searchTerm) ||
                              email.toLowerCase().includes(searchTerm) ||
                              mobile.toLowerCase().includes(searchTerm) ||
                              city.toLowerCase().includes(searchTerm);
          
          row.style.display = matchesSearch ? '' : 'none';
      });
    });
  </script>
</body>
</html>
