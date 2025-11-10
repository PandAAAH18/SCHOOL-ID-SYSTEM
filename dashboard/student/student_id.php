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

// Check if profile is complete for ID issuance
$profile_complete = true;
$missing_fields = [];

$required_fields = [
    'first_name', 'last_name', 'student_id', 'course', 'year_level',
    'contact_number', 'emergency_contact', 'address', 'photo'
];

foreach ($required_fields as $field) {
    if (empty($student[$field])) {
        $profile_complete = false;
        $missing_fields[] = $field;
    }
}

// If profile not complete, redirect to complete profile
if (!$profile_complete) {
    $_SESSION['missing_fields'] = $missing_fields;
    header("Location: edit_student_profile.php?incomplete=1");
    exit();
}

// Store in session for header_student.php to use
$_SESSION['student_first_name'] = $student['first_name'] ?? '';
$_SESSION['student_last_name'] = $student['last_name'] ?? '';
$_SESSION['student_photo'] = $student['photo'] ?? '';

// Check for existing ID requests
$request_stmt = $conn->prepare("SELECT * FROM id_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$request_stmt->bind_param("i", $student['id']);
$request_stmt->execute();
$request_result = $request_stmt->get_result();
$current_request = $request_result->fetch_assoc();
$request_stmt->close();

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_type'])) {
    $request_type = $_POST['request_type'];
    $reason = $_POST['reason'] ?? '';
    
    // Check if there's already a pending request
    $check_stmt = $conn->prepare("SELECT id FROM id_requests WHERE student_id = ? AND status = 'pending'");
    $check_stmt->bind_param("i", $student['id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "You already have a pending ID request. Please wait for it to be processed.";
    } else {
        // Insert new request
        $insert_stmt = $conn->prepare("INSERT INTO id_requests (student_id, request_type, reason, status) VALUES (?, ?, ?, 'pending')");
        $insert_stmt->bind_param("iss", $student['id'], $request_type, $reason);
        
        if ($insert_stmt->execute()) {
            $success_message = "ID request submitted successfully! Your request is now pending approval.";
            // Refresh current request
            $request_stmt = $conn->prepare("SELECT * FROM id_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
            $request_stmt->bind_param("i", $student['id']);
            $request_stmt->execute();
            $request_result = $request_stmt->get_result();
            $current_request = $request_result->fetch_assoc();
            $request_stmt->close();
        } else {
            $error_message = "Error submitting request. Please try again.";
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}

// Combine name
$full_name = trim($student['first_name'] . ' ' . $student['last_name']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Request Student ID | School ID System</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <script>
    function toggleReasonField() {
        const requestType = document.getElementById('request_type').value;
        const reasonField = document.getElementById('reason_field');

        if (requestType === 'replacement' || requestType === 'update') {
            reasonField.style.display = 'block';
            document.getElementById('reason').setAttribute('required', 'required');
        } else {
            reasonField.style.display = 'none';
            document.getElementById('reason').removeAttribute('required');
        }
    }
    </script>
</head>

<body class="bg-light">

    <?php include '../../includes/header_student.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Request Student ID</h2>
            <a href="student_dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ID Request Form -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Request ID Card</h5>
            </div>
            <div class="card-body">
                <?php if (!$current_request || $current_request['status'] !== 'pending'): ?>
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Full Name</strong></label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($full_name) ?>"
                                readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Student ID</strong></label>
                            <input type="text" class="form-control"
                                value="<?= htmlspecialchars($student['student_id']) ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Email</strong></label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>"
                                readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Request Type</strong></label>
                            <select class="form-select" name="request_type" id="request_type"
                                onchange="toggleReasonField()" required>
                                <option value="">Select request type</option>
                                <option value="new">New ID Card</option>
                                <option value="replacement">Replacement (Lost/Damaged)</option>
                                <option value="update">Update Information</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="reason_field" style="display: none;">
                        <label class="form-label"><strong>Reason for Request</strong></label>
                        <textarea class="form-control" name="reason" id="reason" rows="3"
                            placeholder="Please provide additional details about your request..."></textarea>
                        <div class="form-text">
                            For replacement: Please explain what happened to your previous ID<br>
                            For update: Please specify what information needs to be updated
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Current Photo</strong></label>
                        <div class="text-center">
                            <img src="<?= $student['photo'] ? '../../uploads/student_photos/' . htmlspecialchars($student['photo']) : '../../assets/img/default_user.png' ?>"
                                alt="Profile Photo" class="rounded-circle mb-2" width="120" height="120"
                                style="object-fit: cover;">
                            <p class="text-muted">This photo will be used for your ID card</p>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> Your request will be reviewed by administration. You will be notified
                        once your ID is ready for pickup.
                    </div>

                    <button type="submit" class="btn btn-success">Submit Request</button>
                    <a href="student.php" class="btn btn-secondary">Cancel</a>
                </form>
                <?php else: ?>
                <div class="alert alert-warning">
                    <h6>You have a pending ID request</h6>
                    <p class="mb-1"><strong>Request Type:</strong> <?= ucfirst($current_request['request_type']) ?></p>
                    <p class="mb-1"><strong>Submitted:</strong>
                        <?= date('F j, Y g:i A', strtotime($current_request['created_at'])) ?></p>
                    <p class="mb-0"><strong>Status:</strong> <span class="badge bg-warning">Pending Approval</span></p>
                </div>
                <p>Please wait for your current request to be processed before submitting a new one.</p>
                <a href="student.php" class="btn btn-primary">Back to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current ID Status -->
        <div class="card shadow mb-4">
            <div class="card-header bg-dark text-white">Current ID Status</div>
            <div class="card-body">
                <?php if ($current_request): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Request ID:</strong> #<?= $current_request['id'] ?></p>
                        <p><strong>Type:</strong> <?= ucfirst($current_request['request_type']) ?></p>
                        <p><strong>Submitted:</strong>
                            <?= date('F j, Y g:i A', strtotime($current_request['created_at'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong>
                            <span class="badge 
                  <?= $current_request['status'] == 'approved' ? 'bg-success' : '' ?>
                  <?= $current_request['status'] == 'pending' ? 'bg-warning' : '' ?>
                  <?= $current_request['status'] == 'rejected' ? 'bg-danger' : '' ?>
                  <?= $current_request['status'] == 'completed' ? 'bg-info' : '' ?>">
                                <?= ucfirst($current_request['status']) ?>
                            </span>
                        </p>
                        <?php if ($current_request['reason']): ?>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($current_request['reason']) ?></p>
                        <?php endif; ?>
                        <?php if ($current_request['admin_notes']): ?>
                        <p><strong>Admin Notes:</strong> <?= htmlspecialchars($current_request['admin_notes']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted">No ID requests found. Submit a request above to get started.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Digital ID Card Preview -->
        <!-- ========== Digital ID Card ========== -->
        <div class="col-md-6">
            <div class="card info-card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Digital ID Card</h5>
                </div>
                <div class="card-body text-center">
                    <?php
      $idFile  = $student['digital_id_path'] ?? '';          // DB column
      $idPath  = "../../uploads/digital_ids/" . $idFile;     // disk location
      $hasID   = $idFile && file_exists($idPath);
      ?>
                    <div class="mb-3">
                        <img src="<?= $hasID ? htmlspecialchars($idPath) : '../../assets/img/id_placeholder.png' ?>"
                            alt="Digital ID Card" class="img-fluid border rounded" style="max-width:300px">
                    </div>

                    <?php if ($hasID): ?>
                    <div class="btn-group">
                        <a href="<?= htmlspecialchars($idPath) ?>" download class="btn btn-primary btn-sm">
                            Download</a>
                        <a href="<?= htmlspecialchars($idPath) ?>" target="_blank"
                            class="btn btn-outline-primary btn-sm">View Full Size</a>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Last generated: <?= date('M j, Y g:i A', strtotime($student['digital_id_generated_at'])) ?>
                    </small>
                    <?php else: ?>
                    <p class="text-muted mb-0">No digital ID generated yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>