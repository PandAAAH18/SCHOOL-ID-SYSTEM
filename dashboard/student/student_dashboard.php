<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if logged in and role is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header("Location: ../index.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email']; // We set this during login in auth.php

// ✅ FIXED: Fetch student info by EMAIL, not by ID
$stmt = $conn->prepare("SELECT * FROM student WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// If no profile found, redirect to complete profile form
if (!$student || empty($student['first_name'])) {
  $_SESSION['student_id'] = $student ? $student['id'] : null;
  header("Location: ../../complete_profile.php");
  exit();
}

// Store in session for header_student.php to use
$_SESSION['student_first_name'] = $student['first_name'] ?? '';
$_SESSION['student_last_name'] = $student['last_name'] ?? '';
$_SESSION['student_photo'] = $student['photo'] ?? '';

// Combine name
$full_name = trim($student['first_name'] . ' ' . $student['last_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
</head>
<body class="bg-light">

<?php include '../../includes/header_student.php'; ?>

  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Welcome, <?= htmlspecialchars($full_name) ?></h2>
      <a href="../../logout.php" class="btn btn-secondary btn-sm">Logout</a>
    </div>

    <!-- Profile Info -->
    <div class="card shadow mb-4">
      <div class="card-header bg-success text-white">My Profile</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 text-center">
            <img src="<?= $student['photo'] ? '../../uploads/student_photos/' . htmlspecialchars($student['photo']) : '../../assets/img/default_user.png' ?>" 
                 alt="Profile Photo" class="rounded-circle mb-3" width="120" height="120">
          </div>
          <div class="col-md-8">
            <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
            <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
            <p><strong>Year level:</strong> <?= htmlspecialchars($student['year_level']) ?></p>
            <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($student['contact_number']) ?></p>
            <p><strong>Address:</strong> <?= htmlspecialchars($student['address']) ?></p>
            <a href="edit_student_profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
            <a href="change_password.php" class="btn btn-outline-warning btn-sm">Change Password</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Digital ID Card -->
    <div class="card shadow mb-4">
      <div class="card-header bg-success text-white">My Digital ID Card</div>
      <div class="card-body text-center">
        <?php if (!empty($student['student_id'])): ?>
          <div class="border p-3 rounded bg-white d-inline-block">
            <h5><?= htmlspecialchars($full_name) ?></h5>
            <img src="../../assets/img/sample_qr.png" alt="QR Code" width="100">
            <p class="mt-2"><strong>ID STATUS:</strong> ✅ Issued</p>
            <a href="download_id.php" class="btn btn-success btn-sm">Download ID</a>
          </div>
        <?php else: ?>
          <p class="text-muted">Your ID has not been generated yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- ID Issuance Status -->
    <div class="card shadow mb-4">
      <div class="card-header bg-dark text-white">ID Issuance Status</div>
      <div class="card-body">
        <?php
          // Replace with your real status logic if you add one later
          echo "<p>🕒 Your ID request is being processed.</p>";
        ?>
      </div>
    </div>
  </div>
<?php include '../../includes/footer.php'; ?>
  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>