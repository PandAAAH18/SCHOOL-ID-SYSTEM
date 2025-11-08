<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Get comprehensive statistics
$stats = $conn->query("
    SELECT 
        -- User Statistics
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as student_users,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_users,
        (SELECT COUNT(*) FROM users WHERE role = 'staff') as staff_users,
        (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
        (SELECT COUNT(*) FROM users WHERE status = 'approved') as approved_users,
        (SELECT COUNT(*) FROM users WHERE is_verified = 1) as verified_users,
        
        -- Student Statistics
        (SELECT COUNT(*) FROM student) as total_students,
        (SELECT COUNT(*) FROM student WHERE student_id IS NOT NULL) as students_with_ids,
        (SELECT COUNT(*) FROM student s LEFT JOIN users u ON s.email = u.email WHERE u.email IS NULL) as students_without_accounts,
        
        -- ID Request Statistics
        (SELECT COUNT(*) FROM id_requests) as total_id_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'pending') as pending_id_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'approved') as approved_id_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'completed') as completed_id_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'rejected') as rejected_id_requests,
        
        -- Request Type Breakdown
        (SELECT COUNT(*) FROM id_requests WHERE request_type = 'new') as new_requests,
        (SELECT COUNT(*) FROM id_requests WHERE request_type = 'replacement') as replacement_requests,
        (SELECT COUNT(*) FROM id_requests WHERE request_type = 'update') as update_requests,
        
        -- Course Statistics
        (SELECT COUNT(DISTINCT course) FROM student WHERE course IS NOT NULL) as total_courses,
        
        -- Recent Activity
        (SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()) as today_activity
")->fetch_assoc();

// Get course distribution
$course_stats = $conn->query("
    SELECT course, COUNT(*) as count 
    FROM student 
    WHERE course IS NOT NULL 
    GROUP BY course 
    ORDER BY count DESC
");

// Get monthly user registrations
$monthly_registrations = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

// Get ID request trends
$request_trends = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        status,
        COUNT(*) as count
    FROM id_requests 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), status
    ORDER BY month ASC, status
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports & Analytics | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .stat-card {
        transition: transform 0.2s;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-2px);
    }
    .progress {
        height: 8px;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Reports & Analytics</h2>
      <a href="admin.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card stat-card text-white bg-primary">
          <div class="card-body">
            <h4><?= $stats['total_users'] ?></h4>
            <p class="mb-1">Total Users</p>
            <small>
              <?= $stats['student_users'] ?> students, 
              <?= $stats['admin_users'] ?> admins
            </small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stat-card text-white bg-success">
          <div class="card-body">
            <h4><?= $stats['total_students'] ?></h4>
            <p class="mb-1">Total Students</p>
            <small>
              <?= $stats['students_with_ids'] ?> with IDs,
              <?= $stats['students_without_accounts'] ?> pending
            </small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stat-card text-white bg-info">
          <div class="card-body">
            <h4><?= $stats['total_id_requests'] ?></h4>
            <p class="mb-1">ID Requests</p>
            <small>
              <?= $stats['completed_id_requests'] ?> completed,
              <?= $stats['pending_id_requests'] ?> pending
            </small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stat-card text-white bg-warning">
          <div class="card-body">
            <h4><?= $stats['today_activity'] ?></h4>
            <p class="mb-1">Today's Activity</p>
            <small>Admin actions today</small>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- User Statistics -->
      <div class="col-md-6 mb-4">
        <div class="card shadow">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0">👥 User Statistics</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <strong>User Status Distribution:</strong>
              <div class="mt-2">
                <div class="d-flex justify-content-between">
                  <span>Approved Users</span>
                  <span><?= $stats['approved_users'] ?> (<?= round(($stats['approved_users']/$stats['total_users'])*100, 1) ?>%)</span>
                </div>
                <div class="progress mb-2">
                  <div class="progress-bar bg-success" style="width: <?= ($stats['approved_users']/$stats['total_users'])*100 ?>%"></div>
                </div>
                
                <div class="d-flex justify-content-between">
                  <span>Pending Users</span>
                  <span><?= $stats['pending_users'] ?> (<?= round(($stats['pending_users']/$stats['total_users'])*100, 1) ?>%)</span>
                </div>
                <div class="progress mb-2">
                  <div class="progress-bar bg-warning" style="width: <?= ($stats['pending_users']/$stats['total_users'])*100 ?>%"></div>
                </div>
              </div>
            </div>
            
            <div class="mb-3">
              <strong>Verification Status:</strong>
              <div class="mt-2">
                <div class="d-flex justify-content-between">
                  <span>Verified Users</span>
                  <span><?= $stats['verified_users'] ?> (<?= round(($stats['verified_users']/$stats['total_users'])*100, 1) ?>%)</span>
                </div>
                <div class="progress">
                  <div class="progress-bar bg-info" style="width: <?= ($stats['verified_users']/$stats['total_users'])*100 ?>%"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ID Request Statistics -->
      <div class="col-md-6 mb-4">
        <div class="card shadow">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0">🎫 ID Request Statistics</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <strong>Request Status:</strong>
              <div class="mt-2">
                <?php if ($stats['total_id_requests'] > 0): ?>
                  <div class="d-flex justify-content-between">
                    <span>Completed</span>
                    <span><?= $stats['completed_id_requests'] ?> (<?= round(($stats['completed_id_requests']/$stats['total_id_requests'])*100, 1) ?>%)</span>
                  </div>
                  <div class="progress mb-2">
                    <div class="progress-bar bg-success" style="width: <?= ($stats['completed_id_requests']/$stats['total_id_requests'])*100 ?>%"></div>
                  </div>
                  
                  <div class="d-flex justify-content-between">
                    <span>Pending</span>
                    <span><?= $stats['pending_id_requests'] ?> (<?= round(($stats['pending_id_requests']/$stats['total_id_requests'])*100, 1) ?>%)</span>
                  </div>
                  <div class="progress mb-2">
                    <div class="progress-bar bg-warning" style="width: <?= ($stats['pending_id_requests']/$stats['total_id_requests'])*100 ?>%"></div>
                  </div>
                  
                  <div class="d-flex justify-content-between">
                    <span>Approved</span>
                    <span><?= $stats['approved_id_requests'] ?> (<?= round(($stats['approved_id_requests']/$stats['total_id_requests'])*100, 1) ?>%)</span>
                  </div>
                  <div class="progress">
                    <div class="progress-bar bg-info" style="width: <?= ($stats['approved_id_requests']/$stats['total_id_requests'])*100 ?>%"></div>
                  </div>
                <?php else: ?>
                  <p class="text-muted">No ID requests yet.</p>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="mb-3">
              <strong>Request Types:</strong>
              <div class="mt-2">
                <span class="badge bg-primary">New: <?= $stats['new_requests'] ?></span>
                <span class="badge bg-warning">Replacement: <?= $stats['replacement_requests'] ?></span>
                <span class="badge bg-info">Update: <?= $stats['update_requests'] ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Course Distribution -->
    <div class="card shadow mb-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0">📚 Course Distribution</h5>
      </div>
      <div class="card-body">
        <?php if ($course_stats->num_rows > 0): ?>
          <div class="row">
            <?php while ($course = $course_stats->fetch_assoc()): ?>
              <div class="col-md-4 mb-3">
                <div class="card">
                  <div class="card-body">
                    <h6><?= htmlspecialchars($course['course']) ?></h6>
                    <p class="mb-1"><strong><?= $course['count'] ?></strong> students</p>
                    <small class="text-muted">
                      <?= round(($course['count']/$stats['total_students'])*100, 1) ?>% of total
                    </small>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <p class="text-muted">No course data available.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- System Overview -->
    <div class="card shadow">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0">📊 System Overview</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h6>Registration Completion Rate</h6>
            <?php
            $completion_rate = $stats['total_students'] > 0 ? 
                (($stats['students_with_ids'] / $stats['total_students']) * 100) : 0;
            ?>
            <div class="progress mb-3" style="height: 20px;">
              <div class="progress-bar bg-success" style="width: <?= $completion_rate ?>%">
                <?= round($completion_rate, 1) ?>%
              </div>
            </div>
            <small class="text-muted">
              <?= $stats['students_with_ids'] ?> of <?= $stats['total_students'] ?> students have complete profiles
            </small>
          </div>
          
          <div class="col-md-6">
            <h6>Account Activation Rate</h6>
            <?php
            $activation_rate = $stats['total_students'] > 0 ? 
                ((($stats['total_students'] - $stats['students_without_accounts']) / $stats['total_students']) * 100) : 0;
            ?>
            <div class="progress mb-3" style="height: 20px;">
              <div class="progress-bar bg-info" style="width: <?= $activation_rate ?>%">
                <?= round($activation_rate, 1) ?>%
              </div>
            </div>
            <small class="text-muted">
              <?= $stats['students_without_accounts'] ?> students pending account creation
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>