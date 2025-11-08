<?php
session_start();
include 'db_connect.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$email = $_SESSION['email'];

// Get form values
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

// Validate new passwords match
if ($new_password !== $confirm_password) {
    $_SESSION['error'] = "New passwords do not match!";
    header("Location: ../dashboard/change_password.php");
    exit();
}

// Validate minimum length
if (strlen($new_password) < 6) {
    $_SESSION['error'] = "Password must be at least 6 characters long.";
    header("Location: ../dashboard/change_password.php");
    exit();
}

// Get current password hash from database
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: ../dashboard/change_password.php");
    exit();
}

// Verify current password
if (!password_verify($current_password, $user['password_hash'])) {
    $_SESSION['error'] = "Current password is incorrect.";
    header("Location: ../dashboard/change_password.php");
    exit();
}

// Hash new password
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in users table
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
$stmt->bind_param("si", $new_password_hash, $user_id);

if ($stmt->execute()) {
    // Also update in student/teacher table if applicable
    if ($role === 'student') {
        $update_student = $conn->prepare("UPDATE student SET password = ? WHERE email = ?");
        $update_student->bind_param("ss", $new_password_hash, $email);
        $update_student->execute();
        $update_student->close();
    } elseif ($role === 'teacher') {
        $update_teacher = $conn->prepare("UPDATE teacher SET password = ? WHERE email = ?");
        $update_teacher->bind_param("ss", $new_password_hash, $email);
        $update_teacher->execute();
        $update_teacher->close();
    }

    $_SESSION['success'] = "Password changed successfully!";
    header("Location: ../dashboard/{$role}/{$role}_dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Error updating password: " . $conn->error;
    header("Location: ../dashboard/student/change_password.php");
    exit();
}

$stmt->close();
$conn->close();
?>