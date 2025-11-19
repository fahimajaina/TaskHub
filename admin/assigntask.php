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

// Validation functions
function validateTitle($title) {
    return strlen($title) >= 3 && strlen($title) <= 200;
}

function validateDescription($description) {
    return strlen($description) >= 10 && strlen($description) <= 1000;
}

// Handle form submission
if (isset($_POST['assign'])) {
    try {
        $userId = intval($_POST['user_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $dueDate = trim($_POST['due_date']);
        $status = 'Pending'; // Default status for new tasks

        
        if (!validateTitle($title)) {
            throw new Exception("Title must be between 3 and 200 characters");
        }

        
        if (!validateDescription($description)) {
            throw new Exception("Description must be between 10 and 1000 characters");
        }

        // Validate user 
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND status = 'Active'");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Selected employee does not exist or is inactive");
        }

        // Validate due date 
        if (!empty($dueDate)) {
            if (strtotime($dueDate) < strtotime('today')) {
                throw new Exception("Due date cannot be in the past");
            }
        } else {
            $dueDate = null; // null for no deadline
        }

        // Insert 
        $sql = "INSERT INTO tasks (user_id, title, description, due_date, status) 
                VALUES (:user_id, :title, :description, :due_date, :status)";
        
        $query = $dbh->prepare($sql);
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->bindParam(':title', $title, PDO::PARAM_STR);
        $query->bindParam(':description', $description, PDO::PARAM_STR);
        $query->bindParam(':due_date', $dueDate, PDO::PARAM_STR);
        $query->bindParam(':status', $status, PDO::PARAM_STR);

        $query->execute();
        
        if ($query->rowCount() > 0) {
            $success = "Task assigned successfully!";
            
            //  Notification create for user
            $notifSql = "INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)";
            $notifQuery = $dbh->prepare($notifSql);
            $message = "New task assigned: " . $title;
            $notifQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $notifQuery->bindParam(':message', $message, PDO::PARAM_STR);
            $notifQuery->execute();
        } else {
            throw new Exception("Something went wrong while assigning task");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch active employees 
try {
    $stmt = $dbh->query("SELECT id, full_name, email FROM users WHERE status = 'Active' ORDER BY full_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching employees: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assign Task | TaskHub</title>
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

    /* Form Styles */
    .form-section {
      background: #ffffff;
      padding: 40px 30px;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      max-width: 1000px;
    }

    .form-label {
      font-weight: 500;
      margin-bottom: 6px;
      color: #333;
    }

    .form-control,
    .form-select {
      border-radius: 10px;
      border: 1px solid #d9d9d9;
      padding: 10px 12px;
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: #5bc0de;
      box-shadow: 0 0 0 0.2rem rgba(91, 192, 222, 0.25);
    }

    textarea.form-control {
      min-height: 120px;
      resize: vertical;
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
      <a class="trigger active" href="assigntask.php">
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
      <div>Assign Task</div>
    </div>
    <div>
      <span class="material-icons">notifications</span>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <h4 class="mb-4 heading-colored">
      <span class="material-icons me-2" style="vertical-align: middle;">assignment</span> Assign New Task
    </h4>

    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <form class="form-section" method="POST" onsubmit="return validateForm();">
      <h5 class="mb-4 heading-colored">Task Information</h5>

      <div class="row g-3">
        <div class="col-md-6">
          <label for="user_id" class="form-label">Assign To</label>
          <select class="form-select" id="user_id" name="user_id" required>
            <option value="">Select Employee</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo htmlspecialchars($emp['id']); ?>">
                <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['email']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label for="due_date" class="form-label">Due Date (Optional)</label>
          <input type="date" class="form-control" id="due_date" name="due_date" autocomplete="off">
          <small class="text-muted">Leave empty for no deadline</small>
        </div>

        <div class="col-12">
          <label for="title" class="form-label">Task Title</label>
          <input type="text" class="form-control" id="title" name="title" required autocomplete="off" placeholder="Enter task title">
          <small class="text-muted">Minimum 3 characters, maximum 200 characters</small>
        </div>

        <div class="col-12">
          <label for="description" class="form-label">Task Description </label>
          <textarea class="form-control" id="description" name="description" required autocomplete="off" placeholder="Enter detailed task description"></textarea>
          <small class="text-muted">Minimum 10 characters, maximum 1000 characters</small>
        </div>

        <div class="col-12 mt-4">
          <button type="submit" name="assign" class="btn btn-custom w-100">
            <span class="material-icons" style="vertical-align: middle; font-size: 20px;">add_task</span>
            Assign Task
          </button>
        </div>
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

    // Form validation
    function validateForm() {
      const userId = document.getElementById('user_id').value;
      const title = document.getElementById('title').value.trim();
      const description = document.getElementById('description').value.trim();
      const dueDate = document.getElementById('due_date').value;

      // User validation
      if (!userId) {
        alert("Please select an employee to assign the task");
        return false;
      }

      // Title validation
      if (title.length < 3) {
        alert("Task title must be at least 3 characters long");
        return false;
      }
      if (title.length > 200) {
        alert("Task title must not exceed 200 characters");
        return false;
      }

      // Description validation
      if (description.length < 10) {
        alert("Task description must be at least 10 characters long");
        return false;
      }
      if (description.length > 1000) {
        alert("Task description must not exceed 1000 characters");
        return false;
      }

      // Due date validation 
      if (dueDate) {
        const dueDateObj = new Date(dueDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (dueDateObj < today) {
          alert("Due date cannot be in the past");
          return false;
        }
      }

      return true;
    }
  </script>
</body>
</html>
