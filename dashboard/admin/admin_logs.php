<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$total_result = $conn->query("SELECT COUNT(*) as total FROM activity_logs");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get activity logs with pagination
$logs = $conn->query("
    SELECT al.*, u.email as target_email, a.email as admin_email
    FROM activity_logs al 
    LEFT JOIN users u ON al.target_user = u.user_id 
    LEFT JOIN users a ON al.admin_id = a.user_id 
    ORDER BY al.created_at DESC 
    LIMIT $limit OFFSET $offset
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Activity Log | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Activity Log</h2>
      <a href="admin.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>

    <!-- Activity Log -->
    <div class="card shadow">
      <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">📋 System Activity Log</h5>
        <span class="badge bg-light text-dark">
          <?= $total_rows ?> total entries
        </span>
      </div>
      <div class="card-body">
        <?php if ($logs->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>Timestamp</th>
                  <th>Admin</th>
                  <th>Action</th>
                  <th>Target User</th>
                </tr>
              </thead>
              <tbody>
              <?php while ($log = $logs->fetch_assoc()): ?>
                <tr>
                  <td>
                    <small>
                      <?= date('M j, Y', strtotime($log['created_at'])) ?><br>
                      <?= date('g:i A', strtotime($log['created_at'])) ?>
                    </small>
                  </td>
                  <td>
                    <?php if ($log['admin_email']): ?>
                      <?= htmlspecialchars($log['admin_email']) ?>
                    <?php else: ?>
                      <span class="text-muted">Admin #<?= $log['admin_id'] ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($log['action']) ?></td>
                  <td>
                    <?php if ($log['target_user'] > 0): ?>
                      <?php if ($log['target_email']): ?>
                        <?= htmlspecialchars($log['target_email']) ?>
                      <?php else: ?>
                        User #<?= $log['target_user'] ?>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">System</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
          <nav aria-label="Activity log pagination">
            <ul class="pagination justify-content-center">
              <?php if ($page > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                </li>
              <?php endif; ?>
              
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              
              <?php if ($page < $total_pages): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                </li>
              <?php endif; ?>
            </ul>
          </nav>
          <?php endif; ?>

        <?php else: ?>
          <div class="text-center py-4">
            <p class="text-muted">No activity logs found.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>