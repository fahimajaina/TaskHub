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

// Validations
function validateName($name) {
    return preg_match("/^[a-zA-Z ]{3,100}$/", $name);
}

function validatePhone($phone) {
    return preg_match("/^[0-9]{11}$/", $phone);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // input data
        $fullname = trim($_POST['full_name']);
        $mobileno = trim($_POST['mobile_no']);
        $gender = trim($_POST['gender']);
        $dob = trim($_POST['dob']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        
        // empty field Validation
        if (empty($fullname) || empty($mobileno) || empty($dob) || empty($address) || empty($city)) {
            $error = "All fields are required";
        } 
        // Full name validation
        elseif (!validateName($fullname)) {
            $error = "Full name should only contain letters and be between 3-100 characters";
        }
        // Phone validation
        elseif (!validatePhone($mobileno)) {
            $error = "Invalid phone number format. Must be 11 digits";
        }
        // duplicate mobile number
        
            $sql = "SELECT COUNT(*) FROM users WHERE mobile_no = :mobileno AND id != :userId";
            $query = $dbh->prepare($sql);
            $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
            $query->bindParam(':userId', $userId, PDO::PARAM_INT);
            $query->execute();
            if ($query->fetchColumn() > 0) {
                $error = "This mobile number is already registered with another account";
            }
        
        // Date of birth validation
        elseif (strtotime($dob) > strtotime('today')) {
            $error = "Date of Birth cannot be in the future";
        } elseif (strtotime($dob) > strtotime('-18 years')) {
            $error = "You must be at least 18 years old";
        } elseif (strtotime($dob) < strtotime('-100 years')) {
            $error = "Please enter a valid Date of Birth";
        }
        // Address validation
        elseif (strlen($address) < 5) {
            $error = "Address is too short. Minimum 5 characters required";
        } elseif (strlen($address) > 200) {
            $error = "Address is too long. Maximum 200 characters allowed";
        }
        // City validation
        elseif (!preg_match("/^[a-zA-Z ]{2,50}$/", $city)) {
            $error = "City name must contain only letters and be between 2-50 characters";
        } else {
            try {
                // Update employee information
                $sql = "UPDATE users SET full_name=:fullname, mobile_no=:mobileno, 
                        gender=:gender, dob=:dob, address=:address, city=:city 
                        WHERE id=:userId";
                
                $query = $dbh->prepare($sql);
                $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
                $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
                $query->bindParam(':gender', $gender, PDO::PARAM_STR);
                $query->bindParam(':dob', $dob, PDO::PARAM_STR);
                $query->bindParam(':address', $address, PDO::PARAM_STR);
                $query->bindParam(':city', $city, PDO::PARAM_STR);
                $query->bindParam(':userId', $userId, PDO::PARAM_INT);
                
                if ($query->execute()) {
                    // Update session name if changed
                    $_SESSION['ename'] = $fullname;
                    $_SESSION['success'] = 'Profile updated successfully';
                    header('location: profile.php');
                    exit();
                } else {
                    $error = 'Something went wrong. Please try again';
                }
            } catch (PDOException $e) {
                $error = 'Database Error: ' . $e->getMessage();
            }
        }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Profile | TaskHub</title>
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

    .form-control:disabled,
    .form-control[readonly] {
      background-color: #f8f8ed;
      color: #495057;
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

    .readonly-field {
      background-color: #f8f8ed;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #d9d9d9;
      color: #495057;
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
      <div>Edit Profile</div>
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
      <span class="material-icons me-2" style="vertical-align: middle;">edit</span> Update My Info
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

    <form class="form-section" method="POST" id="updateProfileForm">
      <h5 class="mb-4 heading-colored">Personal Information</h5>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Email (Cannot be changed)</label>
          <div class="readonly-field"><?php echo htmlspecialchars($employee['email']); ?></div>
        </div>

        <div class="col-md-6">
          <label for="full_name" class="form-label">Full Name</label>
          <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required autocomplete="off">
        </div>

        <div class="col-md-6">
          <label for="mobile_no" class="form-label">Mobile Number</label>
          <input type="tel" class="form-control" id="mobile_no" name="mobile_no" value="<?php echo htmlspecialchars($employee['mobile_no']); ?>" required autocomplete="off">
        </div>

        <div class="col-md-6">
          <label for="gender" class="form-label">Gender</label>
          <select class="form-select" id="gender" name="gender" required>
            <option value="Male" <?php echo ($employee['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo ($employee['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
            <option value="Other" <?php echo ($employee['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>

        <div class="col-md-6">
          <label for="dob" class="form-label">Date of Birth</label>
          <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($employee['dob']); ?>" required>
        </div>

        <div class="col-md-6">
          <label for="city" class="form-label">City</label>
          <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($employee['city']); ?>" required autocomplete="off">
        </div>

        <div class="col-md-6">
          <label for="address" class="form-label">Address</label>
          <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($employee['address']); ?>" required autocomplete="off">
        </div>

        <div class="col-12 mt-4">
          <button type="submit" name="update" class="btn btn-custom w-100">
            Update Profile
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

    // js validation
    document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
        const fullName = document.getElementById('full_name').value.trim();
        const phone = document.getElementById('mobile_no').value.trim();
        const address = document.getElementById('address').value.trim();
        const city = document.getElementById('city').value.trim();
        const dob = document.getElementById('dob').value;
        
        // Name validation
        const nameRegex = /^[a-zA-Z ]{3,100}$/;
        if (!nameRegex.test(fullName)) {
            alert("Full name should only contain letters and be between 3-100 characters");
            e.preventDefault();
            return false;
        }

        // Phone validation
        if (!/^[0-9]{11}$/.test(phone)) {
            alert("Invalid phone number format. Must be 11 digits");
            e.preventDefault();
            return false;
        }

        // Address validation
        if (address.length < 5) {
            alert("Address is too short. Minimum 5 characters required");
            e.preventDefault();
            return false;
        }
        if (address.length > 200) {
            alert("Address is too long. Maximum 200 characters allowed");
            e.preventDefault();
            return false;
        }
        
        // City validation
        if (!/^[a-zA-Z ]{2,50}$/.test(city)) {
            alert("City name must contain only letters and be between 2-50 characters");
            e.preventDefault();
            return false;
        }

        // Date of Birth validations
        const dobDate = new Date(dob);
        const today = new Date();
        
        if (dobDate > today) {
            alert("Date of Birth cannot be in the future");
            e.preventDefault();
            return false;
        }

        let age = today.getFullYear() - dobDate.getFullYear();
        const monthDiff = today.getMonth() - dobDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
            age--;
        }

        if (age < 18) {
            alert("You must be at least 18 years old");
            e.preventDefault();
            return false;
        }

        if (age > 100) {
            alert("Please enter a valid Date of Birth");
            e.preventDefault();
            return false;
        }

        return true;
    });
  </script>
</body>
</html>
