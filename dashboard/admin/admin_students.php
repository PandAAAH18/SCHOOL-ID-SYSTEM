<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Handle CSV Import
if (isset($_POST['import_students']) && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $import_count = 0;
        $errors = [];
        
        // Skip header row
        fgetcsv($file);
        
        while (($data = fgetcsv($file)) !== FALSE) {
            if (count($data) >= 4) {
                $email = trim($data[0]);
                $student_id = trim($data[1]);
                $first_name = trim($data[2]);
                $last_name = trim($data[3]);
                
                // Validate data
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email: $email";
                    continue;
                }
                
                if (empty($student_id)) {
                    $errors[] = "Missing Student ID for: $email";
                    continue;
                }
                
                // Check if student exists
                $check_stmt = $conn->prepare("SELECT id FROM student WHERE email = ?");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing student
                    $stmt = $conn->prepare("UPDATE student SET student_id = ?, first_name = ?, last_name = ? WHERE email = ?");
                    $stmt->bind_param("ssss", $student_id, $first_name, $last_name, $email);
                } else {
                    // Insert new student
                    $stmt = $conn->prepare("INSERT INTO student (email, student_id, first_name, last_name) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $email, $student_id, $first_name, $last_name);
                }
                
                if ($stmt->execute()) {
                    $import_count++;
                    
                    // Also update users table if email exists there
                    $update_user = $conn->prepare("UPDATE users SET full_name = ? WHERE email = ?");
                    $full_name = $first_name . ' ' . $last_name;
                    $update_user->bind_param("ss", $full_name, $email);
                    $update_user->execute();
                    $update_user->close();
                    
                    // Log activity
                    $admin_id = $_SESSION['user_id'];
                    $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Imported/updated student: $email', 0)");
                }
                $stmt->close();
                $check_stmt->close();
            }
        }
        
        fclose($file);
        
        if ($import_count > 0) {
            $_SESSION['success'] = "Successfully imported/updated $import_count student records!";
        }
        if (!empty($errors)) {
            $_SESSION['import_errors'] = $errors;
        }
    } else {
        $_SESSION['error'] = "Error uploading file. Please try again.";
    }
    header("Location: admin_students.php");
    exit();
}

// Handle individual Student ID assignment
if (isset($_GET['action']) && $_GET['action'] === 'assign_id' && isset($_GET['email'])) {
    $student_id = $_GET['student_id'];
    $email = $_GET['email'];
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE student SET student_id = ? WHERE email = ?");
    $stmt->bind_param("ss", $student_id, $email);
    
    if ($stmt->execute()) {
        $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Assigned Student ID: $student_id to $email', 0)");
        $_SESSION['success'] = "Student ID assigned successfully!";
    } else {
        $_SESSION['error'] = "Error assigning Student ID.";
    }
    $stmt->close();
    header("Location: admin_students.php");
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course'] ?? '';
$year_filter = $_GET['year_level'] ?? '';
$student_id_filter = $_GET['student_id_status'] ?? '';
$account_status_filter = $_GET['account_status'] ?? '';

// Build query with filters for all students
$query = "
    SELECT s.*, u.user_id, u.status as user_status, u.is_verified 
    FROM student s 
    LEFT JOIN users u ON s.email = u.email 
    WHERE 1=1
";

$params = [];
$types = '';

// Add search filter
if (!empty($search)) {
    $query .= " AND (s.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ssss';
}

// Add course filter
if (!empty($course_filter) && $course_filter !== 'all') {
    $query .= " AND s.course = ?";
    $params[] = $course_filter;
    $types .= 's';
}

// Add year level filter
if (!empty($year_filter) && $year_filter !== 'all') {
    $query .= " AND s.year_level = ?";
    $params[] = $year_filter;
    $types .= 's';
}

// Add student ID status filter
if (!empty($student_id_filter) && $student_id_filter !== 'all') {
    if ($student_id_filter === 'has_id') {
        $query .= " AND s.student_id IS NOT NULL";
    } elseif ($student_id_filter === 'no_id') {
        $query .= " AND s.student_id IS NULL";
    }
}

// Add account status filter
if (!empty($account_status_filter) && $account_status_filter !== 'all') {
    if ($account_status_filter === 'has_account') {
        $query .= " AND u.user_id IS NOT NULL";
    } elseif ($account_status_filter === 'no_account') {
        $query .= " AND u.user_id IS NULL";
    }
}

$query .= " ORDER BY s.student_id ASC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

// Get unique courses and year levels for filters
$courses = $conn->query("SELECT DISTINCT course FROM student WHERE course IS NOT NULL ORDER BY course");
$year_levels = $conn->query("SELECT DISTINCT year_level FROM student WHERE year_level IS NOT NULL ORDER BY year_level");

// Get students without user accounts (with same filters)
$pending_query = "
    SELECT s.* 
    FROM student s 
    LEFT JOIN users u ON s.email = u.email 
    WHERE u.email IS NULL
";

$pending_params = [];
$pending_types = '';

// Add same filters to pending students
if (!empty($search)) {
    $pending_query .= " AND (s.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
    $pending_params[] = $search_term;
    $pending_params[] = $search_term;
    $pending_params[] = $search_term;
    $pending_params[] = $search_term;
    $pending_types .= 'ssss';
}

if (!empty($course_filter) && $course_filter !== 'all') {
    $pending_query .= " AND s.course = ?";
    $pending_params[] = $course_filter;
    $pending_types .= 's';
}

if (!empty($year_filter) && $year_filter !== 'all') {
    $pending_query .= " AND s.year_level = ?";
    $pending_params[] = $year_filter;
    $pending_types .= 's';
}

if (!empty($student_id_filter) && $student_id_filter !== 'all') {
    if ($student_id_filter === 'has_id') {
        $pending_query .= " AND s.student_id IS NOT NULL";
    } elseif ($student_id_filter === 'no_id') {
        $pending_query .= " AND s.student_id IS NULL";
    }
}

$pending_query .= " ORDER BY s.student_id ASC";

$pending_stmt = $conn->prepare($pending_query);
if (!empty($pending_params)) {
    $pending_stmt->bind_param($pending_types, ...$pending_params);
}
$pending_stmt->execute();
$pending_students = $pending_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Management | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .filter-section {
        background: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0,0,0,.075);
    }
    .badge-course {
        background-color: #6f42c1;
    }
    .filter-badge {
        cursor: pointer;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Student Management</h2>
      <a href="admin.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
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

    <?php if(isset($_SESSION['import_errors'])): ?>
      <div class="alert alert-warning alert-dismissible fade show">
        <strong>Import Warnings:</strong>
        <ul class="mb-0">
          <?php foreach($_SESSION['import_errors'] as $error): ?>
            <li><?= $error ?></li>
          <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <?php unset($_SESSION['import_errors']); ?>
      </div>
    <?php endif; ?>

    <!-- Bulk Import Card -->
    <div class="card shadow mb-4" id="import">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0">📥 Bulk Import Student Data</h5>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Upload CSV File</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            <small class="form-text text-muted">
              CSV format: email,student_id,first_name,last_name<br>
              <a href="sample_students.csv" download class="btn btn-sm btn-outline-primary mt-1">📄 Download Sample CSV</a>
            </small>
          </div>
          <div class="col-md-4">
            <button type="submit" name="import_students" class="btn btn-success">Import Student Data</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Advanced Filters -->
    <div class="card shadow mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">🔍 Search & Filters</h5>
      </div>
      <div class="card-body">
        <form method="GET" class="row g-3">
          <!-- Search -->
          <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" 
                   placeholder="Search by name, email, or student ID" 
                   value="<?= htmlspecialchars($search) ?>">
          </div>
          
          <!-- Course Filter -->
          <div class="col-md-2">
            <label class="form-label">Course</label>
            <select name="course" class="form-select">
              <option value="all">All Courses</option>
              <?php while ($course = $courses->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($course['course']) ?>" 
                  <?= $course_filter === $course['course'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($course['course']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          
          <!-- Year Level Filter -->
          <div class="col-md-2">
            <label class="form-label">Year Level</label>
            <select name="year_level" class="form-select">
              <option value="all">All Years</option>
              <?php while ($year = $year_levels->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($year['year_level']) ?>" 
                  <?= $year_filter === $year['year_level'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($year['year_level']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          
          <!-- Student ID Status -->
          <div class="col-md-2">
            <label class="form-label">Student ID</label>
            <select name="student_id_status" class="form-select">
              <option value="all">All</option>
              <option value="has_id" <?= $student_id_filter === 'has_id' ? 'selected' : '' ?>>Has ID</option>
              <option value="no_id" <?= $student_id_filter === 'no_id' ? 'selected' : '' ?>>No ID</option>
            </select>
          </div>
          
          <!-- Account Status -->
          <div class="col-md-2">
            <label class="form-label">Account Status</label>
            <select name="account_status" class="form-select">
              <option value="all">All</option>
              <option value="has_account" <?= $account_status_filter === 'has_account' ? 'selected' : '' ?>>Has Account</option>
              <option value="no_account" <?= $account_status_filter === 'no_account' ? 'selected' : '' ?>>No Account</option>
            </select>
          </div>
          
          <!-- Filter Buttons -->
          <div class="col-md-12 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="admin_students.php" class="btn btn-outline-secondary">Clear All</a>
            
            <!-- Active Filter Badges -->
            <?php if (!empty($search) || !empty($course_filter) || !empty($year_filter) || !empty($student_id_filter) || !empty($account_status_filter)): ?>
              <div class="ms-3">
                <small class="text-muted me-2">Active filters:</small>
                <?php if (!empty($search)): ?>
                  <span class="badge bg-info filter-badge">
                    Search: "<?= htmlspecialchars($search) ?>"
                    <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="text-white ms-1">×</a>
                  </span>
                <?php endif; ?>
                <?php if (!empty($course_filter) && $course_filter !== 'all'): ?>
                  <span class="badge bg-primary filter-badge">
                    Course: <?= htmlspecialchars($course_filter) ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['course' => 'all'])) ?>" class="text-white ms-1">×</a>
                  </span>
                <?php endif; ?>
                <?php if (!empty($year_filter) && $year_filter !== 'all'): ?>
                  <span class="badge bg-success filter-badge">
                    Year: <?= htmlspecialchars($year_filter) ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['year_level' => 'all'])) ?>" class="text-white ms-1">×</a>
                  </span>
                <?php endif; ?>
                <?php if (!empty($student_id_filter) && $student_id_filter !== 'all'): ?>
                  <span class="badge bg-warning filter-badge">
                    ID: <?= $student_id_filter === 'has_id' ? 'Has ID' : 'No ID' ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['student_id_status' => 'all'])) ?>" class="text-white ms-1">×</a>
                  </span>
                <?php endif; ?>
                <?php if (!empty($account_status_filter) && $account_status_filter !== 'all'): ?>
                  <span class="badge bg-danger filter-badge">
                    Account: <?= $account_status_filter === 'has_account' ? 'Has Account' : 'No Account' ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['account_status' => 'all'])) ?>" class="text-white ms-1">×</a>
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- All Students -->
    <div class="card shadow mb-4">
      <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">🎓 All Students</h5>
        <span class="badge bg-light text-dark">
          <?= $students->num_rows ?> student(s) found
        </span>
      </div>
      <div class="card-body">
        <?php if ($students->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>Student ID</th>
                  <th>Email</th>
                  <th>Name</th>
                  <th>Course</th>
                  <th>Year Level</th>
                  <th>User Account</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php while ($student = $students->fetch_assoc()): ?>
                <tr>
                  <td>
                    <?php if (!empty($student['student_id'])): ?>
                      <span class="badge bg-success"><?= $student['student_id'] ?></span>
                    <?php else: ?>
                      <span class="badge bg-warning">Not assigned</span>
                      <br>
                      <small>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#assignIdModal" 
                           data-email="<?= $student['email'] ?>"
                           class="text-decoration-none">Assign ID</a>
                      </small>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($student['email']) ?></td>
                  <td>
                    <?= !empty($student['first_name']) ? htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) : 'Name not set' ?>
                  </td>
                  <td>
                    <?php if (!empty($student['course'])): ?>
                      <span class="badge badge-course"><?= htmlspecialchars($student['course']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">Not set</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($student['year_level'])): ?>
                      <span class="badge bg-info"><?= htmlspecialchars($student['year_level']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">Not set</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($student['user_id']): ?>
                      <span class="badge bg-success">✅ Registered</span>
                      <br>
                      <small class="text-muted">
                        Status: <?= $student['user_status'] ?><br>
                        Verified: <?= $student['is_verified'] ? 'Yes' : 'No' ?>
                      </small>
                    <?php else: ?>
                      <span class="badge bg-warning">❌ No Account</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="student_details.php?id=<?= $student['id'] ?>" class="btn btn-info">View</a>
                      <a href="#" class="btn btn-warning">Edit</a>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <p class="text-muted">No students found matching your criteria.</p>
            <a href="admin_students.php" class="btn btn-primary">Clear Filters</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Pending Students (No User Accounts) -->
    <?php if ($pending_students->num_rows > 0): ?>
    <div class="card shadow">
      <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">📋 Students Without User Accounts</h5>
        <span class="badge bg-light text-dark">
          <?= $pending_students->num_rows ?> student(s)
        </span>
      </div>
      <div class="card-body">
        <div class="alert alert-info">
          <strong>Note:</strong> These students are enrolled in the system but haven't created user accounts yet.
        </div>
        
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover">
            <thead class="table-warning">
              <tr>
                <th>Student ID</th>
                <th>Email</th>
                <th>Name</th>
                <th>Course</th>
                <th>Year Level</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($student = $pending_students->fetch_assoc()): ?>
              <tr>
                <td>
                  <?php if (!empty($student['student_id'])): ?>
                    <span class="badge bg-success"><?= $student['student_id'] ?></span>
                  <?php else: ?>
                    <span class="badge bg-warning">Not assigned</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($student['email']) ?></td>
                <td>
                  <?= !empty($student['first_name']) ? htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) : 'Name not set' ?>
                </td>
                <td>
                  <?php if (!empty($student['course'])): ?>
                    <span class="badge badge-course"><?= htmlspecialchars($student['course']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Not set</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($student['year_level'])): ?>
                    <span class="badge bg-info"><?= htmlspecialchars($student['year_level']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Not set</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-danger">No User Account</span>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Assign Student ID Modal -->
  <div class="modal fade" id="assignIdModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Student ID</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="GET" action="">
          <div class="modal-body">
            <input type="hidden" name="action" value="assign_id">
            <input type="hidden" name="email" id="modal_email">
            
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="text" class="form-control" id="display_email" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Student ID</label>
              <input type="text" name="student_id" class="form-control" required placeholder="Enter official Student ID">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Assign ID</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Modal functionality
    const assignIdModal = document.getElementById('assignIdModal');
    assignIdModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const email = button.getAttribute('data-email');
      
      document.getElementById('modal_email').value = email;
      document.getElementById('display_email').value = email;
    });
  </script>
</body>
</html>

<?php 
$stmt->close();
if (isset($pending_stmt)) {
    $pending_stmt->close();
}
?>