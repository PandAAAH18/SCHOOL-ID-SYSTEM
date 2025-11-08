<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Get statistics for dashboard
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
        (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
        (SELECT COUNT(*) FROM student s LEFT JOIN users u ON s.email = u.email WHERE u.email IS NULL) as pending_students,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'pending') as pending_id_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'completed') as completed_id_requests,
        (SELECT COUNT(*) FROM student WHERE student_id IS NOT NULL) as students_with_ids
")->fetch_assoc();

// Get recent activity
$recent_activity = $conn->query("
    SELECT al.*, u.email 
    FROM activity_logs al 
    LEFT JOIN users u ON al.target_user = u.user_id 
    ORDER BY al.created_at DESC 
    LIMIT 5
");

// Get pending ID requests
$pending_requests = $conn->query("
    SELECT ir.*, s.first_name, s.last_name, s.student_id, s.email 
    FROM id_requests ir 
    JOIN student s ON ir.student_id = s.id 
    WHERE ir.status = 'pending' 
    ORDER BY ir.created_at DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .stats-card {
        transition: transform 0.2s;
        height: 100%;
    }
    .stats-card:hover {
        transform: translateY(-2px);
    }
    .quick-action-card {
        border-left: 4px solid #0d6efd;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Admin Dashboard</h2>
      <div class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
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
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-primary">
          <div class="card-body text-center">
            <h2><?= $stats['total_users'] ?></h2>
            <p class="mb-0">Total Users</p>
            <small><?= $stats['total_students'] ?> students, <?= $stats['total_admins'] ?> admins</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-success">
          <div class="card-body text-center">
            <h2><?= $stats['students_with_ids'] ?></h2>
            <p class="mb-0">Students with IDs</p>
            <small><?= $stats['completed_id_requests'] ?> IDs issued</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-warning">
          <div class="card-body text-center">
            <h2><?= $stats['pending_users'] + $stats['pending_students'] ?></h2>
            <p class="mb-0">Pending Actions</p>
            <small><?= $stats['pending_users'] ?> users, <?= $stats['pending_students'] ?> students</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-info">
          <div class="card-body text-center">
            <h2><?= $stats['pending_id_requests'] ?></h2>
            <p class="mb-0">ID Requests</p>
            <small>Awaiting processing</small>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Quick Actions -->
      <div class="col-md-6 mb-4">
        <div class="card shadow">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0">🚀 Quick Actions</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="card quick-action-card">
                  <div class="card-body">
                    <h6>User Management</h6>
                    <p class="text-muted small">Approve, verify, and manage users</p>
                    <a href="users.php" class="btn btn-primary btn-sm">Manage Users</a>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card quick-action-card">
                  <div class="card-body">
                    <h6>Student Records</h6>
                    <p class="text-muted small">View and manage student data</p>
                    <a href="students.php" class="btn btn-success btn-sm">View Students</a>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card quick-action-card">
                  <div class="card-body">
                    <h6>Bulk Import</h6>
                    <p class="text-muted small">Import student data from CSV</p>
                    <a href="students.php#import" class="btn btn-info btn-sm">Import Data</a>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card quick-action-card">
                  <div class="card-body">
                    <h6>Reports</h6>
                    <p class="text-muted small">View system analytics</p>
                    <a href="reports.php" class="btn btn-warning btn-sm">View Reports</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="col-md-6 mb-4">
        <div class="card shadow">
          <div class="card-header bg-dark text-white">
            <h5 class="mb-0">📋 Recent Activity</h5>
          </div>
          <div class="card-body">
            <?php if ($recent_activity->num_rows > 0): ?>
              <div class="list-group list-group-flush">
                <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                  <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                      <small class="text-muted"><?= $activity['action'] ?></small>
                      <small><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></small>
                    </div>
                    <?php if ($activity['email']): ?>
                      <small class="text-muted">User: <?= $activity['email'] ?></small>
                    <?php endif; ?>
                  </div>
                <?php endwhile; ?>
              </div>
              <div class="mt-3 text-center">
                <a href="activity_log.php" class="btn btn-outline-dark btn-sm">View All Activity</a>
              </div>
            <?php else: ?>
              <p class="text-muted">No recent activity</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending ID Requests -->
    <?php if ($pending_requests->num_rows > 0): ?>
    <div class="card shadow mb-4">
      <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">⏳ Pending ID Requests</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Student</th>
                <th>Student ID</th>
                <th>Request Type</th>
                <th>Submitted</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($request = $pending_requests->fetch_assoc()): ?>
              <tr>
                <td>
                  <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?><br>
                  <small class="text-muted"><?= $request['email'] ?></small>
                </td>
                <td><span class="badge bg-secondary"><?= $request['student_id'] ?></span></td>
                <td><span class="badge bg-info"><?= ucfirst($request['request_type']) ?></span></td>
                <td><small><?= date('M j, g:i A', strtotime($request['created_at'])) ?></small></td>
                <td>
                  <a href="students.php?action=process_request&id=<?= $request['id'] ?>" class="btn btn-success btn-sm">Process</a>
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

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>