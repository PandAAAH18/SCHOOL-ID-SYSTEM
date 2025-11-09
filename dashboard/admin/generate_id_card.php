<?php
require_once "../../includes/db_connect.php";
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); exit("Admin only");
}

$student_id = intval($_GET['student_id'] ?? 0);
$stmt = $conn->prepare(
 "SELECT s.first_name, s.last_name, s.student_id, s.course, s.year_level,
         s.photo, s.blood_type, s.emergency_contact, s.signature
  FROM student s WHERE s.id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$s) { http_response_code(404); exit("Student not found"); }

$photo = $s['photo'] ? "../../uploads/student_photos/" . htmlspecialchars($s['photo'])
                     : "../../assets/img/default_user.png";
$sign  = $s['signature'] ? "../../uploads/student_signatures/" . htmlspecialchars($s['signature'])
                         : "../../assets/img/default_sign.png";
$bg    = "../../assets/img/bg.jpg";   // 1050×672 px – same folder
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>ID – <?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
/* ---------- canvas wrapper – EXACT 1050×672 px ---------- */
#cardCanvas{
 width:1050px; height:672px; margin:20px auto;
 background:#f5f5f5; display:flex; justify-content:center; align-items:center; gap:40px;
}

/* ---------- card face – 336×504 px ---------- */
.face{
 width:336px; height:504px; background-size:cover; background-position:center;
 border-radius:12px; overflow:hidden; box-shadow:0 0 10px rgba(0,0,0,.35);
 font-family:Arial,Helvetica,sans-serif; color:#000;
 display:flex; flex-direction:column; align-items:center;
}

.front{ background-image:url(<?= $bg ?>); }
.back { background-image:url(<?= $bg ?>); transform:rotate(180deg); }

/* ---------- shared content ---------- */
.photo{width:120px;height:150px;object-fit:cover;border:3px solid #fff;border-radius:8px;margin:15px 0}
.logo {height:70px;margin:20px 0 10px}
.data {display:flex;flex-direction:column;align-items:center;gap:8px;margin-bottom:30px}
.name {font-size:22px;font-weight:bold}
.idno {font-size:18px}
.course{font-size:16px}
.blood,.emergency{align-self:flex-start;margin-left:30px;font-size:14px}

.back .content{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:15px}
.back h3{margin:0}
.signature{height:40px}
.barcode{height:40px;width:200px;background:#fff;border-radius:4px}

/* ---------- buttons ---------- */
.buttons{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:10px}
button{padding:10px 20px;font-size:16px;cursor:pointer}

/* ---------- paper ---------- */
@page { size: ID; margin: 0 }   /* some drivers recognise “ID”, else 3.375in 2.125in */
@media print {
  body { margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact }
  img#printID {                    /* the image we inject into the pop-up */
     width: 3.375in;               /* CR80 width */
     height: 2.125in;              /* CR80 height */
     display: block;
     margin: 0 auto;               /* centre on page */
  }
}
</style>
</head>
<body>
<!-- ========== CARD TO RENDER ========== -->
<div id="cardCanvas">
  <!-- front -->
  <div class="face front">
    <img src="../../assets/img/kldlogo.png" class="logo">
    <img src="<?= $photo ?>" class="photo">
    <div class="data">
      <div class="name"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
      <div class="idno"><?= htmlspecialchars($s['student_id']) ?></div>
      <div class="course"><?= htmlspecialchars($s['course']) ?> – Year <?= htmlspecialchars($s['year_level']) ?></div>
      <div class="blood">Blood: <?= htmlspecialchars($s['blood_type']) ?></div>
      <div class="emergency">Emergency: <?= htmlspecialchars($s['emergency_contact']) ?></div>
    </div>
  </div>

  <!-- back -->
  <div class="face back">
    <div class="content">
      <h3>IMPORTANT</h3>
      <p>This card is property of the school.<br>If found, please return to the registrar.</p>
      <p><strong>Valid for A.Y. 2025-2026</strong></p>
      <!-- signature slot -->
<?php if (empty($s['signature'])): ?>
    <div class="signature-text"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
<?php else: ?>
    <img src="<?= $sign ?>" class="signature">
<?php endif; ?>
      <div class="barcode"></div>
    </div>
  </div>
</div>

<!-- ========== BUTTONS ========== -->
<div class="buttons">
  <button onclick="downloadJPG()">📥 Download JPG</button>
  <button onclick="printJPG()">🖨️ Print</button>
</div>

<script>
/* ---------- exact-pixel capture – no viewport scaling ---------- */
function capture(){
  const node   = document.getElementById('cardCanvas');
  return html2canvas(node, {
      width: 1050,   // real pixels
      height: 672,
      scale: 2,      // 2× = 2100×1344 px final image
      useCORS: true,
      backgroundColor: null
  });
}

function downloadJPG(){
  capture().then(canvas=>{
    const link = document.createElement('a');
    link.download = 'ID_<?= $s['student_id'] ?>.jpg';
    link.href = canvas.toDataURL('image/jpeg', 0.95);
    link.click();
  });
}

function printJPG(){
  capture().then(canvas=>{
    const win = window.open('', '_blank');
    const img = new Image();
    img.src = canvas.toDataURL('image/jpeg', 0.95);
    win.document.write('<html><head><title>Print ID</title></head><body style="margin:0">');
    win.document.write('<img src="'+img.src+'" style="width:100%" onload="window.print(); setTimeout(()=>win.close(), 500)">');
    win.document.write('</body></html>');
    win.document.close();
  });
}
</script>
</body>
</html>