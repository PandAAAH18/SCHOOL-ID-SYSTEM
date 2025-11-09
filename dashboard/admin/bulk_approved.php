<?php
session_start();
require_once("../../includes/db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit();
}

/* ---------- pull ONLY approved ---------- */
$stmt = $conn->prepare(
 "SELECT ir.id            AS request_id,
         ir.student_id    AS student_pk_id,
         s.first_name, s.last_name, s.student_id, s.course, s.year_level,
         s.photo, s.blood_type, s.emergency_contact, s.signature
  FROM   id_requests ir
  JOIN   student      s ON ir.student_id = s.id
  WHERE  ir.status = 'approved'
  ORDER  BY s.last_name, s.first_name");
$stmt->execute();
$approved = $stmt->get_result();
$stmt->close();

$bg  = '../bg.jpg';          // 1050×672 px background
$logo= '../../assets/img/school_logo.png';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Bulk Approved IDs</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<style>
/* ---------- card strip ---------- */
#bulkCanvas{
  width:3150px;                /* 3 cards per row */
  background:#fff; display:flex; flex-wrap:wrap; gap:10px;
  padding:10px; box-sizing:border-box;
}
.card{
  width:1050px; height:672px; position:relative;
  background-size:cover; background-position:center;
}
.face{
  width:336px; height:504px; position:absolute; top:84px;
  background-size:cover; border-radius:12px; overflow:hidden;
  box-shadow:0 0 10px rgba(0,0,0,.35);
  font-family:Arial,Helvetica,sans-serif; color:#000;
  display:flex; flex-direction:column; align-items:center;
}
.front{left:187px;background-image:url(<?= $bg ?>);}
.back {right:187px;background-image:url(<?= $bg ?>);transform:rotate(180deg);}

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

/* ---------- utilities ---------- */
.buttons{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:10px}
button{padding:10px 20px;font-size:16px;cursor:pointer}

#searchBox{max-width:400px}
.student-card{display:block}          /* we toggle this class */
.d-none{display:none !important}
</style>
</head>
<body class="bg-light">
<?php include '../../includes/header_admin.php'; ?>

<div class="container mt-4">
  <h2>Bulk Generate – Approved Requests</h2>

  <!-- ========== NEW : search / filter ========== -->
  <div class="card shadow mb-3">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
      <input id="searchBox" class="form-control" placeholder="🔍  Type name, ID, course or year…">
      <button class="btn btn-sm btn-secondary" onclick="selectAll(true)">Select All</button>
      <button class="btn btn-sm btn-secondary" onclick="selectAll(false)">Deselect All</button>
      <button class="btn btn-success" onclick="downloadBulkJPG()">📥 Download JPG</button>
      <button class="btn btn-info"    onclick="printBulkJPG()">🖨️ Print Bulk</button>
      <small class="text-muted ms-auto">Tick the cards you want, then hit Download or Print.</small>
    </div>
  </div>

  <!-- hidden canvas for rendering -->
  <div id="bulkCanvas"></div>

  <!-- ========== student cards ========== -->
  <div class="row" id="cardRow">   <!-- wrapper for live search -->
  <?php while ($row = $approved->fetch_assoc()):
        /* build one string that contains every searchable field */
        $searchStr = strtolower($row['last_name'].' '.$row['first_name'].' '.
                               $row['student_id'].' '.$row['course'].' '.$row['year_level']);
  ?>
    <div class="col-md-6 col-lg-4 mb-4 student-card" data-search="<?= htmlspecialchars($searchStr) ?>">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <input type="checkbox" class="form-check-input student-chk" value="<?= $row['student_pk_id'] ?>">
          <img src="<?= $row['photo'] ? '../../uploads/student_photo/'.htmlspecialchars($row['photo']) : '../../assets/img/default_user.png' ?>"
               class="rounded" width="60" height="60" style="object-fit:cover">
          <div>
            <strong><?= htmlspecialchars($row['last_name'].', '.$row['first_name']) ?></strong><br>
            <small><?= htmlspecialchars($row['student_id']) ?></small><br>
            <small><?= htmlspecialchars($row['course']) ?> - Year <?= htmlspecialchars($row['year_level']) ?></small>
          </div>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
  </div>

  <?php if ($approved->num_rows === 0): ?>
    <div class="alert alert-warning">No approved requests at the moment.</div>
  <?php endif; ?>
</div>

<script>
/* ---------- live filter ---------- */
document.getElementById('searchBox').addEventListener('input', function(){
  const needle = this.value.toLowerCase();
  document.querySelectorAll('.student-card').forEach(card => {
    card.classList.toggle('d-none', !card.dataset.search.includes(needle));
  });
});

/* ---------- collect / download / print ---------- */
function getSelected(){
  return Array.from(document.querySelectorAll('.student-chk:checked')).map(chk => chk.value);
}
function downloadBulkJPG(){
  const ids = getSelected();
  if (!ids.length){ alert('No students selected'); return; }
  location.href = 'generate_id_bulk.php?ids=' + ids.join(',');
}
function printBulkJPG(){
  const ids = getSelected();
  if (!ids.length){ alert('No students selected'); return; }
  window.open('generate_id_bulk.php?ids=' + ids.join(',') + '&print=1', '_blank');
}
function selectAll(checked){
  document.querySelectorAll('.student-chk').forEach(chk => chk.checked = checked);
}
</script>
</body>
</html>