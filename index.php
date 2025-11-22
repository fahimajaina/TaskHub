<?php
session_start();
include('include/config.php');

if (isset($_POST['signin'])) {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  $sql = "SELECT id, full_name, email, password, status FROM users WHERE email = :email";
  $query = $dbh->prepare($sql);
  $query->bindParam(':email', $email, PDO::PARAM_STR);
  $query->execute();
  $result = $query->fetch(PDO::FETCH_OBJ);

  if ($result) {
      if ($result->status !== 'Active') {
          echo "<script>alert('Your account is inactive. Please contact admin.');</script>";
      } elseif (password_verify($password, $result->password)) {
          $_SESSION['elogin'] = $result->email;
          $_SESSION['eid'] = $result->id;
          $_SESSION['ename'] = $result->full_name;
          header('location: dashboard.php');
          exit();
      } else {
          echo "<script>alert('Invalid Password');</script>";
      }
  } else {
      echo "<script>alert('Invalid Email');</script>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Employee Login | TaskHub</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #1f2a35;
      margin: 0;
      padding-top: 60px;
      color: #e8eef0;
    }

    .card {
      max-width: 500px;
      margin: 0 auto;
      padding: 40px 35px;
      border: none;
      border-radius: 20px;
      background: #2e3b48;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
      color: #e8eef0;
    }

    .card h4 {
      text-align: center;
      font-weight: 700;
      color: #5bc0de;
      margin-bottom: 25px;
    }

    .form-control {
      padding: 12px;
      border-radius: 10px;
      background: #26323d;
      color: #e8eef0;
      border: 1px solid #384959;
    }

    .form-control:focus {
      border-color: #5bc0de;
      box-shadow: 0 0 0 0.15rem rgba(91, 192, 222, 0.25);
      background: #26323d;
      color: #fff;
    }

    .btn-custom {
      background-color: #5bc0de;
      color: #1f2a35;
      padding: 12px;
      font-weight: 500;
      border-radius: 8px;
      transition: background-color 0.3s ease, transform 0.2s ease;
      border: none;
    }

    .btn-custom:hover {
      background-color: #384959;
      color: #5bc0de;
      transform: translateY(-1px);
    }

    .admin-link {
      text-align: center;
      margin-top: 20px;
    }

    .admin-link a {
      color: #5bc0de;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .admin-link a:hover {
      color: #e8eef0;
    }

    .material-icons {
      vertical-align: middle;
      color: #5bc0de;
    }
  </style>
</head>
<body>

  <!-- Form -->
  <div class="card mt-5">
    <h4><span class="material-icons">person</span> Employee Login</h4>
    <form method="post" name="signin">
      <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" class="form-control" id="email" name="email" required autocomplete="off">
      </div>
      <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="off">
      </div>
      <div class="d-grid">
        <button type="submit" name="signin" class="btn btn-custom">Sign In</button>
      </div>
    </form>

    <!-- Admin -->
    <div class="admin-link">
      <a href="admin/index.php">
        <span class="material-icons">admin_panel_settings</span> Admin Login
      </a>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
