<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Get mode and selected requests
$mode = $_GET['mode'] ?? 'preview';
$selected_requests = [];

if ($mode === 'download' && isset($_SESSION['bulk_download_requests'])) {
    $selected_requests = $_SESSION['bulk_download_requests'];
    unset($_SESSION['bulk_download_requests']);
} elseif ($mode === 'print' && isset($_SESSION['bulk_print_requests'])) {
    $selected_requests = $_SESSION['bulk_print_requests'];
    unset($_SESSION['bulk_print_requests']);
}

if (empty($selected_requests)) {
    die("No requests selected for bulk operation.");
}

// Get student data for selected requests
$placeholders = str_repeat('?,', count($selected_requests) - 1) . '?';
$stmt = $conn->prepare("
    SELECT s.*, ir.request_type, ir.created_at as request_date
    FROM student s 
    JOIN id_requests ir ON s.id = ir.student_id 
    WHERE ir.id IN ($placeholders)
");
$stmt->bind_param(str_repeat('i', count($selected_requests)), ...$selected_requests);
$stmt->execute();
$students = $stmt->get_result();
$stmt->close();

// For now, we'll create a simple multi-ID preview
// In a real implementation, you'd use a PDF library like TCPDF or Dompdf
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bulk ID Cards</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .id-card {
        width: 300px;
        height: 180px;
        border: 2px solid #333;
        border-radius: 10px;
        margin: 10px;
        display: inline-block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        position: relative;
        overflow: hidden;
    }
    .id-header {
        background: rgba(0,0,0,0.3);
        padding: 5px;
        text-align: center;
        font-weight: bold;
    }
    .id-body {
        padding: 10px;
        display: flex;
    }
    .id-photo {
        width: 80px;
        height: 80px;
        border-radius: 5px;
        border: 2px solid white;
        margin-right: 10px;
    }
    .id-details {
        flex: 1;
    }
    .id-details h6 {
        margin: 0;
        font-size: 14px;
    }
    .id-details p {
        margin: 2px 0;
        font-size: 12px;
    }
    .id-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0,0,0,0.3);
        padding: 3px;
        text-align: center;
        font-size: 10px;
    }
    .bulk-container {
        text-align: center;
        padding: 20px;
    }
    @media print {
        .no-print { display: none; }
        .id-card { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="no-print d-flex justify-content-between align-items-center p-3 bg-light">
      <h4>Bulk ID Cards - <?= ucfirst($mode) ?> Mode</h4>
      <div>
        <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Print All</button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">Close</button>
      </div>
    </div>

    <div class="bulk-container">
      <?php while ($student = $students->fetch_assoc()): ?>
        <div class="id-card">
          <div class="id-header">
            SCHOOL ID CARD
          </div>
          <div class="id-body">
            <img src="<?= $student['photo'] ? '../../uploads/' . htmlspecialchars($student['photo']) : '../../assets/img/default_user.png' ?>" 
                 alt="Photo" class="id-photo">
            <div class="id-details">
              <h6><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h6>
              <p><strong>ID:</strong> <?= $student['student_id'] ?></p>
              <p><strong>Course:</strong> <?= htmlspecialchars($student['course'] ?? 'N/A') ?></p>
              <p><strong>Year:</strong> <?= htmlspecialchars($student['year_level'] ?? 'N/A') ?></p>
              <p><strong>Blood Type:</strong> <?= htmlspecialchars($student['blood_type'] ?? 'N/A') ?></p>
            </div>
          </div>
          <div class="id-footer">
            Valid until: <?= date('Y', strtotime('+1 year')) ?> | Emergency: <?= htmlspecialchars($student['emergency_contact'] ?? 'N/A') ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <?php if ($mode === 'download'): ?>
    <script>
      // Auto-trigger download (this would normally generate a PDF)
      setTimeout(() => {
        alert('In a full implementation, this would download a PDF file with all ID cards.');
        // window.print(); // Alternative: print instead of download
      }, 1000);
    </script>
    <?php endif; ?>
  </div>
</body>
</html>
<?php $conn->close(); ?>