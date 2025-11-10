<?php
require_once "../../includes/db_connect.php";
session_start();

/* ---------- auth ---------- */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); 
    exit();
}

$admin_id = $_SESSION['user_id'];

/* ---------- handle bulk generation ---------- */
if (isset($_POST['generate_bulk'])) {
    $selected_students = $_POST['selected_students'] ?? [];
    
    if (empty($selected_students)) {
        $_SESSION['error'] = "No students selected for bulk generation.";
        header("Location: generate_digital_bulk.php");
        exit();
    }

    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($selected_students as $student_id) {
        $student_id = intval($student_id);
        
        if ($student_id <= 0) continue;
        
        // Check if digital ID already exists
        $check_stmt = $conn->prepare("SELECT id, first_name, last_name, digital_id_path FROM student WHERE id = ?");
        $check_stmt->bind_param("i", $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $student_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if (!$student_data) {
            $error_count++;
            $errors[] = "Student not found (ID: $student_id)";
            continue;
        }
        
        if (empty($student_data['digital_id_path'])) {
            // Use direct image generation approach
            $result = generateDigitalIDDirect($student_id, $conn);
            
            if ($result) {
                $success_count++;
                $log_msg = "Generated digital ID for student: {$student_data['first_name']} {$student_data['last_name']}";
                $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, '$log_msg', $student_id)");
            } else {
                $error_count++;
                $errors[] = "Failed to generate ID for {$student_data['first_name']} {$student_data['last_name']}";
            }
        } else {
            // Digital ID already exists
            $error_count++;
            $errors[] = "Digital ID already exists for {$student_data['first_name']} {$student_data['last_name']}";
        }
    }
    
    if ($success_count > 0) {
        $_SESSION['success'] = "Successfully generated $success_count digital ID(s).";
    }
    if ($error_count > 0) {
        $error_msg = "$error_count student(s) failed. " . implode(', ', array_slice($errors, 0, 3));
        if (count($errors) > 3) {
            $error_msg .= " and " . (count($errors) - 3) . " more";
        }
        $_SESSION['error'] = $error_msg;
    }
    
    header("Location: generate_digital_bulk.php");
    exit();
}

/* ---------- function to generate digital ID directly ---------- */
function generateDigitalIDDirect($student_id, $conn) {
    // Get complete student data
    $stmt = $conn->prepare(
        "SELECT s.id, s.first_name, s.last_name, s.student_id, s.course, s.year_level,
                s.photo, s.blood_type, s.emergency_contact, s.signature
         FROM student s WHERE s.id = ?"
    );
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$student) return false;
    
    // File paths
    $photo_path = $student['photo'] ? "../../uploads/student_photos/" . $student['photo']
                                    : "../../assets/img/default_user.png";
    $sign_path  = $student['signature'] ? "../../uploads/student_signatures/" . $student['signature']
                                        : "../../assets/img/default_sign.png";
    $bg_path    = "../../assets/img/bg.jpg";
    $logo_path  = "../../assets/img/kldlogo.png";
    
    $outDir = "../../uploads/digital_ids/";
    if (!is_dir($outDir)) mkdir($outDir, 0755, true);
    
    $filename = "digital_id_{$student_id}.jpg";
    $filepath = $outDir . $filename;
    
    // Create image using GD (simpler approach)
    return createDigitalIDImageGD($student, $filepath, $filename, $photo_path, $sign_path, $bg_path, $logo_path, $conn);
}

/* ---------- function to create digital ID image using GD ---------- */
function createDigitalIDImageGD($student, $filepath, $filename, $photo_path, $sign_path, $bg_path, $logo_path, $conn) {
    $width = 1050;
    $height = 672;
    
    // Create image
    $image = imagecreatetruecolor($width, $height);
    
    // Allocate colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 0, 0, 255);
    $dark_blue = imagecolorallocate($image, 0, 51, 102);
    
    // Fill background
    imagefill($image, 0, 0, $white);
    
    // Card dimensions (same as generate_digital_id.php)
    $card_width = 336;
    $card_height = 504;
    $gap = 40;
    $card1_x = ($width - ($card_width * 2 + $gap)) / 2;
    $card2_x = $card1_x + $card_width + $gap;
    $card_y = ($height - $card_height) / 2;
    
    // Draw card backgrounds with borders
    imagefilledrectangle($image, $card1_x, $card_y, $card1_x + $card_width, $card_y + $card_height, $white);
    imagerectangle($image, $card1_x, $card_y, $card1_x + $card_width, $card_y + $card_height, $black);
    
    imagefilledrectangle($image, $card2_x, $card_y, $card2_x + $card_width, $card_y + $card_height, $white);
    imagerectangle($image, $card2_x, $card_y, $card2_x + $card_width, $card_y + $card_height, $black);
    
    // FRONT CARD CONTENT
    // Add logo area
    $logo_text = "KLD LOGO";
    imagestring($image, 3, $card1_x + 120, $card_y + 30, $logo_text, $dark_blue);
    
    // Add photo area
    $photo_x = $card1_x + 108;
    $photo_y = $card_y + 90;
    $photo_width = 120;
    $photo_height = 150;
    
    // Draw photo placeholder
    imagefilledrectangle($image, $photo_x, $photo_y, $photo_x + $photo_width, $photo_y + $photo_height, $white);
    imagerectangle($image, $photo_x, $photo_y, $photo_x + $photo_width, $photo_y + $photo_height, $black);
    imagestring($image, 2, $photo_x + 40, $photo_y + 65, "PHOTO", $black);
    
    // Add student name
    $name = $student['first_name'] . ' ' . $student['last_name'];
    $name_x = $card1_x + 50;
    $name_y = $card_y + 260;
    imagestring($image, 3, $name_x, $name_y, $name, $black);
    
    // Add student ID
    $id_text = "ID: " . $student['student_id'];
    imagestring($image, 2, $name_x, $name_y + 25, $id_text, $black);
    
    // Add course info
    $course_text = $student['course'] . " - Year " . $student['year_level'];
    imagestring($image, 2, $name_x, $name_y + 50, $course_text, $black);
    
    // Add blood type
    $blood_text = "Blood: " . ($student['blood_type'] ?: 'N/A');
    imagestring($image, 2, $name_x, $name_y + 80, $blood_text, $black);
    
    // Add emergency contact
    $emergency_text = "Emergency: " . ($student['emergency_contact'] ?: 'N/A');
    imagestring($image, 2, $name_x, $name_y + 105, $emergency_text, $black);
    
    // BACK CARD CONTENT
    // Add important text
    $back_x = $card2_x + 50;
    $back_y = $card_y + 100;
    
    imagestring($image, 3, $back_x + 80, $back_y, "IMPORTANT", $black);
    imagestring($image, 2, $back_x, $back_y + 40, "This card is property of", $black);
    imagestring($image, 2, $back_x, $back_y + 60, "the school.", $black);
    imagestring($image, 2, $back_x, $back_y + 90, "If found, please return", $black);
    imagestring($image, 2, $back_x, $back_y + 110, "to the registrar.", $black);
    
    // Add validity
    $valid_text = "Valid for A.Y. 2025-2026";
    imagestring($image, 2, $back_x + 40, $back_y + 150, $valid_text, $black);
    
    // Add signature area
    $sign_x = $back_x + 68;
    $sign_y = $back_y + 200;
    imagefilledrectangle($image, $sign_x, $sign_y, $sign_x + 200, $sign_y + 40, $white);
    imagerectangle($image, $sign_x, $sign_y, $sign_x + 200, $sign_y + 40, $black);
    
    if (empty($student['signature'])) {
        $sign_text = $student['first_name'] . ' ' . $student['last_name'];
        imagestring($image, 2, $sign_x + 20, $sign_y + 15, $sign_text, $black);
    } else {
        imagestring($image, 2, $sign_x + 70, $sign_y + 15, "[SIGNATURE]", $black);
    }
    
    // Add barcode area
    $barcode_x = $back_x + 68;
    $barcode_y = $back_y + 260;
    imagefilledrectangle($image, $barcode_x, $barcode_y, $barcode_x + 200, $barcode_y + 40, $white);
    imagerectangle($image, $barcode_x, $barcode_y, $barcode_x + 200, $barcode_y + 40, $black);
    imagestring($image, 2, $barcode_x + 70, $barcode_y + 15, "[BARCODE]", $black);
    
    // Save image
    $image_saved = imagejpeg($image, $filepath, 90);
    imagedestroy($image);
    
    if ($image_saved) {
        // Update database
        $update_stmt = $conn->prepare(
            "UPDATE student SET digital_id_path = ?, digital_id_generated_at = NOW() WHERE id = ?"
        );
        $update_stmt->bind_param("si", $filename, $student['id']);
        $result = $update_stmt->execute();
        $update_stmt->close();
        
        return $result;
    }
    
    return false;
}

/* ---------- filters / search ---------- */
$status_filter = $_GET['status'] ?? 'approved';
$search = $_GET['search'] ?? '';
$request_type = $_GET['request_type'] ?? 'all';

$params = []; 
$types = '';
$query = "SELECT DISTINCT s.id, s.first_name, s.last_name, s.student_id, s.course, s.year_level,
                 s.email, s.photo, s.digital_id_path, s.digital_id_generated_at,
                 ir.status, ir.request_type, ir.created_at
          FROM student s 
          JOIN id_requests ir ON s.id = ir.student_id 
          WHERE 1=1";

if ($status_filter !== 'all') {
    $query .= " AND ir.status = ?"; 
    $params[] = $status_filter; 
    $types .= 's';
}

if ($search) {
    $like = "%$search%";
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.student_id LIKE ?)";
    array_push($params, $like, $like, $like, $like); 
    $types .= 'ssss';
}

if ($request_type !== 'all') {
    $query .= " AND ir.request_type = ?"; 
    $params[] = $request_type; 
    $types .= 's';
}

$query .= " ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($query);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

/* ---------- stats ---------- */
$stats_query = "
    SELECT 
        COUNT(DISTINCT s.id) AS total_students,
        COUNT(DISTINCT CASE WHEN s.digital_id_path IS NOT NULL THEN s.id END) AS with_digital_id,
        COUNT(DISTINCT CASE WHEN s.digital_id_path IS NULL THEN s.id END) AS without_digital_id
    FROM student s 
    JOIN id_requests ir ON s.id = ir.student_id 
    WHERE ir.status = 'approved'
";

$stats_params = [];
$stats_types = '';

if ($search) {
    $stats_query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.student_id LIKE ?)";
    $like = "%$search%";
    $stats_params = [$like, $like, $like, $like];
    $stats_types = 'ssss';
}

$stats_stmt = $conn->prepare($stats_query);
if ($stats_types && !empty($stats_params)) {
    $stats_stmt->bind_param($stats_types, ...$stats_params);
}
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Ensure stats are set
$stats = array_merge([
    'total_students' => 0,
    'with_digital_id' => 0,
    'without_digital_id' => 0
], $stats ?: []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Digital ID Generation | School ID System</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <style>
        .student-card {
            transition: transform 0.2s;
            border-left: 4px solid #0d6efd;
        }
        .student-card:hover {
            transform: translateY(-2px);
        }
        .has-digital-id {
            border-left-color: #198754;
        }
        .no-digital-id {
            border-left-color: #ffc107;
        }
        .student-photo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .stats-card {
            text-align: center;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .form-check-input:disabled {
            background-color: #e9ecef;
            opacity: 0.6;
        }
        .form-check-input:disabled + label {
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../../includes/header_admin.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Bulk Digital ID Generation</h2>
            <a href="admin_id.php" class="btn btn-secondary btn-sm">← Back to ID Management</a>
        </div>

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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-primary">
                    <div class="card-body stats-card">
                        <div class="stats-number"><?= $stats['total_students'] ?></div>
                        <p class="mb-0">Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-success">
                    <div class="card-body stats-card">
                        <div class="stats-number"><?= $stats['with_digital_id'] ?></div>
                        <p class="mb-0">With Digital ID</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-warning">
                    <div class="card-body stats-card">
                        <div class="stats-number"><?= $stats['without_digital_id'] ?></div>
                        <p class="mb-0">Without Digital ID</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🔍 Filter Students</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by name, email, or student ID" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Request Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Request Type</label>
                        <select name="request_type" class="form-select">
                            <option value="all" <?= $request_type === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="new" <?= $request_type === 'new' ? 'selected' : '' ?>>New ID</option>
                            <option value="replacement" <?= $request_type === 'replacement' ? 'selected' : '' ?>>Replacement</option>
                            <option value="update" <?= $request_type === 'update' ? 'selected' : '' ?>>Update</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                        <a href="generate_digital_bulk.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions Form -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions">
                <div class="d-flex align-items-center gap-3">
                    <button type="submit" name="generate_bulk" class="btn btn-primary btn-sm"
                        onclick="return confirmBulkAction()">
                        Generate Digital IDs for Selected
                    </button>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">Select All (Without Digital IDs)</label>
                    </div>
                    <small class="text-muted">Select students without digital IDs using checkboxes below</small>
                </div>
            </div>

            <!-- Students List -->
            <div class="card shadow">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">🎓 Students</h5>
                    <span class="badge bg-light text-dark">
                        <?= $students->num_rows ?> student(s)
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($students->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($student = $students->fetch_assoc()): 
                            $hasDigitalID = !empty($student['digital_id_path']);
                            $checkboxId = 'student_' . $student['id'];
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card student-card h-100 <?= $hasDigitalID ? 'has-digital-id' : 'no-digital-id' ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= $student['photo'] ? '../../uploads/student_photos/' . htmlspecialchars($student['photo']) : '../../assets/img/default_user.png' ?>"
                                                alt="Student Photo" class="student-photo me-3">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                                </h6>
                                                <p class="text-muted mb-1"><?= $student['email'] ?></p>
                                                <span class="badge bg-secondary"><?= $student['student_id'] ?></span>
                                            </div>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input student-checkbox" type="checkbox" 
                                                name="selected_students[]" 
                                                value="<?= $student['id'] ?>" 
                                                id="<?= $checkboxId ?>"
                                                <?= $hasDigitalID ? 'disabled' : '' ?>>
                                            <label class="form-check-label" for="<?= $checkboxId ?>">
                                                <?= $hasDigitalID ? 'Already Generated' : 'Select' ?>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-2">
                                                <strong>Course:</strong> <?= htmlspecialchars($student['course'] ?? 'Not set') ?><br>
                                                <strong>Year:</strong> <?= htmlspecialchars($student['year_level'] ?? 'Not set') ?><br>
                                                <strong>Status:</strong> 
                                                <span class="badge bg-<?= 
                                                    $student['status'] === 'approved' ? 'success' : 
                                                    ($student['status'] === 'completed' ? 'info' : 'warning')
                                                ?>">
                                                    <?= ucfirst($student['status']) ?>
                                                </span><br>
                                                <strong>Digital ID:</strong> 
                                                <span class="badge bg-<?= $hasDigitalID ? 'success' : 'warning' ?>">
                                                    <?= $hasDigitalID ? 'Generated' : 'Not Generated' ?>
                                                </span>
                                            </div>

                                            <?php if ($hasDigitalID && $student['digital_id_generated_at']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    Generated: <?= date('M j, Y g:i A', strtotime($student['digital_id_generated_at'])) ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>

                                            <small class="text-muted">
                                                Request Type: <?= ucfirst($student['request_type']) ?> • 
                                                Submitted: <?= date('M j, Y', strtotime($student['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-3">
                                        <div class="btn-group btn-group-sm w-100">
                                            <?php if ($hasDigitalID): ?>
                                            <a href="../../uploads/digital_ids/<?= htmlspecialchars($student['digital_id_path']) ?>" 
                                               class="btn btn-success" target="_blank">View Digital ID</a>
                                            <a href="generate_digital_id.php?student_id=<?= $student['id'] ?>" 
                                               class="btn btn-outline-primary">Regenerate</a>
                                            <?php else: ?>
                                            <a href="generate_digital_id.php?student_id=<?= $student['id'] ?>" 
                                               class="btn btn-primary">Generate Digital ID</a>
                                            <?php endif; ?>
                                            <a href="student_details.php?id=<?= $student['id'] ?>" 
                                               class="btn btn-outline-dark">View Student</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No students found matching your criteria.</p>
                        <a href="generate_digital_bulk.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    // Select all checkboxes (only enabled ones)
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Confirm bulk action
    function confirmBulkAction() {
        const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
        
        if (selectedCount === 0) {
            alert('Please select at least one student to generate digital IDs for.');
            return false;
        }
        
        return confirm(`Are you sure you want to generate digital IDs for ${selectedCount} selected student(s)?`);
    }

    // Update select all checkbox state when individual checkboxes change
    document.querySelectorAll('.student-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const enabledCheckboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
            const checkedEnabledCheckboxes = document.querySelectorAll('.student-checkbox:not(:disabled):checked');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            selectAllCheckbox.checked = (enabledCheckboxes.length > 0 && checkedEnabledCheckboxes.length === enabledCheckboxes.length);
            selectAllCheckbox.indeterminate = (checkedEnabledCheckboxes.length > 0 && checkedEnabledCheckboxes.length < enabledCheckboxes.length);
        });
    });
    </script>
</body>
</html>

<?php 
if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
?>