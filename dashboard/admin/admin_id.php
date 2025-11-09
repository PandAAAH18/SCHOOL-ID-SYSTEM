<?php
session_start();
require_once("../../includes/db_connect.php");

/* ---------- auth ---------- */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit();
}

/* ---------- helper : run UPDATE + log in one call ---------- */
function execUpdate($sql, $types = '', $vals = [], $logMsg = ''){
    global $conn, $admin_id;
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
    if ($logMsg) $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, '$logMsg', 0)");
}

$admin_id = $_SESSION['user_id'];

/* ---------- single-action GET ---------- */
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    switch ($_GET['action']) {
        case 'approve':
            execUpdate("UPDATE id_requests SET status='approved', updated_at=NOW() WHERE id=?", 'i', [$id], "Approved ID request #$id");
            $_SESSION['success'] = "ID request approved successfully!";
            break;

        case 'reject':
            $reason = $_GET['reason'] ?? 'No reason provided';
            execUpdate("UPDATE id_requests SET status='rejected', admin_notes=?, updated_at=NOW() WHERE id=?", 'si', [$reason, $id], "Rejected ID request #$id");
            $_SESSION['success'] = "ID request rejected successfully!";
            break;

        case 'complete':
            execUpdate("UPDATE id_requests SET status='completed', updated_at=NOW() WHERE id=?", 'i', [$id], "Completed ID issuance for request #$id");
            $_SESSION['success'] = "ID marked as completed/issued!";
            break;

        case 'generate_id':
            $stmt = $conn->prepare("SELECT s.email, s.first_name, s.last_name
                                    FROM id_requests ir JOIN student s ON ir.student_id = s.id WHERE ir.id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $st = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            execUpdate("UPDATE id_requests SET status='completed', updated_at=NOW() WHERE id=?", 'i', [$id],
                       "Generated ID for student: {$st['email']}");
            $_SESSION['success'] = "ID generated successfully for {$st['first_name']} {$st['last_name']}!";
            break;
    }
    header("Location: admin_id.php"); exit();
}

/* ---------- redirect helpers ---------- */
foreach (['view_id','download_id','print_id'] as $k) {
    if (isset($_GET[$k])) {
        $mode = str_replace('_id','', $k);
        header("Location: generate_id_card.php?student_id=".intval($_GET[$k])."&mode=$mode"); exit();
    }
}

/* ---------- bulk POST ---------- */
if (isset($_POST['bulk_action'], $_POST['selected_requests'])) {
    $action = $_POST['bulk_action'];
    $processed = 0;
    foreach ($_POST['selected_requests'] as $rid) {
        $rid = intval($rid);
        switch ($action) {
            case 'approve': execUpdate("UPDATE id_requests SET status='approved', updated_at=NOW() WHERE id=?", 'i', [$rid]); break;
            case 'reject':  execUpdate("UPDATE id_requests SET status='rejected', updated_at=NOW() WHERE id=?", 'i', [$rid]); break;
            case 'complete':execUpdate("UPDATE id_requests SET status='completed', updated_at=NOW() WHERE id=?", 'i', [$rid]); break;
        }
        $processed++;
    }
    if ($processed) {
        $conn->query("INSERT INTO activity_logs (admin_id, action, target_user) VALUES ($admin_id, 'Bulk $action: $processed requests', 0)");
        $_SESSION['success'] = "Bulk action completed! $processed requests processed.";
    }
    header("Location: admin_id.php"); exit();
}

/* ---------- filters / stats ---------- */
$status_filter   = $_GET['status']   ?? 'pending';
$search          = $_GET['search']   ?? '';
$request_type    = $_GET['request_type'] ?? '';

$params = []; $types = '';
$query  = "SELECT ir.*, ir.student_id AS student_pk_id, s.first_name, s.last_name,
                  s.student_id AS student_id_number, s.email, s.course, s.year_level,
                  s.photo, s.contact_number, s.emergency_contact, s.blood_type,
                  u.email AS user_exists
           FROM id_requests ir JOIN student s ON ir.student_id = s.id
           LEFT JOIN users u ON s.email = u.email WHERE 1=1";

if ($status_filter !== 'all') {
    $query .= " AND ir.status = ?"; $params[] = $status_filter; $types .= 's';
}
if ($search) {
    $like = "%$search%";
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.student_id LIKE ?)";
    array_push($params, $like, $like, $like, $like); $types .= 'ssss';
}
if ($request_type !== 'all') {
    $query .= " AND ir.request_type = ?"; $params[] = $request_type; $types .= 's';
}
$query .= " ORDER BY ir.created_at DESC";

$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$id_requests = $stmt->get_result();

$stats = $conn->query("
    SELECT 
        SUM(status='pending')   AS pending_requests,
        SUM(status='approved')  AS approved_requests,
        SUM(status='completed') AS completed_requests,
        SUM(status='rejected')  AS rejected_requests,
        COALESCE(SUM(request_type='new'),0)         AS new_requests,
        COALESCE(SUM(request_type='replacement'),0) AS replacement_requests,
        COALESCE(SUM(request_type='update'),0)      AS update_requests
    FROM id_requests
")->fetch_assoc();

/* ---------- cast every value to int ---------- */
$stats = array_map(fn($v) => (int)$v, $stats);
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

    .status-pending {
        border-left-color: #ffc107;
    }

    .status-approved {
        border-left-color: #198754;
    }

    .status-completed {
        border-left-color: #0dcaf0;
    }

    .status-rejected {
        border-left-color: #dc3545;
    }

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
                            <a href="bulk_approved.php" class="btn btn-success">
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
                            placeholder="Search by name, email, or student ID" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending
                            </option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved
                            </option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed
                            </option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Request Type</label>
                        <select name="request_type" class="form-select">
                            <option value="all" <?= ($request_type === 'all') ? 'selected' : '' ?>>All Types</option>
                            <option value="new" <?= ($request_type === 'new') ? 'selected' : '' ?>>New ID</option>
                            <option value="replacement" <?= ($request_type === 'replacement') ? 'selected' : '' ?>>
                                Replacement</option>
                            <option value="update" <?= ($request_type === 'update') ? 'selected' : '' ?>>Update</option>
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
                <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('Are you sure you want to perform this bulk action?')">
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
                                            <span class="badge bg-secondary"><?= $request['student_id_number'] ?></span>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="selected_requests[]"
                                                value="<?= $request['id'] ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-4">
                                            <img src="<?= $request['photo'] ? '../../uploads/student_photos/' . htmlspecialchars($request['photo']) : '../../assets/img/default_user.png' ?>"
                                                alt="Student Photo" class="student-photo w-100">
                                        </div>
                                        <div class="col-8">
                                            <div class="mb-2">
                                                <strong>Course:</strong>
                                                <?= htmlspecialchars($request['course'] ?? 'Not set') ?><br>
                                                <strong>Year:</strong>
                                                <?= htmlspecialchars($request['year_level'] ?? 'Not set') ?><br>
                                                <strong>Contact:</strong>
                                                <?= htmlspecialchars($request['contact_number'] ?? 'Not set') ?>
                                            </div>

                                            <div class="mb-2">
                                                <span class="badge bg-<?= 
                              $request['status'] === 'pending' ? 'warning' : 
                              ($request['status'] === 'approved' ? 'info' : 
                              ($request['status'] === 'completed' ? 'success' : 'danger')) 
                            ?>">
                                                    <?= ucfirst($request['status']) ?>
                                                </span>
                                                <span
                                                    class="badge bg-primary"><?= ucfirst($request['request_type']) ?></span>
                                                <?php if (!$request['user_exists']): ?>
                                                <span class="badge bg-danger">No User Account</span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($request['reason']): ?>
                                            <div class="mb-2">
                                                <small><strong>Reason:</strong>
                                                    <?= htmlspecialchars($request['reason']) ?></small>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($request['admin_notes']): ?>
                                            <div class="mb-2">
                                                <small><strong>Admin Notes:</strong>
                                                    <?= htmlspecialchars($request['admin_notes']) ?></small>
                                            </div>
                                            <?php endif; ?>

                                            <small class="text-muted">
                                                Submitted:
                                                <?= date('M j, Y g:i A', strtotime($request['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-3">
                                        <div class="btn-group btn-group-sm w-100">
                                            <?php if ($request['status'] === 'pending'): ?>
                                            <a href="?action=approve&id=<?= $request['id'] ?>"
                                                class="btn btn-success">Approve</a>
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                                data-bs-target="#rejectModal"
                                                data-requestid="<?= $request['id'] ?>">Reject</button>
                                            <?php elseif ($request['status'] === 'approved'): ?>
                                            <a href="?action=generate_id&id=<?= $request['id'] ?>"
                                                class="btn btn-primary">Generate ID</a>
                                            <a href="?action=complete&id=<?= $request['id'] ?>"
                                                class="btn btn-success">Mark Complete</a>
                                            <?php elseif ($request['status'] === 'completed'): ?>
                                            <!-- In your action buttons section, update the links: -->
                                            <div class="quick-actions w-100">
                                                <a href="generate_id_card.php?student_id=<?= $request['student_pk_id'] ?>&mode=preview"
                                                    target="_blank" class="btn btn-success">👁️ View</a>
                                                <a href="generate_id_card.php?student_id=<?= $request['student_pk_id'] ?>&mode=download"
                                                    class="btn btn-success">
                                                    📥 Download</a>
                                                <a href="generate_id_card.php?student_id=<?= $request['student_pk_id'] ?>&mode=print"
                                                    target="_blank" class="btn btn-success">🖨️ Print</a>
                                                <a href="generate_id_card.php?email_id=<?= $request['student_pk_id'] ?>&template=id_template.html"
                                                    class="btn btn-success">📧 Email</a>
                                            </div>
                                            <?php endif; ?>
                                            <a href="student_details.php?id=<?= $request['student_id'] ?>"
                                                class="btn btn-outline-dark mt-1">View Student</a>
                                        </div>

                                        <!-- ID Preview for Completed Requests -->
                                        <?php if ($request['status'] === 'completed'):
    /* fetch student row once (we only have the request row) */
    $sigStmt = $conn->prepare("SELECT signature, first_name, last_name, student_id, course, year_level, blood_type, emergency_contact FROM student WHERE id = ?");
    $sigStmt->bind_param('i', $request['student_pk_id']);
    $sigStmt->execute();
    $stu = $sigStmt->get_result()->fetch_assoc();
    $sigStmt->close();

    $photoPath = $request['photo'] ? '../../uploads/student_photos/'.htmlspecialchars($request['photo'])
                                   : '../../assets/img/default_user.png';
    $signPath  = $stu['signature'] ? '../../uploads/student_signatures/'.htmlspecialchars($stu['signature'])
                                   : '../../assets/img/default_sign.png';
    $bgPath    = '../../assets/img/bg.jpg';
    $logoPath  = '../../assets/img/kldlogo.png';
?>
                                        <div class="mt-2 p-2 border rounded">
                                            <small class="text-muted d-block mb-1">ID Preview (actual size):</small>
                                            <div class="id-preview mx-auto"
                                                style="max-width:315px;background:#fff;border:1px solid #dee2e6;border-radius:8px;overflow:hidden">
                                                <div
                                                    style="width:315px;height:201.6px;position:relative;font-family:Arial,Helvetica,sans-serif;color:#000">

                                                    <!-- FRONT -->
                                                    <div
                                                        style="width:100.8px;height:151.2px;position:absolute;top:25.2px;left:56.7px;background-size:cover;background-image:url(<?= $bgPath ?>);border-radius:4px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.35);display:flex;flex-direction:column;align-items:center">
                                                        <img src="<?= $logoPath ?>"
                                                            style="height:21px;margin:6px 0 3px">
                                                        <img src="<?= $photoPath ?>"
                                                            style="width:36px;height:45px;object-fit:cover;border:1px solid #fff;border-radius:2px;margin:3px 0">
                                                        <div
                                                            style="font-size:6.6px;font-weight:bold;margin-top:1px;text-align:center;line-height:1.1">
                                                            <?= htmlspecialchars($stu['first_name']) ?><br><?= htmlspecialchars($stu['last_name']) ?>
                                                        </div>
                                                        <div style="font-size:5.4px">
                                                            <?= htmlspecialchars($stu['student_id']) ?></div>
                                                        <div style="font-size:4.8px">
                                                            <?= htmlspecialchars($stu['course']) ?> - Yr
                                                            <?= htmlspecialchars($stu['year_level']) ?></div>
                                                        <div
                                                            style="font-size:4.2px;align-self:flex-start;margin-left:8px;margin-top:auto;margin-bottom:9px">
                                                            Blood:
                                                            <?= htmlspecialchars($stu['blood_type']) ?><br>Emergency:
                                                            <?= htmlspecialchars($stu['emergency_contact']) ?></div>
                                                    </div>

                                                    <!-- BACK -->
                                                    <div
                                                        style="width:100.8px;height:151.2px;position:absolute;top:25.2px;right:56.7px;background-size:cover;background-image:url(<?= $bgPath ?>);border-radius:4px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.35);display:flex;flex-direction:column;align-items:center;justify-content:center;transform:rotate(180deg)">
                                                        <div style="font-size:5px;text-align:center;line-height:1.2">
                                                            <strong>IMPORTANT</strong><br>This card is property of the
                                                            school.<br>If found, please return to registrar.</div>
                                                        <div style="font-size:4.5px;margin-top:3px"><strong>Valid A.Y.
                                                                2025-2026</strong></div>
                                                        <img src="<?= $signPath ?>" style="height:12px;margin-top:3px">
                                                        <div
                                                            style="width:60px;height:12px;background:#fff;border-radius:2px;margin-top:3px">
                                                            <!-- barcode -->
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <!-- ===== END fixed preview ===== -->
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
                            <textarea name="reason" class="form-control" rows="3"
                                placeholder="Please provide a reason for rejection..." required></textarea>
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
    rejectModal.addEventListener('show.bs.modal', function(event) {
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