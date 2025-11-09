<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// No database query here - just use session data

$header_full_name = trim(($_SESSION['student_first_name'] ?? '') . ' ' . ($_SESSION['student_last_name'] ?? ''));
$header_photo = !empty($_SESSION['student_photo'])
    ? '../../uploads/student_photos/' . htmlspecialchars($_SESSION['student_photo'])
    : '../../assets/img/default_user.png';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container-fluid">
    <a class="navbar-brand" href="student_dashboard.php">School ID System</a>

    <!-- Hamburger toggle -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNavbar" aria-controls="studentNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navbar links -->
    <div class="collapse navbar-collapse" id="studentNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="student_dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="student_profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="student_records.php">Records</a></li>
        <li class="nav-item"><a class="nav-link" href="student_id.php">Your ID</a></li>
        <li class="nav-item"><a class="nav-link" href="student_help.php">Help</a></li>
      </ul>

      <!-- Profile dropdown -->
      <div class="dropdown">
        <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" href="#" role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="<?= $header_photo ?>" alt="Profile" class="profile-img" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 8px;">
          <span><?= htmlspecialchars($header_full_name) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="profileDropdown">
          <li><a class="dropdown-item" href="edit_student.php">View Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="../../logout.php">Logout</a></li>
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
  display: none; /* hides the default arrow */
}
</style>