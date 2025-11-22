<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>TaskHub Employee Dashboard</title>
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
      font-size: 18px;
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
      font-size:22px;
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

    /*Main content*/
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

    .card-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0,1fr));
      gap: 20px;
    }

    .info-card {
      background-color: #2e3b48;
      color: white;
      border-radius: 10px;
      padding: 28px 18px;
      text-align: center;
      transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
      display: flex;
      flex-direction: column;
      gap:10px;
      align-items:center;
      justify-content:center;
      height:150px;
    }

    .info-card:hover {
      transform: translateY(-6px);
      background-color: #384959;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }

    .info-card .material-icons {
      font-size: 34px;
      color: #5bc0de;
    }

    .info-card h5 {
      font-size: 20px;
      font-weight: 500;
      margin: 0;
      color:#e8eef0;
    }

    .card-link {
      text-decoration: none;
    }

    @media (max-width: 980px) {
      .card-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }
    @media (max-width: 640px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.visible { transform: translateX(0); }
      .topbar { left: 0; }
      .main-content { margin-left: 0; padding: 18px; }
      .card-grid { grid-template-columns: 1fr; }
    }

  </style>

</head>
<body>

  <!--Sidebar-->
  <aside class="sidebar" id="sidebar">
    <h3>TaskHub</h3>

    <div class="profile">
      <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
      <p>Employee</p>
    </div>

    <nav class="nav-links">
      <a href="#" class="trigger active"><span class="material-icons">dashboard</span> Dashboard</a>
      <a href="mytasks.php" class="trigger"><span class="material-icons">task</span> My Task</a>
      <a href="profile.php" class="trigger"><span class="material-icons">person</span> Profile</a>
      <a href="changepassword.php" class="trigger"><span class="material-icons">lock</span> Change Password</a>
      <a href="logout.php" class="trigger"><span class="material-icons">logout</span> Logout</a>
    </nav>
  </aside>

  <!-- Top Bar -->
  <header class="topbar" id="topbar">
    <div class="brand">
      <button class="hamburger" id="hamburger">
        <span class="material-icons">menu</span>
      </button>
      <div>Employee Dashboard</div>
    </div>
    <span class="material-icons">notifications</span>
  </header>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">

    <div class="card-grid">

      <a class="card-link" href="mytasks.php">
        <div class="info-card"><span class="material-icons">task</span><h5>10 My Tasks</h5></div>
      </a>

      <a class="card-link" href="overdue.php">
        <div class="info-card"><span class="material-icons">cancel</span><h5>3 Overdue</h5></div>
      </a>

      <a class="card-link" href="duetoday.php">
        <div class="info-card"><span class="material-icons">today</span><h5>2 Due Today</h5></div>
      </a>

      <a class="card-link" href="notifications.php">
        <div class="info-card"><span class="material-icons">notifications</span><h5>6 Notifications</h5></div>
      </a>

      <a class="card-link" href="pending.php">
        <div class="info-card"><span class="material-icons">pending</span><h5>5 Pending</h5></div>
      </a>

      <a class="card-link" href="completed.php">
        <div class="info-card"><span class="material-icons">check_circle</span><h5>1 Completed</h5></div>
      </a>

    </div>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const sidebar = document.getElementById('sidebar');
    const topbar = document.getElementById('topbar');
    const mainContent = document.getElementById('mainContent');
    const hamburger = document.getElementById('hamburger');

    hamburger.addEventListener('click', () => {
      if (window.innerWidth <= 640) {
        sidebar.classList.toggle('visible');
      } else {
        sidebar.classList.toggle('collapsed');
        topbar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
      }
    });
  </script>

</body>
</html>
