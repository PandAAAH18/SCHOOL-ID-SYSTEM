<?php
require_once "../../includes/db_connect.php";
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); exit("Admin only");
}

/* ---------- 1. grab student list ---------- */
$ids = [];
if (isset($_GET['ids'])) {                       // comma-separated
    $ids = array_map('intval', explode(',', $_GET['ids']));
} elseif ($_GET['filter'] ?? '' === 'completed') { // bulk by status
    $stmt = $conn->query("SELECT DISTINCT student_id FROM id_requests WHERE status='completed'");
    while ($row = $stmt->fetch_assoc()) $ids[] = (int)$row['student_id'];
}
if (!$ids) exit("No students selected");

/* ---------- 2. pull data ---------- */
$place = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("SELECT id,first_name,last_name,student_id,course,year_level,photo,blood_type,emergency_contact,signature
                        FROM student WHERE id IN ($place)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$bg = '../../assets/img/bg.jpg';   // 1050×672 px – same folder (adjust path)
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Bulk ID Generator</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
/* ---------- page ---------- */
@page{size:A4 portrait;margin:5mm}
@media print{
  body{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  #bulkCanvas img{width:100%;display:block}
}

/* ---------- canvas wrapper ---------- */
#bulkCanvas{
 width:3150px;                /* 3 cards per row → 3×1050 */
 background:#fff; display:flex; flex-wrap:wrap; gap:10px;
 padding:10px; box-sizing:border-box;
}

/* ---------- single card ---------- */
.card{
 width:1050px; height:672px; background-size:cover;
 position:relative;            /* we’ll absolutely-place the face inside */
}
.face{
 width:336px; height:504px; position:absolute; top:84px;   /* (672-504)/2 */
 background-size:cover; border-radius:12px; overflow:hidden;
 box-shadow:0 0 10px rgba(0,0,0,.35);
 font-family:Arial,Helvetica,sans-serif; color:#000;
 display:flex; flex-direction:column; align-items:center;
}
.front{left:187px;background-image:url(<?= $bg ?>);}
.back {right:187px;background-image:url(<?= $bg ?>);transform:rotate(180deg);}

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
</style>
</head>
<body>
<!-- ========== BULK CANVAS ========== -->
<div id="bulkCanvas">
<?php
$logo = '../../assets/img/kldlogo.png';
foreach ($students as $s):
  $photo = $s['photo'] ? "../../uploads/student_photos/" . htmlspecialchars($s['photo'])
                       : "../../assets/img/default_user.png";
  $sign  = $s['signature'] ? "../../uploads/student_signatures/" . htmlspecialchars($s['signature'])
                           : "../../assets/img/default_sign.png";
?>
  <!-- ===== one student ===== -->
  <div class="card">
    <!-- front -->
    <div class="face front">
      <img src="<?= $logo ?>" class="logo">
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
        <img src="<?= $sign ?>" class="signature">
        <div class="barcode"></div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- ========== BUTTONS ========== -->
<div class="buttons">
  <button onclick="downloadBulkJPG()">📥 Download Bulk JPG</button>
  <button onclick="printBulkJPG()">🖨️ Print Bulk</button>
</div>

<script>
/* ---------- exact-pixel capture ---------- */
function captureBulk(){
  const node = document.getElementById('bulkCanvas');
  return html2canvas(node, {
      width: node.scrollWidth,   // 3150 px (or whatever)
      height: node.scrollHeight, // 672 px * rows
      scale: 2,                  // 2× for crisp print
      useCORS: true,
      backgroundColor: '#ffffff'
  });
}

function downloadBulkJPG(){
  captureBulk().then(canvas=>{
    const link = document.createElement('a');
    link.download = 'Bulk_ID_<?= date('Y-m-d_His') ?>.jpg';
    link.href = canvas.toDataURL('image/jpeg', 0.95);
    link.click();
  });
}

function printBulkJPG(){
  captureBulk().then(canvas=>{
    const win = window.open('', '_blank');
    const img = new Image();
    img.src = canvas.toDataURL('image/jpeg', 0.95);
    win.document.write('<html><head><title>Bulk ID Print</title></head><body style="margin:0">');
    win.document.write('<img src="'+img.src+'" style="width:100%" onload="window.print(); setTimeout(()=>win.close(), 500)">');
    win.document.write('</body></html>');
    win.document.close();
  });
}
</script>
</body>
</html>