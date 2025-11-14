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

// Validation 
function validateName($name) {
    return preg_match("/^[a-zA-Z ]{3,100}$/", $name);
}

function validatePhone($phone) {
    return preg_match("/^[0-9]{11}$/", $phone);
}

function validatePassword($password) {
    return strlen($password) >= 8 && 
           preg_match("/[A-Z]/", $password) && 
           preg_match("/[a-z]/", $password) && 
           preg_match("/[0-9]/", $password);
}

// input data fetching
if (isset($_POST['add'])) {
    try {
        $fullname = trim($_POST['full_name']);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        $confirmpassword = trim($_POST['confirmpassword']);
        $gender = trim($_POST['gender']);
        $dob = $_POST['dob'];
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $mobileno = trim($_POST['mobile_no']);
        $status = 'Active'; 
        
        // full name
        if (!validateName($fullname)) {
            throw new Exception("Full name should only contain letters and be between 3-100 characters");
        }

        // email 
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }

        // Check if email already exists
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Email already exists");
        }

        // phone number
        if (!validatePhone($mobileno)) {
            throw new Exception("Invalid phone number format. Must be 11 digits");
        }

        // Check for duplicate mobile number
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM users WHERE mobile_no = ?");
        $stmt->execute([$mobileno]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("This mobile number is already registered");
        }

        //password
        if (!validatePassword($password)) {
            throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        }

        // Check if passwords match
        if ($password !== $confirmpassword) {
            throw new Exception("Passwords do not match");
        }

        // date of birth Validation
        if (strtotime($dob) > strtotime('today')) {
            throw new Exception("Date of Birth cannot be in the future");
        }
        if (strtotime($dob) > strtotime('-18 years')) {
            throw new Exception("Employee must be at least 18 years old");
        }
        if (strtotime($dob) < strtotime('-100 years')) {
            throw new Exception("Please enter a valid Date of Birth");
        }

        // Address validation
        if (strlen($address) < 5) {
            throw new Exception("Address is too short. Minimum 5 characters required");
        }
        if (strlen($address) > 200) {
            throw new Exception("Address is too long. Maximum 200 characters allowed");
        }

        // City validation
        if (!preg_match("/^[a-zA-Z ]{2,50}$/", $city)) {
            throw new Exception("City name must contain only letters and be between 2-50 characters");
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert data
        $sql = "INSERT INTO users (full_name, email, mobile_no, gender, dob, address, city, status, password) 
                VALUES (:fullname, :email, :mobile, :gender, :dob, :address, :city, :status, :password)";
        
        $query = $dbh->prepare($sql);
        $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->bindParam(':mobile', $mobileno, PDO::PARAM_STR);
        $query->bindParam(':gender', $gender, PDO::PARAM_STR);
        $query->bindParam(':dob', $dob, PDO::PARAM_STR);
        $query->bindParam(':address', $address, PDO::PARAM_STR);
        $query->bindParam(':city', $city, PDO::PARAM_STR);
        $query->bindParam(':status', $status, PDO::PARAM_STR);
        $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);

        $query->execute();
        
        if ($query->rowCount() > 0) {
            $success = "Employee added successfully!";
        } else {
            throw new Exception("Something went wrong while adding employee");
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
  <title>Add Employee | TaskHub</title>
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

    /* Form */
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
      <div>Add Employee</div>
    </div>
    <div>
      <span class="material-icons">notifications</span>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <h4 class="mb-4 heading-colored"><span class="material-icons me-2" style="vertical-align: middle;">person_add</span> Add New Employee</h4>

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
      <h5 class="mb-4 heading-colored">Employee Information</h5>

      <div class="row g-3">
        <div class="col-md-6">
          <label for="full_name" class="form-label">Full Name</label>
          <input type="text" class="form-control" id="full_name" name="full_name" required autocomplete="off">
        </div>

        <div class="col-md-6">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email" required autocomplete="off">
        </div>

        <div class="col-md-6">
          <label for="mobile_no" class="form-label">Mobile Number</label>
          <input type="tel" class="form-control" id="mobile_no" name="mobile_no" required autocomplete="off" >
        </div>

        <div class="col-md-6">
          <label for="gender" class="form-label">Gender</label>
          <select class="form-select" id="gender" name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div class="col-md-6">
          <label for="dob" class="form-label">Date of Birth</label>
          <input type="date" class="form-control" id="dob" name="dob" required>
        </div>

        <div class="col-md-6">
          <label for="city" class="form-label">City</label>
          <input type="text" class="form-control" id="city" name="city" required autocomplete="off">
        </div>

        <div class="col-md-6">
          <label for="address" class="form-label">Address</label>
          <input type="text" class="form-control" id="address" name="address" required autocomplete="off">
        </div>

        <div class="col-md-6">
          <label for="password" class="form-label">Password</label>
          <div class="position-relative">
            <input type="password" class="form-control" id="password" name="password" required autocomplete="off">
            <span class="material-icons position-absolute top-50 end-0 translate-middle-y me-2 text-muted" 
                  style="cursor: pointer; font-size: 20px;" onclick="togglePassword('password')">visibility_off</span>
          </div>
          <small class="text-danger">Min. 8 characters with uppercase, lowercase & number</small>
        </div>

        <div class="col-md-6">
          <label for="confirmpassword" class="form-label">Confirm Password</label>
          <div class="position-relative">
            <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" required autocomplete="off">
            <span class="material-icons position-absolute top-50 end-0 translate-middle-y me-2 text-muted" 
                  style="cursor: pointer; font-size: 20px;" onclick="togglePassword('confirmpassword')">visibility_off</span>
          </div>
        </div>

        <div class="col-12 mt-4">
          <button type="submit" name="add" class="btn btn-custom w-100">
            <span class="material-icons" style="vertical-align: middle; font-size: 20px;">add</span>
            Add Employee
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
      const fullName = document.getElementById('full_name').value.trim();
      const email = document.getElementById('email').value.trim();
      const phone = document.getElementById('mobile_no').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmpassword').value;
      const dob = document.getElementById('dob').value;
      const address = document.getElementById('address').value.trim();
      const city = document.getElementById('city').value.trim();

      // Name validation
      const nameRegex = /^[a-zA-Z ]{3,100}$/;
      if (!nameRegex.test(fullName)) {
        alert("Full name should only contain letters and be between 3-100 characters");
        return false;
      }

      // Email validation
      const emailRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
      if (!emailRegex.test(email)) {
        alert("Please enter a valid email address");
        return false;
      }

      // Phone validation
      const phoneRegex = /^[0-9]{11}$/;
      if (!phoneRegex.test(phone)) {
        alert("Invalid phone number format. Must be 11 digits");
        return false;
      }

      // Password validation
      if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
        alert("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        return false;
      }

      // Password match
      if (password !== confirmPassword) {
        alert("Passwords do not match");
        return false;
      }

      // Date of Birth validation
      const dobDate = new Date(dob);
      const today = new Date();
      
      if (dobDate > today) {
        alert("Date of Birth cannot be in the future");
        return false;
      }

      let age = today.getFullYear() - dobDate.getFullYear();
      const monthDiff = today.getMonth() - dobDate.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
      }

      if (age < 18) {
        alert("Employee must be at least 18 years old");
        return false;
      }

      if (age > 100) {
        alert("Please enter a valid Date of Birth");
        return false;
      }

      // Address validation
      if (address.length < 5) {
        alert("Address is too short. Minimum 5 characters required");
        return false;
      }

      if (address.length > 200) {
        alert("Address is too long. Maximum 200 characters allowed");
        return false;
      }

      // City validation
      if (!nameRegex.test(city)) {
        alert("City name must contain only letters and be between 3-100 characters");
        return false;
      }

      return true;
    }

    // Toggle password visibility
    function togglePassword(inputId) {
      const input = document.getElementById(inputId);
      const icon = input.parentElement.querySelector('.material-icons');
      
      if (input.type === "password") {
        input.type = "text";
        icon.textContent = "visibility";
      } else {
        input.type = "password";
        icon.textContent = "visibility_off";
      }
    }
  </script>
</body>
</html>
