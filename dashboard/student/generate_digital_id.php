<?php
require_once "../../includes/db_connect.php";
session_start();

// Allow both admin and student access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'student')) {
    http_response_code(403); 
    exit("Access denied");
}

$student_id = intval($_GET['student_id'] ?? ($_SESSION['role'] === 'student' ? $_SESSION['user_id'] : 0));

// Check if cached ID exists and is recent (less than 1 day old)
$cache_file = "../../uploads/digital_ids/id_{$student_id}.jpg";
$cache_duration = 86400; // 24 hours

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
    // Serve cached file
    header('Content-Type: image/jpeg');
    readfile($cache_file);
    exit;
}

// Generate new ID card
$stmt = $conn->prepare(
 "SELECT s.first_name, s.last_name, s.student_id, s.course, s.year_level,
         s.photo, s.blood_type, s.emergency_contact, s.signature
  FROM student s WHERE s.id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$s) { 
    http_response_code(404); 
    exit("Student not found"); 
}

// Generate the ID card HTML
ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
#cardCanvas{
 width:1050px; height:672px; margin:0; padding:0;
 background:#f5f5f5; display:flex; justify-content:center; align-items:center; gap:40px;
}

.face{
 width:336px; height:504px; background-size:cover; background-position:center;
 border-radius:12px; overflow:hidden; box-shadow:0 0 10px rgba(0,0,0,.35);
 font-family:Arial,Helvetica,sans-serif; color:#000;
 display:flex; flex-direction:column; align-items:center;
}

.front{ background-image:url(<?= $bg ?>); }
.back { background-image:url(<?= $bg ?>); transform:rotate(180deg); }

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
</style>
</head>
<body>
<div id="cardCanvas">
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

  <div class="face back">
    <div class="content">
      <h3>IMPORTANT</h3>
      <p>This card is property of the school.<br>If found, please return to the registrar.</p>
      <p><strong>Valid for A.Y. 2025-2026</strong></p>
      <?php if (empty($s['signature'])): ?>
        <div class="signature-text"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
      <?php else: ?>
        <img src="<?= $sign ?>" class="signature">
      <?php endif; ?>
      <div class="barcode"></div>
    </div>
  </div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

// Convert to image using html2canvas (via Node.js server or PHP library)
// For this example, we'll use a PHP HTML to image converter
require_once '../../includes/html2image.php'; // You'll need to install a PHP library

$image_data = convertHtmlToImage($html); // Your conversion function

// Save to cache
if (!is_dir('../../uploads/digital_ids')) {
    mkdir('../../uploads/digital_ids', 0755, true);
}
file_put_contents($cache_file, $image_data);

// Output the image
header('Content-Type: image/jpeg');
echo $image_data;
?>