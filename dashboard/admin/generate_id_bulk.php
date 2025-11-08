<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Handle bulk ID generation
if (isset($_POST['generate_bulk_ids']) && isset($_POST['selected_requests'])) {
    $selected_requests = $_POST['selected_requests'];
    $admin_id = $_SESSION['user_id'];
    $generated_count = 0;
    $errors = [];
    
    foreach ($selected_requests as $request_id) {
        $request_id = intval($request_id);
        
        // Get request details
        $request_stmt = $conn->prepare("
            SELECT ir.*, s.first_name, s.last_name, s.student_id, s.email, s.course, s.year_level, s.photo, s.emergency_contact, s.blood_type, s.address
            FROM id_requests ir 
            JOIN student s ON ir.student_id = s.id 
            WHERE ir.id = ? AND ir.status IN ('approved', 'pending')
        ");
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_result = $request_stmt->get_result();
        $request_data = $request_result->fetch_assoc();
        $request_stmt->close();
        
        if ($request_data) {
            try {
                // Update request status to completed
                $stmt = $conn->prepare("UPDATE id_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $stmt->close();
                
                $generated_count++;
                
            } catch (Exception $e) {
                $errors[] = "Failed to generate ID for {$request_data['first_name']} {$request_data['last_name']}: " . $e->getMessage();
            }
        } else {
            $errors[] = "Request #$request_id not found or not eligible for ID generation";
        }
    }
    
    // Log activity
    if ($generated_count > 0) {
        $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Bulk generated $generated_count IDs', 0)");
        $_SESSION['success'] = "Successfully generated $generated_count ID(s)!";
        
        if (!empty($errors)) {
            $_SESSION['warning'] = "Some IDs couldn't be generated: " . implode(', ', array_slice($errors, 0, 3));
        }
    } else {
        $_SESSION['error'] = "No IDs were generated. Errors: " . implode(', ', $errors);
    }
    
    header("Location: generate_id_bulk.php");
    exit();
}

// Handle bulk download
if (isset($_POST['download_bulk_ids']) && isset($_POST['selected_requests'])) {
    $selected_requests = $_POST['selected_requests'];
    
    if (count($selected_requests) > 10) {
        $_SESSION['error'] = "For performance reasons, you can only download up to 10 IDs at once.";
        header("Location: generate_id_bulk.php");
        exit();
    }
    
    // Store selected requests in session for the download script
    $_SESSION['bulk_download_requests'] = $selected_requests;
    header("Location: generate_id_card_bulk.php?mode=download");
    exit();
}

// Handle bulk print
if (isset($_POST['print_bulk_ids']) && isset($_POST['selected_requests'])) {
    $selected_requests = $_POST['selected_requests'];
    
    if (count($selected_requests) > 15) {
        $_SESSION['error'] = "For performance reasons, you can only print up to 15 IDs at once.";
        header("Location: generate_id_bulk.php");
        exit();
    }
    
    $_SESSION['bulk_print_requests'] = $selected_requests;
    header("Location: generate_id_card_bulk.php?mode=print");
    exit();
}

// Get eligible requests for bulk generation (approved or pending)
$eligible_requests_query = "
    SELECT ir.*, s.first_name, s.last_name, s.student_id, s.email, s.course, s.year_level, s.photo, s.contact_number
    FROM id_requests ir 
    JOIN student s ON ir.student_id = s.id 
    WHERE ir.status IN ('approved', 'pending')
    ORDER BY ir.created_at DESC
";

$eligible_requests = $conn->query($eligible_requests_query);

// Get statistics
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM id_requests WHERE status IN ('approved', 'pending')) as eligible_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'approved') as approved_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'pending') as pending_requests,
        (SELECT COUNT(*) FROM id_requests WHERE status = 'completed') as completed_requests
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bulk ID Generation | School ID System</title>
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
        border-left: 4px solid #198754;
    }
    .stats-card {
        border: none;
        border-radius: 10px;
        transition: transform 0.2s;
    }
    .stats-card:hover {
        transform: translateY(-3px);
    }
    .select-all-section {
        background: #e9ecef;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 15px;
    }
    .batch-actions {
        position: sticky;
        bottom: 0;
        background: white;
        border-top: 2px solid #dee2e6;
        padding: 15px;
        margin-top: 20px;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>📦 Bulk ID Card Generation</h2>
      <div>
        <a href="admin_id.php" class="btn btn-secondary btn-sm">← Back to ID Management</a>
      </div>
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
    
    <?php if(isset($_SESSION['warning'])): ?>
      <div class="alert alert-warning alert-dismissible fade show">
        <?= $_SESSION['warning']; unset($_SESSION['warning']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-primary">
          <div class="card-body text-center">
            <h3><?= $stats['eligible_requests'] ?></h3>
            <p class="mb-0">Eligible Requests</p>
            <small>Approved + Pending</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-success">
          <div class="card-body text-center">
            <h3><?= $stats['approved_requests'] ?></h3>
            <p class="mb-0">Approved</p>
            <small>Ready for ID Generation</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-warning">
          <div class="card-body text-center">
            <h3><?= $stats['pending_requests'] ?></h3>
            <p class="mb-0">Pending</p>
            <small>Awaiting Approval</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card stats-card text-white bg-info">
          <div class="card-body text-center">
            <h3><?= $stats['completed_requests'] ?></h3>
            <p class="mb-0">Completed</p>
            <small>IDs Generated</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Actions Info Card -->
    <div class="card shadow mb-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0">🚀 Bulk Operations</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <h6>📝 Generate IDs</h6>
            <p class="small text-muted">Mark selected requests as completed and generate ID cards in batch.</p>
          </div>
          <div class="col-md-4">
            <h6>📥 Bulk Download</h6>
            <p class="small text-muted">Download multiple ID cards as PDF (max 10 at once).</p>
          </div>
          <div class="col-md-4">
            <h6>🖨️ Bulk Print</h6>
            <p class="small text-muted">Print multiple ID cards directly (max 15 at once).</p>
          </div>
        </div>
      </div>
    </div>

    <form method="POST" id="bulkForm">
      <!-- Select All Section -->
      <div class="select-all-section">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="selectAll">
          <label class="form-check-label fw-bold" for="selectAll">
            Select All Eligible Requests (<?= $eligible_requests->num_rows ?> requests)
          </label>
        </div>
        <small class="text-muted">Select requests you want to include in bulk operations</small>
      </div>

      <!-- Batch Actions Sticky Bar -->
      <div class="batch-actions shadow" id="batchActions" style="display: none;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <strong><span id="selectedCount">0</span> request(s) selected</strong>
          </div>
          <div class="btn-group">
            <button type="submit" name="generate_bulk_ids" class="btn btn-success" 
                    onclick="return confirm('Are you sure you want to generate IDs for all selected requests?')">
              🎫 Generate IDs
            </button>
            <button type="submit" name="download_bulk_ids" class="btn btn-primary"
                    onclick="return validateBulkAction(10, 'download')">
              📥 Download IDs
            </button>
            <button type="submit" name="print_bulk_ids" class="btn btn-warning"
                    onclick="return validateBulkAction(15, 'print')">
              🖨️ Print IDs
            </button>
          </div>
        </div>
      </div>

      <!-- Eligible Requests -->
      <div class="card shadow">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">✅ Eligible ID Requests</h5>
          <span class="badge bg-light text-dark">
            <?= $eligible_requests->num_rows ?> request(s) available
          </span>
        </div>
        <div class="card-body">
          <?php if ($eligible_requests->num_rows > 0): ?>
            <div class="row">
              <?php while ($request = $eligible_requests->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                  <div class="card request-card status-<?= $request['status'] ?> h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1">
                          <h6 class="card-title mb-1">
                            <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                          </h6>
                          <p class="text-muted small mb-1"><?= $request['email'] ?></p>
                          <span class="badge bg-secondary"><?= $request['student_id'] ?></span>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input request-checkbox" type="checkbox" 
                                 name="selected_requests[]" value="<?= $request['id'] ?>">
                        </div>
                      </div>
                      
                      <div class="d-flex align-items-center mb-2">
                        <div class="me-3">
                          <img src="<?= $request['photo'] ? '../../uploads/' . htmlspecialchars($request['photo']) : '../../assets/img/default_user.png' ?>" 
                               alt="Student Photo" class="student-photo">
                        </div>
                        <div class="flex-grow-1">
                          <div class="small">
                            <strong>Course:</strong> <?= htmlspecialchars($request['course'] ?? 'Not set') ?><br>
                            <strong>Year:</strong> <?= htmlspecialchars($request['year_level'] ?? 'Not set') ?><br>
                            <strong>Contact:</strong> <?= htmlspecialchars($request['contact_number'] ?? 'Not set') ?>
                          </div>
                        </div>
                      </div>
                      
                      <div class="mb-2">
                        <span class="badge bg-<?= $request['status'] === 'pending' ? 'warning' : 'success' ?>">
                          <?= ucfirst($request['status']) ?>
                        </span>
                        <span class="badge bg-primary"><?= ucfirst($request['request_type']) ?></span>
                      </div>
                      
                      <?php if ($request['reason']): ?>
                        <div class="mb-2">
                          <small><strong>Reason:</strong> <?= htmlspecialchars($request['reason']) ?></small>
                        </div>
                      <?php endif; ?>
                      
                      <small class="text-muted">
                        Requested: <?= date('M j, Y', strtotime($request['created_at'])) ?>
                      </small>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <div class="text-muted mb-3">
                <h4>🎉 All Caught Up!</h4>
                <p>No eligible ID requests found for bulk generation.</p>
              </div>
              <a href="admin_id.php" class="btn btn-primary">Manage ID Requests</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
      const checkboxes = document.querySelectorAll('.request-checkbox');
      checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
      });
      updateSelectedCount();
    });

    // Update selected count and show/hide batch actions
    function updateSelectedCount() {
      const selectedCount = document.querySelectorAll('.request-checkbox:checked').length;
      const batchActions = document.getElementById('batchActions');
      const selectedCountElement = document.getElementById('selectedCount');
      
      selectedCountElement.textContent = selectedCount;
      
      if (selectedCount > 0) {
        batchActions.style.display = 'block';
      } else {
        batchActions.style.display = 'none';
      }
    }

    // Add event listeners to all checkboxes
    document.querySelectorAll('.request-checkbox').forEach(checkbox => {
      checkbox.addEventListener('change', updateSelectedCount);
    });

    // Validate bulk actions
    function validateBulkAction(maxLimit, action) {
      const selectedCount = document.querySelectorAll('.request-checkbox:checked').length;
      
      if (selectedCount === 0) {
        alert('Please select at least one request.');
        return false;
      }
      
      if (selectedCount > maxLimit) {
        alert(`For performance reasons, you can only ${action} up to ${maxLimit} IDs at once.`);
        return false;
      }
      
      return confirm(`Are you sure you want to ${action} ${selectedCount} ID(s)?`);
    }

    // Initialize selected count on page load
    document.addEventListener('DOMContentLoaded', updateSelectedCount);
  </script>
</body>
</html>

<?php $conn->close(); ?>