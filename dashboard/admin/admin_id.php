<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Handle ID issuance actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = intval($_GET['id']);
    $admin_id = $_SESSION['user_id'];
    
    if ($_GET['action'] === 'approve') {
        // Update request status to approved
        $stmt = $conn->prepare("UPDATE id_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();
        
        // Log activity
        $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Approved ID request #$request_id', 0)");
        $_SESSION['success'] = "ID request approved successfully!";
        
    } elseif ($_GET['action'] === 'reject') {
        $reason = $_GET['reason'] ?? 'No reason provided';
        
        // Update request status to rejected
        $stmt = $conn->prepare("UPDATE id_requests SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $reason, $request_id);
        $stmt->execute();
        $stmt->close();
        
        // Log activity
        $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Rejected ID request #$request_id', 0)");
        $_SESSION['success'] = "ID request rejected successfully!";
        
    } elseif ($_GET['action'] === 'complete') {
        // Update request status to completed (ID issued)
        $stmt = $conn->prepare("UPDATE id_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();
        
        // Log activity
        $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Completed ID issuance for request #$request_id', 0)");
        $_SESSION['success'] = "ID marked as completed/issued!";
        
    } elseif ($_GET['action'] === 'generate_id') {
        // Generate and issue ID
        $request_id = intval($_GET['id']);
        
        // Get request details
        $request_stmt = $conn->prepare("
            SELECT ir.*, s.first_name, s.last_name, s.student_id, s.email, s.course, s.year_level, s.photo, s.emergency_contact, s.blood_type, s.address
            FROM id_requests ir 
            JOIN student s ON ir.student_id = s.id 
            WHERE ir.id = ?
        ");
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_result = $request_stmt->get_result();
        $request_data = $request_result->fetch_assoc();
        $request_stmt->close();
        
        if ($request_data) {
            // Update request status to completed
            $stmt = $conn->prepare("UPDATE id_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();
            
            // Log activity
            $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Generated ID for student: {$request_data['email']}', 0)");
            $_SESSION['success'] = "ID generated successfully for {$request_data['first_name']} {$request_data['last_name']}!";
        }
    }
    
    header("Location: admin_id.php");
    exit();
}

// Handle ID viewing/downloading
if (isset($_GET['view_id'])) {
    $student_id = $_GET['view_id'];
    header("Location: generate_id_card.php?student_id=" . $student_id . "&mode=preview");
    exit();
}

if (isset($_GET['download_id'])) {
    $student_id = $_GET['download_id'];
    header("Location: generate_id_card.php?student_id=" . $student_id . "&mode=download");
    exit();
}

if (isset($_GET['print_id'])) {
    $student_id = $_GET['print_id'];
    header("Location: generate_id_card.php?student_id=" . $student_id . "&mode=print");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_requests'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_requests = $_POST['selected_requests'];
    $admin_id = $_SESSION['user_id'];
    $processed = 0;
    
    foreach ($selected_requests as $request_id) {
        $request_id = intval($request_id);
        
        if ($bulk_action === 'approve') {
            $stmt = $conn->prepare("UPDATE id_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();
            $processed++;
            
        } elseif ($bulk_action === 'reject') {
            $stmt = $conn->prepare("UPDATE id_requests SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();
            $processed++;
            
        } elseif ($bulk_action === 'complete') {
            $stmt = $conn->prepare("UPDATE id_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();
            $processed++;
        }
    }
    
    if ($processed > 0) {
        $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Bulk $bulk_action: $processed ID requests', 0)");
        $_SESSION['success'] = "Bulk action completed! $processed requests processed.";
    }
    
    header("Location: admin_id.php");
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';
$request_type_filter = $_GET['request_type'] ?? '';

// Build query with filters
$query = "
    SELECT ir.*, s.first_name, s.last_name, s.student_id, s.email, s.course, s.year_level, s.photo, s.contact_number, s.emergency_contact, s.blood_type,
           u.user_id as user_exists
    FROM id_requests ir 
    JOIN student s ON ir.student_id = s.id 
    LEFT JOIN users u ON s.email = u.email
    WHERE 1=1
";

$params = [];
$types = '';

// Add status filter
if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND ir.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

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

// Add request type filter
if (!empty($request_type_filter) && $request_type_filter !== 'all') {
    $query .= " AND ir.request_type = ?";
    $params[] = $request_type_filter;
    $types .= 's';
}

$query .= " ORDER BY ir.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$id_requests = $stmt->get_result();

// Get statistics for dashboard
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM id_requests WHERE status = 'pending') as pending_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'approved') as approved_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'completed') as completed_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'rejected') as rejected_requests,
        (SELECT COUNT(*) FROM id_requests WHERE request_type = 'new') as new_requests,
        (SELECT COUNT(*) FROM id_requests WHERE request_type = 'replacement') as replacement_requests,
        (SELECT COUNT(*) FROM id_requests WHERE request_type = 'update') as update_requests
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ID Issuance | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .request-card {
        transition: transform 0.2s;
        border-left: 4px solid #0d6efd;
    }
    .request-card:hover {
        transform: translateY(-2px);
    }
    .status-pending { border-left-color: #ffc107; }
    .status-approved { border-left-color: #198754; }
    .status-completed { border-left-color: #0dcaf0; }
    .status-rejected { border-left-color: #dc3545; }
    .student-photo {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 5px;
    }
    .bulk-actions {
        background: #f8f9fa;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 15px;
    }
    .id-preview {
        max-width: 300px;
        border: 2px solid #dee2e6;
        border-radius: 10px;
        overflow: hidden;
    }
    .quick-actions {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    .quick-actions .btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>ID Card Issuance</h2>
      <a href="admin.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card text-white bg-warning">
          <div class="card-body text-center">
            <h3><?= $stats['pending_requests'] ?></h3>
            <p class="mb-0">Pending Requests</p>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card text-white bg-info">
          <div class="card-body text-center">
            <h3><?= $stats['approved_requests'] ?></h3>
            <p class="mb-0">Approved</p>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card text-white bg-success">
          <div class="card-body text-center">
            <h3><?= $stats['completed_requests'] ?></h3>
            <p class="mb-0">Completed</p>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card text-white bg-danger">
          <div class="card-body text-center">
            <h3><?= $stats['rejected_requests'] ?></h3>
            <p class="mb-0">Rejected</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="card shadow mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">⚡ Quick Actions</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="d-grid">
              <a href="generate_id_bulk.php" class="btn btn-success">
                📦 Generate IDs in Bulk
              </a>
              <small class="text-muted mt-1">Generate multiple IDs at once</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-grid">
              <a href="id_templates.php" class="btn btn-info">
                🎨 Manage ID Templates
              </a>
              <small class="text-muted mt-1">Customize ID card designs</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-grid">
              <a href="id_reports.php" class="btn btn-warning">
                📊 ID Issuance Reports
              </a>
              <small class="text-muted mt-1">View printing statistics</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">🔍 Filter Requests</h5>
      </div>
      <div class="card-body">
        <form method="GET" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" 
                   placeholder="Search by name, email, or student ID" 
                   value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
              <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
              <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Request Type</label>
            <select name="request_type" class="form-select">
              <option value="all" <?= $request_type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
              <option value="new" <?= $request_type_filter === 'new' ? 'selected' : '' ?>>New ID</option>
              <option value="replacement" <?= $request_type_filter === 'replacement' ? 'selected' : '' ?>>Replacement</option>
              <option value="update" <?= $request_type_filter === 'update' ? 'selected' : '' ?>>Update</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
            <a href="admin_id.php" class="btn btn-outline-secondary">Clear</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Bulk Actions -->
    <form method="POST" id="bulkForm">
      <div class="bulk-actions d-flex align-items-center gap-3 mb-3">
        <select name="bulk_action" class="form-select w-auto" required>
          <option value="">Bulk Actions</option>
          <option value="approve">Approve Selected</option>
          <option value="reject">Reject Selected</option>
          <option value="complete">Mark as Completed</option>
          <option value="generate">Generate IDs</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Are you sure you want to perform this bulk action?')">
          Apply
        </button>
        <small class="text-muted">Select requests using checkboxes below</small>
      </div>

      <!-- ID Requests -->
      <div class="card shadow">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">🎫 ID Requests</h5>
          <span class="badge bg-light text-dark">
            <?= $id_requests->num_rows ?> request(s)
          </span>
        </div>
        <div class="card-body">
          <?php if ($id_requests->num_rows > 0): ?>
            <div class="row">
              <?php while ($request = $id_requests->fetch_assoc()): ?>
                <div class="col-md-6 mb-4">
                  <div class="card request-card status-<?= $request['status'] ?> h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                          <h6 class="card-title mb-1">
                            <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                          </h6>
                          <p class="text-muted mb-1"><?= $request['email'] ?></p>
                          <span class="badge bg-secondary"><?= $request['student_id'] ?></span>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="selected_requests[]" value="<?= $request['id'] ?>">
                        </div>
                      </div>
                      
                      <div class="row">
                        <div class="col-4">
                          <img src="<?= $request['photo'] ? '../../uploads/' . htmlspecialchars($request['photo']) : '../../assets/img/default_user.png' ?>" 
                               alt="Student Photo" class="student-photo w-100">
                        </div>
                        <div class="col-8">
                          <div class="mb-2">
                            <strong>Course:</strong> <?= htmlspecialchars($request['course'] ?? 'Not set') ?><br>
                            <strong>Year:</strong> <?= htmlspecialchars($request['year_level'] ?? 'Not set') ?><br>
                            <strong>Contact:</strong> <?= htmlspecialchars($request['contact_number'] ?? 'Not set') ?>
                          </div>
                          
                          <div class="mb-2">
                            <span class="badge bg-<?= 
                              $request['status'] === 'pending' ? 'warning' : 
                              ($request['status'] === 'approved' ? 'info' : 
                              ($request['status'] === 'completed' ? 'success' : 'danger')) 
                            ?>">
                              <?= ucfirst($request['status']) ?>
                            </span>
                            <span class="badge bg-primary"><?= ucfirst($request['request_type']) ?></span>
                            <?php if (!$request['user_exists']): ?>
                              <span class="badge bg-danger">No User Account</span>
                            <?php endif; ?>
                          </div>
                          
                          <?php if ($request['reason']): ?>
                            <div class="mb-2">
                              <small><strong>Reason:</strong> <?= htmlspecialchars($request['reason']) ?></small>
                            </div>
                          <?php endif; ?>
                          
                          <?php if ($request['admin_notes']): ?>
                            <div class="mb-2">
                              <small><strong>Admin Notes:</strong> <?= htmlspecialchars($request['admin_notes']) ?></small>
                            </div>
                          <?php endif; ?>
                          
                          <small class="text-muted">
                            Submitted: <?= date('M j, Y g:i A', strtotime($request['created_at'])) ?>
                          </small>
                        </div>
                      </div>
                      
                      <!-- Action Buttons -->
                      <div class="mt-3">
                        <div class="btn-group btn-group-sm w-100">
                          <?php if ($request['status'] === 'pending'): ?>
                            <a href="?action=approve&id=<?= $request['id'] ?>" class="btn btn-success">Approve</a>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" 
                                    data-requestid="<?= $request['id'] ?>">Reject</button>
                          <?php elseif ($request['status'] === 'approved'): ?>
                            <a href="?action=generate_id&id=<?= $request['id'] ?>" class="btn btn-primary">Generate ID</a>
                            <a href="?action=complete&id=<?= $request['id'] ?>" class="btn btn-success">Mark Complete</a>
                          <?php elseif ($request['status'] === 'completed'): ?>
                            <div class="quick-actions w-100">
                              <a href="?view_id=<?= $request['student_id'] ?>" class="btn btn-info" target="_blank">👁️ View</a>
                              <a href="?download_id=<?= $request['student_id'] ?>" class="btn btn-success">📥 Download</a>
                              <a href="?print_id=<?= $request['student_id'] ?>" class="btn btn-warning">🖨️ Print</a>
                              <a href="generate_id_card.php?student_id=<?= $request['student_id'] ?>&mode=email" class="btn btn-secondary">📧 Email</a>
                            </div>
                          <?php endif; ?>
                          <a href="student_details.php?id=<?= $request['student_id'] ?>" class="btn btn-outline-dark mt-1">View Student</a>
                        </div>
                        
                        <!-- ID Preview for Completed Requests -->
                        <?php if ($request['status'] === 'completed'): ?>
                          <div class="mt-2 p-2 border rounded">
                            <small class="text-muted d-block mb-1">ID Preview:</small>
                            <div class="id-preview mx-auto">
                              <div class="bg-primary text-white p-2 text-center">
                                <strong>SCHOOL ID</strong>
                              </div>
                              <div class="p-2">
                                <div class="row">
                                  <div class="col-4">
                                    <img src="<?= $request['photo'] ? '../../uploads/' . htmlspecialchars($request['photo']) : '../../assets/img/default_user.png' ?>" 
                                         class="w-100 rounded">
                                  </div>
                                  <div class="col-8">
                                    <small><strong><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></strong></small><br>
                                    <small>ID: <?= $request['student_id'] ?></small><br>
                                    <small><?= $request['course'] ?? 'N/A' ?></small>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <p class="text-muted">No ID requests found matching your criteria.</p>
              <a href="admin_id.php" class="btn btn-primary">Clear Filters</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <!-- Reject Modal -->
  <div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Reject ID Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="GET" action="">
          <div class="modal-body">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="reject_request_id">
            
            <div class="mb-3">
              <label class="form-label">Reason for Rejection</label>
              <textarea name="reason" class="form-control" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Reject Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Reject Modal functionality
    const rejectModal = document.getElementById('rejectModal');
    rejectModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const requestId = button.getAttribute('data-requestid');
      document.getElementById('reject_request_id').value = requestId;
    });

    // Select all checkboxes
    function selectAllCheckboxes(source) {
      const checkboxes = document.querySelectorAll('input[name="selected_requests[]"]');
      checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
      });
    }
  </script>
</body>
</html>

<?php $stmt->close(); ?>