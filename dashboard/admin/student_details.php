<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  $_SESSION['error'] = "Student ID not provided.";
  header("Location: admin_students.php");
  exit();
}

$student_id = $_GET['id'];

// Fetch student details
$query = "
    SELECT s.*, u.user_id, u.status as user_status, u.is_verified, u.created_at as account_created
    FROM student s 
    LEFT JOIN users u ON s.email = u.email 
    WHERE s.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $_SESSION['error'] = "Student not found.";
  header("Location: admin_students.php");
  exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if (isset($_POST['update_student'])) {
  $first_name = $_POST['first_name'];
  $last_name = $_POST['last_name'];
  $student_id_num = $_POST['student_id'];
  $email = $_POST['email'];
  $year_level = $_POST['year_level'];
  $course = $_POST['course'];
  $contact_number = $_POST['contact_number'];
  $address = $_POST['address'];
  $emergency_contact = $_POST['emergency_contact'];
  $blood_type = $_POST['blood_type'];
  $profile_completed = isset($_POST['profile_completed']) ? 1 : 0;
  
  // Check if email already exists for another student
  $check_email_stmt = $conn->prepare("SELECT id FROM student WHERE email = ? AND id != ?");
  $check_email_stmt->bind_param("si", $email, $student_id);
  $check_email_stmt->execute();
  $email_result = $check_email_stmt->get_result();
  
  if ($email_result->num_rows > 0) {
    $_SESSION['error'] = "Email already exists for another student.";
  } else {
    // Update student record
    $update_stmt = $conn->prepare("
      UPDATE student SET 
        first_name = ?, 
        last_name = ?, 
        student_id = ?, 
        email = ?, 
        year_level = ?, 
        course = ?, 
        contact_number = ?, 
        address = ?, 
        emergency_contact = ?, 
        blood_type = ?, 
        profile_completed = ?
      WHERE id = ?
    ");
    
    $update_stmt->bind_param(
      "ssssssssssii", 
      $first_name, 
      $last_name, 
      $student_id_num, 
      $email, 
      $year_level, 
      $course, 
      $contact_number, 
      $address, 
      $emergency_contact, 
      $blood_type, 
      $profile_completed, 
      $student_id
    );
    
    if ($update_stmt->execute()) {
      // Update users table if email changed
      if ($student['email'] !== $email) {
        $update_user_stmt = $conn->prepare("UPDATE users SET email = ? WHERE email = ?");
        $update_user_stmt->bind_param("ss", $email, $student['email']);
        $update_user_stmt->execute();
        $update_user_stmt->close();
      }
      
      // Update full name in users table
      $full_name = $first_name . ' ' . $last_name;
      $update_name_stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE email = ?");
      $update_name_stmt->bind_param("ss", $full_name, $email);
      $update_name_stmt->execute();
      $update_name_stmt->close();
      
      // Log activity
      $admin_id = $_SESSION['user_id'];
      $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Updated student profile: $email', 0)");
      
      $_SESSION['success'] = "Student profile updated successfully!";
      
      // Refresh student data
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $student_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $student = $result->fetch_assoc();
      $stmt->close();
    } else {
      $_SESSION['error'] = "Error updating student profile.";
    }
    
    $update_stmt->close();
  }
  
  $check_email_stmt->close();
}

// Handle photo upload
if (isset($_POST['upload_photo']) && isset($_FILES['photo'])) {
  if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = $_FILES['photo']['type'];
    
    if (in_array($file_type, $allowed_types)) {
      // Generate unique filename
      $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
      $filename = 'student_' . $student_id . '_' . time() . '.' . $file_extension;
      $upload_path = '../../uploads/student_photos/' . $filename;
      
      // Create directory if it doesn't exist
      if (!file_exists('../../uploads/student_photos/')) {
        mkdir('../../uploads/student_photos/', 0777, true);
      }
      
      if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
        // Update database with photo path
        $update_photo_stmt = $conn->prepare("UPDATE student SET photo = ? WHERE id = ?");
        $update_photo_stmt->bind_param("si", $filename, $student_id);
        
        if ($update_photo_stmt->execute()) {
          // Delete old photo if exists
          if (!empty($student['photo']) && file_exists('../../uploads/student_photos/' . $student['photo'])) {
            unlink('../../uploads/student_photos/' . $student['photo']);
          }
          
          $_SESSION['success'] = "Photo uploaded successfully!";
          $student['photo'] = $filename;
        } else {
          $_SESSION['error'] = "Error updating photo in database.";
        }
        
        $update_photo_stmt->close();
      } else {
        $_SESSION['error'] = "Error uploading photo.";
      }
    } else {
      $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF images are allowed.";
    }
  } else {
    $_SESSION['error'] = "Error uploading file. Please try again.";
  }
}

// Handle signature upload
if (isset($_POST['upload_signature']) && isset($_FILES['signature'])) {
  if ($_FILES['signature']['error'] === UPLOAD_ERR_OK) {
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = $_FILES['signature']['type'];
    
    if (in_array($file_type, $allowed_types)) {
      // Generate unique filename
      $file_extension = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
      $filename = 'signature_' . $student_id . '_' . time() . '.' . $file_extension;
      $upload_path = '../../uploads/signatures/' . $filename;
      
      // Create directory if it doesn't exist
      if (!file_exists('../../uploads/signatures/')) {
        mkdir('../../uploads/signatures/', 0777, true);
      }
      
      if (move_uploaded_file($_FILES['signature']['tmp_name'], $upload_path)) {
        // Update database with signature path
        $update_signature_stmt = $conn->prepare("UPDATE student SET signature = ? WHERE id = ?");
        $update_signature_stmt->bind_param("si", $filename, $student_id);
        
        if ($update_signature_stmt->execute()) {
          // Delete old signature if exists
          if (!empty($student['signature']) && file_exists('../../uploads/signatures/' . $student['signature'])) {
            unlink('../../uploads/signatures/' . $student['signature']);
          }
          
          $_SESSION['success'] = "Signature uploaded successfully!";
          $student['signature'] = $filename;
        } else {
          $_SESSION['error'] = "Error updating signature in database.";
        }
        
        $update_signature_stmt->close();
      } else {
        $_SESSION['error'] = "Error uploading signature.";
      }
    } else {
      $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF images are allowed.";
    }
  } else {
    $_SESSION['error'] = "Error uploading file. Please try again.";
  }
}

// Handle COR upload
if (isset($_POST['upload_cor']) && isset($_FILES['cor'])) {
  if ($_FILES['cor']['error'] === UPLOAD_ERR_OK) {
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = $_FILES['cor']['type'];
    
    if (in_array($file_type, $allowed_types)) {
      // Generate unique filename
      $file_extension = pathinfo($_FILES['cor']['name'], PATHINFO_EXTENSION);
      $filename = 'cor_' . $student_id . '_' . time() . '.' . $file_extension;
      $upload_path = '../../uploads/student_cors/' . $filename;
      
      // Create directory if it doesn't exist
      if (!file_exists('../../uploads/student_cors/')) {
        mkdir('../../uploads/student_cors/', 0777, true);
      }
      
      if (move_uploaded_file($_FILES['cor']['tmp_name'], $upload_path)) {
        // Update database with COR path
        $update_cor_stmt = $conn->prepare("UPDATE student SET cor = ? WHERE id = ?");
        $update_cor_stmt->bind_param("si", $filename, $student_id);
        
        if ($update_cor_stmt->execute()) {
          // Delete old COR if exists
          if (!empty($student['cor']) && file_exists('../../uploads/student_cors/' . $student['cor'])) {
            unlink('../../uploads/student_cors/' . $student['cor']);
          }
          
          $_SESSION['success'] = "COR uploaded successfully!";
          $student['cor'] = $filename;
        } else {
          $_SESSION['error'] = "Error updating COR in database.";
        }
        
        $update_cor_stmt->close();
      } else {
        $_SESSION['error'] = "Error uploading COR.";
      }
    } else {
      $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF images are allowed.";
    }
  } else {
    $_SESSION['error'] = "Error uploading file. Please try again.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Details | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .profile-header {
      background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
      color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
    }
    .profile-picture {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 5px solid white;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .signature-preview, .cor-preview {
      max-width: 300px;
      max-height: 150px;
      border: 1px solid #dee2e6;
      border-radius: 5px;
      object-fit: contain;
    }
    .cor-preview {
      max-height: 200px;
    }
    .info-card {
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      margin-bottom: 20px;
      border: none;
    }
    .info-card .card-header {
      border-radius: 10px 10px 0 0 !important;
      font-weight: 600;
    }
    .badge-status {
      font-size: 0.8rem;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <!-- Success/Error Messages -->
    <?php if(isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Student Details</h2>
      <div>
        <a href="admin_students.php" class="btn btn-secondary btn-sm">← Back to Students</a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editStudentModal">Edit Student</button>
      </div>
    </div>

    <!-- Profile Header -->
    <div class="profile-header">
      <div class="row align-items-center">
        <div class="col-md-2 text-center">
          <?php if (!empty($student['photo'])): ?>
            <img src="../../uploads/student_photos/<?= $student['photo'] ?>" class="profile-picture" alt="Student Photo">
          <?php else: ?>
            <div class="profile-picture bg-light d-flex align-items-center justify-content-center">
              <span class="text-muted">No Photo</span>
            </div>
          <?php endif; ?>
          <div class="mt-2">
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
              Change Photo
            </button>
          </div>
        </div>
        <div class="col-md-10">
          <h3><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h3>
          <div class="row mt-3">
            <div class="col-md-4">
              <p class="mb-1"><strong>Student ID:</strong> 
                <?= !empty($student['student_id']) ? htmlspecialchars($student['student_id']) : '<span class="text-warning">Not assigned</span>' ?>
              </p>
            </div>
            <div class="col-md-4">
              <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
            </div>
            <div class="col-md-4">
              <p class="mb-1"><strong>Account Status:</strong> 
                <?php if ($student['user_id']): ?>
                  <span class="badge bg-success">Registered</span>
                <?php else: ?>
                  <span class="badge bg-warning">No Account</span>
                <?php endif; ?>
              </p>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-4">
              <p class="mb-1"><strong>Course:</strong> 
                <?= !empty($student['course']) ? htmlspecialchars($student['course']) : '<span class="text-muted">Not set</span>' ?>
              </p>
            </div>
            <div class="col-md-4">
              <p class="mb-1"><strong>Year Level:</strong> 
                <?= !empty($student['year_level']) ? htmlspecialchars($student['year_level']) : '<span class="text-muted">Not set</span>' ?>
              </p>
            </div>
            <div class="col-md-4">
              <p class="mb-1"><strong>Profile Completed:</strong> 
                <?= $student['profile_completed'] ? 
                  '<span class="badge bg-success">Yes</span>' : 
                  '<span class="badge bg-warning">No</span>' ?>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Personal Information -->
      <div class="col-md-6">
        <div class="card info-card">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Personal Information</h5>
          </div>
          <div class="card-body">
            <table class="table table-borderless">
              <tr>
                <td width="40%"><strong>First Name:</strong></td>
                <td><?= !empty($student['first_name']) ? htmlspecialchars($student['first_name']) : '<span class="text-muted">Not set</span>' ?></td>
              </tr>
              <tr>
                <td><strong>Last Name:</strong></td>
                <td><?= !empty($student['last_name']) ? htmlspecialchars($student['last_name']) : '<span class="text-muted">Not set</span>' ?></td>
              </tr>
              <tr>
                <td><strong>Email:</strong></td>
                <td><?= htmlspecialchars($student['email']) ?></td>
              </tr>
              <tr>
                <td><strong>Contact Number:</strong></td>
                <td><?= !empty($student['contact_number']) ? htmlspecialchars($student['contact_number']) : '<span class="text-muted">Not set</span>' ?></td>
              </tr>
              <tr>
                <td><strong>Address:</strong></td>
                <td><?= !empty($student['address']) ? htmlspecialchars($student['address']) : '<span class="text-muted">Not set</span>' ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Academic Information -->
      <div class="col-md-6">
        <div class="card info-card">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0">Academic Information</h5>
          </div>
          <div class="card-body">
            <table class="table table-borderless">
              <tr>
                <td width="40%"><strong>Student ID:</strong></td>
                <td><?= !empty($student['student_id']) ? htmlspecialchars($student['student_id']) : '<span class="text-warning">Not assigned</span>' ?></td>
              </tr>
              <tr>
                <td><strong>Course:</strong></td>
                <td><?= !empty($student['course']) ? htmlspecialchars($student['course']) : '<span class="text-muted">Not set</span>' ?></td>
              </tr>
              <tr>
                <td><strong>Year Level:</strong></td>
                <td><?= !empty($student['year_level']) ? htmlspecialchars($student['year_level']) : '<span class="text-muted">Not set</span>' ?></td>
              </tr>
              <tr>
                <td><strong>Profile Completed:</strong></td>
                <td>
                  <?= $student['profile_completed'] ? 
                    '<span class="badge bg-success">Yes</span>' : 
                    '<span class="badge bg-warning">No</span>' ?>
                </td>
              </tr>
              <tr>
                <td><strong>Record Created:</strong></td>
                <td><?= !empty($student['created_at']) ? date('M j, Y g:i A', strtotime($student['created_at'])) : 'Unknown' ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Emergency & Medical Information -->
      <div class="col-md-6">
        <div class="card info-card">
          <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Emergency & Medical Information</h5>
          </div>
          <div class="card-body">
            <table class="table table-borderless">
              <tr>
                <td width="40%"><strong>Emergency Contact:</strong></td>
                <td><?= !empty($student['emergency_contact']) ? htmlspecialchars($student['emergency_contact']) : '<span class="text-muted">Not set</span>' ?></td>
              </tr>
              <tr>
                <td><strong>Blood Type:</strong></td>
                <td>
                  <?php if (!empty($student['blood_type'])): ?>
                    <span class="badge bg-danger"><?= htmlspecialchars($student['blood_type']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Not set</span>
                  <?php endif; ?>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Account Information -->
      <div class="col-md-6">
        <div class="card info-card">
          <div class="card-header bg-info text-white">
            <h5 class="mb-0">Account Information</h5>
          </div>
          <div class="card-body">
            <table class="table table-borderless">
              <tr>
                <td width="40%"><strong>User Account:</strong></td>
                <td>
                  <?php if ($student['user_id']): ?>
                    <span class="badge bg-success">Registered</span>
                  <?php else: ?>
                    <span class="badge bg-warning">No Account</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php if ($student['user_id']): ?>
              <tr>
                <td><strong>Account Status:</strong></td>
                <td>
                  <span class="badge <?= $student['user_status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                    <?= ucfirst($student['user_status']) ?>
                  </span>
                </td>
              </tr>
              <tr>
                <td><strong>Verified:</strong></td>
                <td>
                  <?= $student['is_verified'] ? 
                    '<span class="badge bg-success">Yes</span>' : 
                    '<span class="badge bg-warning">No</span>' ?>
                </td>
              </tr>
              <tr>
                <td><strong>Account Created:</strong></td>
                <td><?= !empty($student['account_created']) ? date('M j, Y g:i A', strtotime($student['account_created'])) : 'Unknown' ?></td>
              </tr>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>

      <!-- Signature -->
      <div class="col-md-6">
        <div class="card info-card">
          <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Signature</h5>
          </div>
          <div class="card-body">
            <?php if (!empty($student['signature'])): ?>
              <div class="mb-3">
                <img src="../../uploads/student_signatures/<?= $student['signature'] ?>" class="signature-preview" alt="Student Signature">
              </div>
            <?php else: ?>
              <p class="text-muted">No signature uploaded.</p>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadSignatureModal">
              <?= empty($student['signature']) ? 'Upload Signature' : 'Change Signature' ?>
            </button>
          </div>
        </div>
      </div>

      <!-- COR (Certificate of Registration) -->
      <div class="col-md-6">
    <div class="card info-card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">📄 COR (Certificate of Registration)</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($student['cor'])): ?>
                <div class="mb-3">
                    <a href="../../uploads/student_cor/<?= $student['cor'] ?>" target="_blank">
                        <img src="../../uploads/student_cor/<?= $student['cor'] ?>" 
                             class="cor-preview img-thumbnail" 
                             alt="Certificate of Registration"
                             style="cursor: pointer; max-width: 200px;">
                    </a>
                    <small class="d-block text-muted mt-1">Click image to view full size in new tab</small>
                </div>
            <?php else: ?>
                <p class="text-muted">No COR uploaded.</p>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCORModal">
                <?= empty($student['cor']) ? 'Upload COR' : 'Change COR' ?>
            </button>
        </div>
    </div>
</div>
    </div>
  </div>

  <!-- Edit Student Modal -->
  <div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Student Information</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">First Name</label>
                  <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($student['first_name'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Last Name</label>
                  <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($student['last_name'] ?? '') ?>">
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Student ID</label>
                  <input type="text" name="student_id" class="form-control" value="<?= htmlspecialchars($student['student_id'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Year Level</label>
                  <input type="text" name="year_level" class="form-control" value="<?= htmlspecialchars($student['year_level'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Course</label>
                  <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($student['course'] ?? '') ?>">
                </div>
              </div>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Contact Number</label>
              <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($student['contact_number'] ?? '') ?>">
            </div>
            
            <div class="mb-3">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Emergency Contact</label>
                  <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($student['emergency_contact'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Blood Type</label>
                  <select name="blood_type" class="form-select">
                    <option value="">Select Blood Type</option>
                    <option value="A+" <?= ($student['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                    <option value="A-" <?= ($student['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                    <option value="B+" <?= ($student['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                    <option value="B-" <?= ($student['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                    <option value="AB+" <?= ($student['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                    <option value="AB-" <?= ($student['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                    <option value="O+" <?= ($student['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                    <option value="O-" <?= ($student['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                  </select>
                </div>
              </div>
            </div>
            
            <div class="mb-3 form-check">
              <input type="checkbox" name="profile_completed" class="form-check-input" id="profile_completed" <?= $student['profile_completed'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="profile_completed">Profile Completed</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Upload Photo Modal -->
  <div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Upload Student Photo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Select Photo</label>
              <input type="file" name="photo" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif" required>
              <div class="form-text">Allowed formats: JPG, PNG, GIF. Max file size: 2MB.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="upload_photo" class="btn btn-primary">Upload Photo</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Upload Signature Modal -->
  <div class="modal fade" id="uploadSignatureModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Upload Student Signature</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Select Signature Image</label>
              <input type="file" name="signature" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif" required>
              <div class="form-text">Allowed formats: JPG, PNG, GIF. Max file size: 2MB.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="upload_signature" class="btn btn-primary">Upload Signature</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Upload COR Modal -->
  <div class="modal fade" id="uploadCORModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Upload COR (Certificate of Registration)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Select COR Image</label>
              <input type="file" name="cor" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif" required>
              <div class="form-text">Allowed formats: JPG, PNG, GIF. Max file size: 2MB.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="upload_cor" class="btn btn-primary">Upload COR</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>