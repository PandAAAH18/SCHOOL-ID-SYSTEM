<?php
session_start();
include 'db_connect.php';

// Check if logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$email = $_SESSION['email'];

// Get form values
$first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
$last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
$student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
$year_level = mysqli_real_escape_string($conn, $_POST['year_level']);
$course = mysqli_real_escape_string($conn, $_POST['course']);
$contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
$emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact']);
$address = mysqli_real_escape_string($conn, $_POST['address']);
$blood_type = mysqli_real_escape_string($conn, $_POST['blood_type']);

/* ---------- photo upload ---------- */
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $photo = $_FILES['photo'];
    $allowed = ['image/jpeg','image/png','image/jpg','image/gif'];

    // validate type & size
    if (!in_array($photo['type'], $allowed))
        { $_SESSION['error'] = 'Invalid image format.'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }
    if ($photo['size'] > 2_097_152)
        { $_SESSION['error'] = 'File too large (max 2 MB).'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }

    // student id for filename
    $stmt = $conn->prepare('SELECT id FROM student WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $ext      = pathinfo($photo['name'], PATHINFO_EXTENSION);
    $new_name = 'student_' . $student['id'] . '_' . time() . '.' . $ext;
    $upload_dir = __DIR__ . '/../uploads/student_photos/';   // note trailing slash

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (move_uploaded_file($photo['tmp_name'], $upload_dir . $new_name))
        $photo_path = $new_name;          // store only filename
    else
        { $_SESSION['error'] = 'Upload failed.'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }
}

// Build update query
if ($photo_path) {
    // Update with new photo
    $stmt = $conn->prepare("UPDATE student SET first_name=?, last_name=?, student_id=?, year_level=?, course=?, contact_number=?, emergency_contact=?, address=?, blood_type=?, photo=? WHERE email=?");
    $stmt->bind_param("sssssssssss", $first_name, $last_name, $student_id, $year_level, $course, $contact_number, $emergency_contact, $address, $blood_type, $photo_path, $email);
} else {
    // Update without changing photo
    $stmt = $conn->prepare("UPDATE student SET first_name=?, last_name=?, student_id=?, year_level=?, course=?, contact_number=?, emergency_contact=?, address=?, blood_type=? WHERE email=?");
    $stmt->bind_param("ssssssssss", $first_name, $last_name, $student_id, $year_level, $course, $contact_number, $emergency_contact, $address, $blood_type, $email);
}

// Execute
if ($stmt->execute()) {
    // Update full_name in users table too
    $full_name = $first_name . ' ' . $last_name;
    $update_users = $conn->prepare("UPDATE users SET full_name=? WHERE email=?");
    $update_users->bind_param("ss", $full_name, $email);
    $update_users->execute();
    $update_users->close();

    // Update session variables
    $_SESSION['full_name'] = $full_name;
    $_SESSION['student_first_name'] = $first_name;
    $_SESSION['student_last_name'] = $last_name;
    if ($photo_path) {
        $_SESSION['student_photo'] = $photo_path;
    }

    $_SESSION['success'] = "Profile updated successfully!";
    header("Location: ../dashboard/student/student_dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Error updating profile: " . $conn->error;
    header("Location: ../dashboard/student/edit_student_profile.php");
    exit();
}

$stmt->close();
$conn->close();
?>