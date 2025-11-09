<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if logged in and role is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header("Location: ../index.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Fetch student info by EMAIL
$stmt = $conn->prepare("SELECT * FROM student WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Check if redirected from ID request due to incomplete profile
if (isset($_GET['incomplete']) && isset($_SESSION['missing_fields'])) {
    $missing_fields = $_SESSION['missing_fields'];
    $field_names = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name', 
        'student_id' => 'Student ID',
        'course' => 'Course',
        'year_level' => 'Year Level',
        'contact_number' => 'Contact Number',
        'emergency_contact' => 'Emergency Contact',
        'address' => 'Address',
        'photo' => 'Profile Photo'
    ];
    
    $missing_list = [];
    foreach ($missing_fields as $field) {
        $missing_list[] = $field_names[$field];
    }
    
    echo '<div class="alert alert-warning">
            <strong>Complete Your Profile First!</strong><br>
            You need to complete the following fields before requesting an ID: 
            ' . implode(', ', $missing_list) . '
          </div>';
    unset($_SESSION['missing_fields']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Profile | School ID System</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
</head>

<body class="bg-light">

    <?php include '../../includes/header_student.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit Profile</h2>
            <a href="student_dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php elseif(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">Update Your Information</div>
            <div class="card-body">
                <form action="../../includes/process_edit_profile.php" method="POST" enctype="multipart/form-data">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control"
                                value="<?= htmlspecialchars($student['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control"
                                value="<?= htmlspecialchars($student['last_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id" class="form-control"
                                value="<?= htmlspecialchars($student['student_id'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control"
                                value="<?= htmlspecialchars($student['email'] ?? '') ?>" readonly>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Year Level</label>
                            <select name="year_level" class="form-select" required>
                                <option value="">Select Year Level</option>
                                <option value="1st Year"
                                    <?= ($student['year_level'] ?? '') == '1st Year' ? 'selected' : '' ?>>1st Year
                                </option>
                                <option value="2nd Year"
                                    <?= ($student['year_level'] ?? '') == '2nd Year' ? 'selected' : '' ?>>2nd Year
                                </option>
                                <option value="3rd Year"
                                    <?= ($student['year_level'] ?? '') == '3rd Year' ? 'selected' : '' ?>>3rd Year
                                </option>
                                <option value="4th Year"
                                    <?= ($student['year_level'] ?? '') == '4th Year' ? 'selected' : '' ?>>4th Year
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course</label>
                            <select name="course" class="form-select" required>
                                <option value="">Select Course</option>
                                <option value="BS Information System"
                                    <?= ($student['course'] ?? '') == 'BS Information System' ? 'selected' : '' ?>>BS
                                    Information System</option>
                                <option value="BS Psychology"
                                    <?= ($student['course'] ?? '') == 'BS Psychology' ? 'selected' : '' ?>>BS Psychology
                                </option>
                                <option value="BS Nursing"
                                    <?= ($student['course'] ?? '') == 'BS Nursing' ? 'selected' : '' ?>>BS Nursing
                                </option>
                                <option value="BS Engineering"
                                    <?= ($student['course'] ?? '') == 'BS Engineering' ? 'selected' : '' ?>>BS
                                    Engineering</option>
                                <option value="BS Life Science"
                                    <?= ($student['course'] ?? '') == 'BS Life Science' ? 'selected' : '' ?>>BS Life
                                    Science</option>
                                <option value="BS Midwifery"
                                    <?= ($student['course'] ?? '') == 'BS Midwifery' ? 'selected' : '' ?>>BS Midwifery
                                </option>
                                <option value="BS Computer Science"
                                    <?= ($student['course'] ?? '') == 'BS Computer Science' ? 'selected' : '' ?>>BS
                                    Computer Science</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control"
                                value="<?= htmlspecialchars($student['contact_number'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact</label>
                            <input type="text" name="emergency_contact" class="form-control"
                                value="<?= htmlspecialchars($student['emergency_contact'] ?? '') ?>" required>
                            <small class="text-muted">Name and phone number of emergency contact</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"
                            required><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Blood Type (Optional)</label>
                            <select name="blood_type" class="form-select">
                                <option value="">Select Blood Type</option>
                                <option value="A+" <?= ($student['blood_type'] ?? '') == 'A+' ? 'selected' : '' ?>>A+
                                </option>
                                <option value="A-" <?= ($student['blood_type'] ?? '') == 'A-' ? 'selected' : '' ?>>A-
                                </option>
                                <option value="B+" <?= ($student['blood_type'] ?? '') == 'B+' ? 'selected' : '' ?>>B+
                                </option>
                                <option value="B-" <?= ($student['blood_type'] ?? '') == 'B-' ? 'selected' : '' ?>>B-
                                </option>
                                <option value="AB+" <?= ($student['blood_type'] ?? '') == 'AB+' ? 'selected' : '' ?>>AB+
                                </option>
                                <option value="AB-" <?= ($student['blood_type'] ?? '') == 'AB-' ? 'selected' : '' ?>>AB-
                                </option>
                                <option value="O+" <?= ($student['blood_type'] ?? '') == 'O+' ? 'selected' : '' ?>>O+
                                </option>
                                <option value="O-" <?= ($student['blood_type'] ?? '') == 'O-' ? 'selected' : '' ?>>O-
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Current Photo</label><br>
                        <?php if (!empty($student['photo'])): ?>
                        <img src="../../uploads/student_photos/<?= htmlspecialchars($student['photo']) ?>"
                            alt="Current Photo" class="rounded mb-2" width="100" height="100"
                            style="object-fit: cover;">
                        <?php else: ?>
                        <img src="../../assets/img/default_user.png" alt="No Photo" class="rounded mb-2" width="100"
                            height="100" style="object-fit: cover;">
                        <p class="text-danger">Photo is required for ID card!</p>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload New Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <small class="text-muted">Required for ID card. Accepted formats: JPG, PNG, GIF. Max size:
                            2MB</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Current COR</label><br>
                        <?php if (!empty($student['cor'])): ?>
                        <img src="../../uploads/student_cors/<?= htmlspecialchars($student['cor']) ?>" alt="Current COR"
                            class="rounded mb-2" width="100" height="100" style="object-fit: cover;">
                        <?php else: ?>
                        <p class="text-danger">COR is required for ID card!</p>
                        <?php endif; ?>
                        <input type="file" name="cor" class="form-control"
                            accept="image/jpeg,image/png,image/jpg,image/gif">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Student Signature</label><br>
                        <?php if (!empty($student['signature'])): ?>
                        <img src="../../uploads/student_signatures/<?= htmlspecialchars($student['signatures']) ?>" alt="Student Signature"
                            class="rounded mb-2" width="100" height="100" style="object-fit: cover;">
                        <?php else: ?>
                        <p class="text-danger">Signature is required for ID card!</p>
                        <?php endif; ?>
                        <input type="file" name="cor" class="form-control"
                            accept="image/jpeg,image/png,image/jpg,image/gif">
                    </div>



                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="student_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>