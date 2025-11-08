<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../index.php");
  exit();
}

// Get active template
$template = $conn->query("SELECT * FROM id_templates WHERE is_active = 1 LIMIT 1")->fetch_assoc();
if (!$template) {
    // Default template if none active
    $template = [
        'front_background' => 'background: #ffffff;',
        'back_background' => 'background: #f8f9fa;',
        'front_css' => '.id-front { padding: 20px; }',
        'back_css' => '.id-back { padding: 20px; }',
        'name' => 'Default'
    ];
}

// Get student data
$student_id = $_GET['student_id'] ?? '';
$mode = $_GET['mode'] ?? 'preview';

if ($student_id) {
    $stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ? OR id = ?");
    $stmt->bind_param("ss", $student_id, $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$student) {
    die("Student not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Card - <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></title>
    <style>
        @media print {
            @page {
                size: landscape;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .id-page {
                page-break-after: always;
            }
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }

        .id-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .id-page {
            width: 8.5in;
            height: 5.5in;
            display: flex;
            gap: 10px;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .id-card {
            flex: 1;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
            min-height: 400px;
        }

        /* Apply template styles */
        #id-front {
            <?= $template['front_background'] ?>
        }

        #id-back {
            <?= $template['back_background'] ?>
        }

        <?= $template['front_css'] ?>
        <?= $template['back_css'] ?>

        .action-buttons {
            text-align: center;
            margin: 20px 0;
        }

        .template-info {
            text-align: center;
            margin-bottom: 10px;
            color: #6c757d;
            font-size: 14px;
        }

        /* Default fallback styles */
        .student-photo {
            width: 100px;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
        }

        .student-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .student-info {
            font-size: 14px;
            line-height: 1.4;
        }

        .student-id {
            font-weight: bold;
            color: #0d6efd;
        }

        .qr-code {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .emergency-info {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .school-info {
            text-align: center;
            margin-bottom: 20px;
        }

        /* Ensure content containers have proper structure */
        .id-front-content, .id-back-content {
            height: 100%;
            position: relative;
        }
    </style>
</head>
<body>
    <?php if ($mode === 'preview'): ?>
    <div class="no-print action-buttons">
        <button onclick="window.print()" class="btn btn-primary btn-lg">🖨️ Print ID Card</button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg">Close</button>
        <div class="template-info">
            Using template: <strong><?= htmlspecialchars($template['name']) ?></strong>
        </div>
    </div>
    <?php endif; ?>

    <div class="id-container">
        <div class="id-page">
            <!-- Front of ID -->
            <div class="id-card" id="id-front">
                <div class="id-front-content">
                    <img src="<?= $student['photo'] ? '../../uploads/' . htmlspecialchars($student['photo']) : '../../assets/img/default_user.png' ?>" 
                         alt="Student Photo" class="student-photo">
                    <div class="student-name">
                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                    </div>
                    <div class="student-info">
                        <div class="student-id">ID: <?= $student['student_id'] ?></div>
                        <div class="student-course"><?= htmlspecialchars($student['course'] ?? 'N/A') ?></div>
                        <div class="student-year"><?= htmlspecialchars($student['year_level'] ?? 'N/A') ?></div>
                        <div class="student-blood">Blood Type: <?= htmlspecialchars($student['blood_type'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>

            <!-- Back of ID -->
            <div class="id-card" id="id-back">
                <div class="id-back-content">
                    <div class="school-info">
                        <h3>SCHOOL NAME</h3>
                        <p>123 School Street<br>City, State 12345<br>Phone: (555) 123-4567</p>
                    </div>
                    
                    <div class="emergency-info">
                        <h4>EMERGENCY CONTACT</h4>
                        <p><?= htmlspecialchars($student['emergency_contact'] ?? 'N/A') ?></p>
                        <p><?= htmlspecialchars($student['address'] ?? 'N/A') ?></p>
                    </div>

                    <div class="qr-code">
                        QR CODE<br>
                        <small>ID: <?= $student['student_id'] ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($mode === 'print'): ?>
    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
    <?php endif; ?>
</body>
</html>
<?php $conn->close(); ?>