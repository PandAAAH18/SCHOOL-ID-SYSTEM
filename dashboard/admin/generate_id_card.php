<?php
require_once "../../includes/db_connect.php";
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Admin only");
}

$student_id = intval($_GET['student_id'] ?? 0);
$mode       = $_GET['mode']          ?? 'preview';

$stmt = $conn->prepare(
 "SELECT s.first_name, s.last_name, s.student_id, s.course, s.year_level,
         s.photo, s.blood_type, s.emergency_contact, s.signature
  FROM student s WHERE s.id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$s) { http_response_code(404); exit("Student not found"); }

/* ---------- build URLs ---------- */
$photo = $s['photo'] ? "../../uploads/" . htmlspecialchars($s['photo'])
                     : "../../assets/img/default_user.png";
$sign  = $s['signature'] ? "../../uploads/" . htmlspecialchars($s['signature'])
                         : "../../assets/img/default_sign.png";
$bg    = "bg.jpg";   // 1050×672 px background – put in same folder
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>ID – <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></title>
<style>
/* ---------- paper ---------- */
@page{size:1050px 672px;margin:0}
body{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}

/* ---------- card shell ---------- */
.card{
 width:336px;height:504px;border-radius:12px;overflow:hidden;
 background-size:cover;background-position:center;
 box-shadow:0 0 10px rgba(0,0,0,.35);
 font-family:Arial,Helvetica,sans-serif;color:#000;
 display:flex;flex-direction:column;
}

/* ---------- front ---------- */
.front{background-image:url(<?= $bg ?>)}
.front .logo{height:70px;margin:20px auto 0}
.front .photo{width:120px;height:150px;object-fit:cover;border:3px solid #fff;border-radius:8px;margin:10px auto}
.front .data{display:flex;flex-direction:column;align-items:center;gap:8px;margin:auto 0 30px}
.front .name{font-size:22px;font-weight:bold}
.front .idno{font-size:18px}
.front .course{font-size:16px}
.front .blood,.front .emergency{align-self:flex-start;margin-left:30px;font-size:14px}

/* ---------- back ---------- */
.back{background-image:url(<?= $bg ?>);transform:rotate(180deg)}
.back .content{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:15px}
.back h3{margin:0}
.back .signature{height:40px}
.back .barcode{height:40px;width:200px;background:#fff;border-radius:4px}

/* ---------- page wrapper ---------- */
.page{display:flex;justify-content:center;align-items:center;height:672px}
<?php if ($mode==='print') echo '@media print{body{margin:0}.page{page-break-after:always}}'; ?>
</style>
</head>
<body>
<!-- ========== FRONT ========== -->
<div class="page">
  <div class="card front">
    <img src="../../assets/img/kldlogo.png" class="logo">
    <img src="<?= $photo ?>" class="photo">
    <div class="data">
      <div class="name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
      <div class="idno"><?= htmlspecialchars($s['student_id']) ?></div>
      <div class="course"><?= htmlspecialchars($s['course']) ?> – Year <?= htmlspecialchars($s['year_level']) ?></div>
      <div class="blood">Blood: <?= htmlspecialchars($s['blood_type']) ?></div>
      <div class="emergency">Emergency: <?= htmlspecialchars($s['emergency_contact']) ?></div>
    </div>
  </div>
</div>

<!-- ========== BACK ========== -->
<div class="page">
  <div class="card back">
    <div class="content">
      <h3>IMPORTANT</h3>
      <p>This card is property of the school.<br>If found, please return to the registrar.</p>
      <p><strong>Valid for A.Y. 2025-2026</strong></p>
      <img src="<?= $sign ?>" class="signature">
      <div class="barcode"></div>
    </div>
  </div>
</div>

<?php if ($mode==='print') echo '<script>window.print(); setTimeout(()=>window.close(),200);</script>'; ?>
</body>
</html>