<?php
require_once "../../includes/db_connect.php";
session_start();

/* ---------- auth ---------- */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Admin only");
}

/* ---------- student data ---------- */
$student_id = intval($_GET['student_id'] ?? 0);
$stmt = $conn->prepare(
 "SELECT s.id, s.first_name, s.last_name, s.student_id, s.course, s.year_level,
         s.photo, s.blood_type, s.emergency_contact, s.signature
  FROM   student s
  WHERE  s.id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$s) {
    http_response_code(404);
    exit("Student not found");
}

/* ---------- file paths ---------- */
$photo = $s['photo'] ? "../../uploads/student_photos/" . $s['photo']
                     : "../../assets/img/default_user.png";
$sign  = $s['signature'] ? "../../uploads/student_signatures/" . $s['signature']
                          : "../../assets/img/default_sign.png";
$bg    = "../../assets/img/bg.jpg";               // 1050 × 672 px
$outDir= "../../uploads/digital_ids/";
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

/* ---------- POST receiver – save JPG ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jpg'])) {
    $blob = file_get_contents($_FILES['jpg']['tmp_name']);
    $fname= "digital_id_{$student_id}.jpg";
    $path = $outDir . $fname;
    if ($blob && file_put_contents($path, $blob)) {
        $stmt=$conn->prepare("UPDATE student
                              SET digital_id_path=?, digital_id_generated_at=NOW()
                              WHERE id=?");
        $stmt->bind_param("si", $fname, $student_id);
        $stmt->execute();
        $stmt->close();
        echo 'ok';
    } else {
        http_response_code(500);
        echo 'save error';
    }
    exit;
}

/* ---------- GET – render capture page ---------- */
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>ID – <?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
#cardCanvas{width:1050px;height:672px;margin:0 auto;display:flex;justify-content:center;align-items:center;gap:40px}
.face{width:336px;height:504px;background-size:cover;background-position:center;border-radius:12px;overflow:hidden;box-shadow:0 0 10px rgba(0,0,0,.35);font-family:Arial,Helvetica,sans-serif;color:#000;display:flex;flex-direction:column;align-items:center}
.front{background-image:url(<?= $bg ?>)}
.back{background-image:url(<?= $bg ?>);}
.photo{width:120px;height:150px;object-fit:cover;border:3px solid #fff;border-radius:8px;margin:15px 0}
.logo{height:70px;margin:20px 0 10px}
.data{display:flex;flex-direction:column;align-items:center;gap:8px;margin-bottom:30px}
.name{font-size:22px;font-weight:bold}
.idno{font-size:18px}
.course{font-size:16px}
.blood,.emergency{align-self:flex-start;margin-left:30px;font-size:14px}
.back .content{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:15px}
.signature{height:40px}
.barcode{height:40px;width:200px;background:#fff;border-radius:4px}
</style>
</head>
<body>
<div id="cardCanvas">
  <div class="face front">
    <img src="../../assets/img/kldlogo.png" class="logo">
    <img src="<?= htmlspecialchars($photo) ?>" class="photo">
    <div class="data">
      <div class="name"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
      <div class="idno"><?= htmlspecialchars($s['student_id']) ?></div>
      <div class="course"><?= htmlspecialchars($s['course']) ?> – Year <?= htmlspecialchars($s['year_level']) ?></div>
      <div class="blood">Blood: <?= htmlspecialchars($s['blood_type']) ?></div>
      <div class="emergency">Emergency: <?= htmlspecialchars($s['emergency_contact']) ?></div>
    </div>
  </div>
  <div class="face back">
    <div class="content">
      <h3>IMPORTANT</h3>
      <p>This card is property of the school.<br>If found, please return to the registrar.</p>
      <p><strong>Valid for A.Y. 2025-2026</strong></p>
<?php if (empty($s['signature'])): ?>
      <div class="signature-text"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
<?php else: ?>
      <img src="<?= htmlspecialchars($sign) ?>" class="signature">
<?php endif; ?>
      <div class="barcode"></div>
    </div>
  </div>
</div>
<script>
html2canvas(document.getElementById('cardCanvas'), {
    width: 1050,
    height: 672,
    scale: 2,
    useCORS: true,
    backgroundColor: '#ffffff'   // force white
}).then(canvas => {
    canvas.toBlob(blob => {
        const fd = new FormData();
        fd.append('jpg', blob, 'digital_id_<?= $student_id ?>.jpg');
        fetch('', {method: 'POST', body: fd})
          .then(r => r.text())
          .then(res => {
              if (res === 'ok') {
                  location.href = '?student_id=<?= $student_id ?>&saved=1';
              } else {
                  alert('Save failed');
              }
          });
    }, 'image/jpeg', 0.95);
});
</script>
</body>
</html>
<?php
/* ---------- flash & redirect after save ---------- */
if (isset($_GET['saved'])) {
    $_SESSION['success'] = 'Digital ID generated & saved successfully.';
    header('Location: admin_id.php');
    exit;
}