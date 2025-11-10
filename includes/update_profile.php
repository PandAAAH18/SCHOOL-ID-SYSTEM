<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: ../index.php");
    exit();
}
$student_id = (int)$_SESSION['student_id'];

// Get student email for COR filename
$email_stmt = $conn->prepare("SELECT email FROM student WHERE id = ?");
$email_stmt->bind_param("i", $student_id);
$email_stmt->execute();
$email_result = $email_stmt->get_result();
$student_data = $email_result->fetch_assoc();
$student_email = $student_data['email'];
$email_stmt->close();

/* ---------- helper ---------- */
function uploadFile($field, $allowed, $maxBytes, &$destName, $subFolder, $useEmail = false, $email = '')
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return true; // skip

    $file = $_FILES[$field];
    if (!in_array($file['type'], $allowed)) return false;
    if ($file['size'] > $maxBytes) return false;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if ($useEmail && $email) {
        // Use email and timestamp for COR files
        $clean_email = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $email);
        $destName = $field . '_' . $clean_email . '_' . time() . '.' . $ext;
    } else {
        // Use student_id for other files
        global $student_id;
        $destName = $field . '_' . $student_id . '_' . time() . '.' . $ext;
    }
    
    $dir = __DIR__ . '/../uploads/' . $subFolder . '/';

    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return move_uploaded_file($file['tmp_name'], $dir . $destName);
}

/* ---------- basic data ---------- */
$first_name      = mysqli_real_escape_string($conn, $_POST['first_name']);
$last_name       = mysqli_real_escape_string($conn, $_POST['last_name']);
$year_level      = mysqli_real_escape_string($conn, $_POST['year_level']);
$course          = mysqli_real_escape_string($conn, $_POST['course']);
$contact_number  = mysqli_real_escape_string($conn, $_POST['contact_number']);
$address         = mysqli_real_escape_string($conn, $_POST['address']);

/* ---------- uploads ---------- */
$allowed = ['image/jpeg','image/png','image/jpg','image/gif'];
$max     = 2 * 1024 * 1024; // 2 MB

$photo_path = $cor_path = $signature_path = null;

/* photo */
if (!uploadFile('photo', $allowed, $max, $photo_path, 'student_photos', false)) {
    $_SESSION['error'] = 'Photo upload failed (≤2 MB, jpg/png/gif only).';
    header("Location: ../complete_profile.php"); exit();
}

/* COR - using email and timestamp */
if (!uploadFile('cor', $allowed, $max, $cor_path, 'student_cors', true, $student_email)) {
    $_SESSION['error'] = 'COR upload failed (≤2 MB, jpg/png/gif only).';
    header("Location: ../complete_profile.php"); exit();
}

/* signature */
if (!uploadFile('signature', $allowed, $max, $signature_path, 'student_signatures', false)) {
    $_SESSION['error'] = 'Signature upload failed (≤2 MB, jpg/png/gif only).';
    header("Location: ../complete_profile.php"); exit();
}

/* ---------- build dynamic update ---------- */
$fields = ["first_name=?", "last_name=?", "year_level=?", "course=?", "contact_number=?", "address=?"];
$values = [$first_name, $last_name, $year_level, $course, $contact_number, $address];
$types  = "ssssss";

if ($photo_path)     { $fields[] = "photo=?";     $values[] = $photo_path;     $types .= "s"; }
if ($cor_path)       { $fields[] = "cor=?";       $values[] = $cor_path;       $types .= "s"; }
if ($signature_path) { $fields[] = "signature=?"; $values[] = $signature_path; $types .= "s"; }

$values[] = $student_id;   // last placeholder for WHERE
$types   .= "i";

$sql = "UPDATE student SET " . implode(", ", $fields) . " WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    $_SESSION['success'] = "Profile updated successfully!";
    unset($_SESSION['student_id']);
    header("Location: ../dashboard/student_dashboard.php");
} else {
    $_SESSION['error'] = "DB error: " . $conn->error;
    header("Location: ../complete_profile.php");
}
?>