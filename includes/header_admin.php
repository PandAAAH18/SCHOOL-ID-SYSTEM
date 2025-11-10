<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get admin info from session
$admin_name = $_SESSION['full_name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container-fluid">
    <a class="navbar-brand" href="admin.php">School ID System - Admin</a>

    <!-- Hamburger toggle -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navbar links -->
    <div class="collapse navbar-collapse" id="adminNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>" href="admin.php">
            📊 Dashboard
          </a>
        </li>
         <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin_id.php' ? 'active' : '' ?>" href="admin_id.php">
            💳 ID's
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="admin_users.php">
            👥 Users
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : '' ?>" href="admin_students.php">
            🎓 Students
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="admin_reports.php">
            📈 Reports
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'activity_log.php' ? 'active' : '' ?>" href="admin_logs.php">
            📋 Activity Log
          </a>
        </li>
      </ul>

      <!-- Admin profile dropdown -->
      <div class="dropdown">
        <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" href="#" role="button" id="adminProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="../../assets/img/default_user.png" alt="Admin Profile" class="profile-img" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 8px;">
          <span><?= htmlspecialchars($admin_name) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="adminProfileDropdown">
          <li><span class="dropdown-item-text small text-muted"><?= htmlspecialchars($admin_email) ?></span></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="admin_profile.php">👤 My Profile</a></li>
          <li><a class="dropdown-item" href="admin_settings.php">⚙️ Settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="../../logout.php">🚪 Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<style>
.profile-img {
  width: 35px;
  height: 35px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 8px;
}
.dropdown-toggle::after {
  display: none;
}
.nav-link.active {
  font-weight: 600;
  background-color: rgba(255,255,255,0.1);
  border-radius: 5px;
}
.nav-link:hover {
  background-color: rgba(255,255,255,0.1);
  border-radius: 5px;
}
</style>