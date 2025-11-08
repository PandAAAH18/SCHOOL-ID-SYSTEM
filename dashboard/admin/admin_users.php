<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
  $user_id = intval($_GET['id']);
  $action = $_GET['action'];
  $admin_id = $_SESSION['user_id'];

  if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE users SET status='approved' WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Approved user ID', $user_id)");
    $_SESSION['success'] = "User approved successfully!";
  } 
  elseif ($action === 'verify') {
    $stmt = $conn->prepare("UPDATE users SET is_verified=1 WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Verified user email', $user_id)");
    $_SESSION['success'] = "User verified successfully!";
  } 
  elseif ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Deleted user account', $user_id)");
    $_SESSION['success'] = "User deleted successfully!";
  }
  elseif ($action === 'unapprove') {
    $stmt = $conn->prepare("UPDATE users SET status='pending' WHERE user_id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute(); $stmt->close();
    $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Unapproved user ID', $user_id)");
    $_SESSION['success'] = "User unapproved successfully!";
  }
  elseif ($action === 'unverify') {
    $stmt = $conn->prepare("UPDATE users SET is_verified=0 WHERE user_id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute(); $stmt->close();
    $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Unverified user email', $user_id)");
    $_SESSION['success'] = "User unverified successfully!";
  }

  header("Location: users.php");
  exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$verified_filter = $_GET['verified'] ?? '';

// Build query with filters
$query = "
    SELECT u.*, s.student_id, s.first_name, s.last_name 
    FROM users u 
    LEFT JOIN student s ON u.email = s.email 
    WHERE 1=1
";

$params = [];
$types = '';

// Add search filter
if (!empty($search)) {
    $query .= " AND (u.email LIKE ? OR u.full_name LIKE ? OR s.student_id LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

// Add role filter
if (!empty($role_filter) && $role_filter !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

// Add status filter
if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Add verified filter
if (!empty($verified_filter) && $verified_filter !== 'all') {
    if ($verified_filter === 'verified') {
        $query .= " AND u.is_verified = 1";
    } elseif ($verified_filter === 'not_verified') {
        $query .= " AND u.is_verified = 0";
    }
}

$query .= " ORDER BY u.user_id ASC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Management | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .filter-section {
        background: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>User Management</h2>
      <a href="admin.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filter-section">
      <form method="GET" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Email, Name, or Student ID" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Role</label>
          <select name="role" class="form-select">
            <option value="all">All Roles</option>
            <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="all">All Status</option>
            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Verified</label>
          <select name="verified" class="form-select">
            <option value="all">All</option>
            <option value="verified" <?= $verified_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
            <option value="not_verified" <?= $verified_filter === 'not_verified' ? 'selected' : '' ?>>Not Verified</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
          <a href="users.php" class="btn btn-outline-secondary">Clear</a>
        </div>
      </form>
    </div>

    <!-- Users Table -->
    <div class="card shadow">
      <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">👥 User Accounts</h5>
        <span class="badge bg-light text-dark">
          <?= $users->num_rows ?> user(s)
        </span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover">
            <thead class="table-dark">
              <tr>
                <th>User ID</th>
                <th>Email</th>
                <th>Name</th>
                <th>Role</th>
                <th>Student ID</th>
                <th>Verified</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($row = $users->fetch_assoc()): ?>
              <tr>
                <td><?= $row['user_id'] ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td>
                  <?= !empty($row['full_name']) ? htmlspecialchars($row['full_name']) : 
                      (!empty($row['first_name']) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Not set') ?>
                </td>
                <td>
                  <span class="badge bg-<?= $row['role'] === 'student' ? 'primary' : ($row['role'] === 'admin' ? 'dark' : 'warning') ?>">
                    <?= ucfirst($row['role']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($row['role'] === 'student'): ?>
                    <?php if (!empty($row['student_id'])): ?>
                      <span class="badge bg-success"><?= $row['student_id'] ?></span>
                    <?php else: ?>
                      <span class="badge bg-warning">Not assigned</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge bg-secondary">N/A</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($row['is_verified']): ?>
                    <span class="badge bg-success">✅ Verified</span>
                  <?php else: ?>
                    <span class="badge bg-warning">❌ Not Verified</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-<?= $row['status'] === 'approved' ? 'success' : 'warning' ?>">
                    <?= ucfirst($row['status']) ?>
                  </span>
                </td>
                <td class="text-nowrap">
                  <div class="btn-group btn-group-sm" role="group">
                    <?php if ($row['status'] === 'approved'): ?>
                      <a href="?action=unapprove&id=<?= $row['user_id'] ?>" class="btn btn-warning">Unapprove</a>
                    <?php else: ?>
                      <a href="?action=approve&id=<?= $row['user_id'] ?>" class="btn btn-success">Approve</a>
                    <?php endif; ?>

                    <?php if ($row['is_verified']): ?>
                      <a href="?action=unverify&id=<?= $row['user_id'] ?>" class="btn btn-outline-info">Unverify</a>
                    <?php else: ?>
                      <a href="?action=verify&id=<?= $row['user_id'] ?>" class="btn btn-info">Verify</a>
                    <?php endif; ?>

                    <a href="?action=delete&id=<?= $row['user_id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <?php if ($users->num_rows === 0): ?>
          <div class="text-center py-4">
            <p class="text-muted">No users found matching your criteria.</p>
            <a href="users.php" class="btn btn-primary">Clear Filters</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $stmt->close(); ?>