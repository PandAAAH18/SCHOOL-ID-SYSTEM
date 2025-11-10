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

/* ---------- COR upload ---------- */
$cor_path = null;
if (isset($_FILES['cor']) && $_FILES['cor']['error'] === UPLOAD_ERR_OK) {
    $cor = $_FILES['cor'];
    $allowed = ['image/jpeg','image/png','image/jpg','image/gif','application/pdf'];

    // validate type & size
    if (!in_array($cor['type'], $allowed))
        { $_SESSION['error'] = 'Invalid COR format. Only JPG, PNG, GIF, and PDF allowed.'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }
    if ($cor['size'] > 2_097_152)
        { $_SESSION['error'] = 'COR file too large (max 2 MB).'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }

    // Create filename using email and timestamp
    $clean_email = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $email); // Clean email for filename
    $ext = pathinfo($cor['name'], PATHINFO_EXTENSION);
    $new_name = 'cor_' . $clean_email . '_' . time() . '.' . $ext;
    $upload_dir = __DIR__ . '/../uploads/student_cor/';   // note trailing slash

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (move_uploaded_file($cor['tmp_name'], $upload_dir . $new_name))
        $cor_path = $new_name;          // store only filename
    else
        { $_SESSION['error'] = 'COR upload failed.'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }
}

/* ---------- Signature upload ---------- */
$signature_path = null;
if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
    $signature = $_FILES['signature'];
    $allowed = ['image/jpeg','image/png','image/jpg','image/gif'];

    // validate type & size
    if (!in_array($signature['type'], $allowed))
        { $_SESSION['error'] = 'Invalid signature format. Only JPG, PNG, GIF allowed.'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }
    if ($signature['size'] > 2_097_152)
        { $_SESSION['error'] = 'Signature file too large (max 2 MB).'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }

    // Create filename using email and timestamp
    $clean_email = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $email);
    $ext = pathinfo($signature['name'], PATHINFO_EXTENSION);
    $new_name = 'signature_' . $clean_email . '_' . time() . '.' . $ext;
    $upload_dir = __DIR__ . '/../uploads/student_signatures/';   // note trailing slash

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (move_uploaded_file($signature['tmp_name'], $upload_dir . $new_name))
        $signature_path = $new_name;          // store only filename
    else
        { $_SESSION['error'] = 'Signature upload failed.'; header('Location: ../dashboard/student/edit_student_profile.php'); exit(); }
}

// Build update query based on what files were uploaded
$update_fields = [];
$update_values = [];
$update_types = "";

// Always update these basic fields
$base_fields = ["first_name", "last_name", "student_id", "year_level", "course", "contact_number", "emergency_contact", "address", "blood_type"];
$base_values = [$first_name, $last_name, $student_id, $year_level, $course, $contact_number, $emergency_contact, $address, $blood_type];
$base_types = "sssssssss";

foreach ($base_fields as $field) {
    $update_fields[] = "{$field}=?";
}
$update_values = array_merge($update_values, $base_values);
$update_types .= $base_types;

// Add file fields if they exist
if ($photo_path) {
    $update_fields[] = "photo=?";
    $update_values[] = $photo_path;
    $update_types .= "s";
}

if ($cor_path) {
    $update_fields[] = "cor=?";
    $update_values[] = $cor_path;
    $update_types .= "s";
}

if ($signature_path) {
    $update_fields[] = "signature=?";
    $update_values[] = $signature_path;
    $update_types .= "s";
}

// Add WHERE clause
$update_values[] = $email;
$update_types .= "s";

// Build and execute the query
$sql = "UPDATE student SET " . implode(", ", $update_fields) . " WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($update_types, ...$update_values);

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
    if ($cor_path) {
        $_SESSION['student_cor'] = $cor_path;
    }
    if ($signature_path) {
        $_SESSION['student_signature'] = $signature_path;
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