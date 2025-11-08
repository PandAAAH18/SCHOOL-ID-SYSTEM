<?php
session_start();
require_once("../../includes/db_connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Handle image upload
if (isset($_FILES['background_image'])) {
    $upload_dir = "../../uploads/templates/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['background_image']['name']);
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['background_image']['tmp_name'], $target_file)) {
        echo json_encode(['success' => true, 'file_path' => 'uploads/templates/' . $file_name]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Upload failed']);
    }
    exit();
}

// Handle template save
if (isset($_POST['save_template'])) {
    $template_id = $_POST['template_id'] ?? 0;
    $name = $_POST['name'];
    $front_background = $_POST['front_background'];
    $back_background = $_POST['back_background'];
    $front_css = $_POST['front_css'];
    $back_css = $_POST['back_css'];
    
    if ($template_id > 0) {
        // Update existing template
        $stmt = $conn->prepare("UPDATE id_templates SET name = ?, front_background = ?, back_background = ?, front_css = ?, back_css = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $front_background, $back_background, $front_css, $back_css, $template_id);
    } else {
        // Create new template
        $stmt = $conn->prepare("INSERT INTO id_templates (name, front_background, back_background, front_css, back_css, is_active) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssss", $name, $front_background, $back_background, $front_css, $back_css);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Template saved successfully!";
    } else {
        $_SESSION['error'] = "Failed to save template: " . $conn->error;
    }
    $stmt->close();
    
    header("Location: id_templates.php");
    exit();
}

// Handle template actions
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'activate' && isset($_GET['id'])) {
        $template_id = intval($_GET['id']);
        $conn->query("UPDATE id_templates SET is_active = 0");
        $conn->query("UPDATE id_templates SET is_active = 1 WHERE id = $template_id");
        $_SESSION['success'] = "Template activated successfully!";
    }
    elseif ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $template_id = intval($_GET['id']);
        $conn->query("DELETE FROM id_templates WHERE id = $template_id");
        $_SESSION['success'] = "Template deleted successfully!";
    }
    elseif ($_GET['action'] === 'duplicate' && isset($_GET['id'])) {
        $template_id = intval($_GET['id']);
        $template = $conn->query("SELECT * FROM id_templates WHERE id = $template_id")->fetch_assoc();
        if ($template) {
            $new_name = $template['name'] . " (Copy)";
            $conn->query("INSERT INTO id_templates (name, front_background, back_background, front_css, back_css, is_active) 
                         VALUES ('$new_name', '{$template['front_background']}', '{$template['back_background']}', '{$template['front_css']}', '{$template['back_css']}', 0)");
            $_SESSION['success'] = "Template duplicated successfully!";
        }
    }
    header("Location: id_templates.php");
    exit();
}

// Get all templates
$templates = $conn->query("SELECT * FROM id_templates ORDER BY is_active DESC, id ASC");
$active_template = $conn->query("SELECT * FROM id_templates WHERE is_active = 1 LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ID Templates | School ID System</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <style>
    .template-card {
        border: 2px solid #dee2e6;
        border-radius: 10px;
        transition: all 0.3s;
        cursor: pointer;
        background: white;
    }
    .template-card:hover {
        border-color: #0d6efd;
        transform: translateY(-2px);
    }
    .template-card.active {
        border-color: #198754;
        border-width: 3px;
    }
    .template-preview {
        width: 100%;
        height: 200px;
        position: relative;
        overflow: hidden;
    }
    .preview-front, .preview-back {
        width: 45%;
        height: 180px;
        border: 1px solid #ccc;
        display: inline-block;
        margin: 5px;
        position: relative;
        background-size: cover;
        background-position: center;
    }
    .css-editor {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        height: 150px;
    }
    .template-actions {
        padding: 10px;
    }
    .live-preview-container {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 15px;
        background: white;
        min-height: 300px;
    }
    #livePreviewFront, #livePreviewBack {
        min-height: 280px;
        border: 1px solid #ccc;
        border-radius: 8px;
        position: relative;
        overflow: hidden;
        background-size: cover;
        background-position: center;
    }
    .image-upload-container {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .image-upload-container:hover {
        border-color: #0d6efd;
        background: #f8f9fa;
    }
    .image-preview {
        max-width: 100%;
        max-height: 150px;
        border-radius: 5px;
        margin-top: 10px;
        display: none;
    }
    .bg-option {
        cursor: pointer;
        padding: 10px;
        border: 2px solid transparent;
        border-radius: 5px;
        margin: 5px;
        text-align: center;
    }
    .bg-option:hover {
        border-color: #0d6efd;
    }
    .bg-option.active {
        border-color: #198754;
        background: #e8f5e8;
    }
  </style>
</head>
<body class="bg-light">
  <?php include '../../includes/header_admin.php'; ?>
  
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>🎨 ID Template Designer</h2>
      <div>
        <a href="admin_id.php" class="btn btn-secondary btn-sm">← Back to ID Management</a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="newTemplate()">
          + New Template
        </button>
      </div>
    </div>

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

    <!-- Current Active Template -->
    <?php if($active_template): ?>
    <div class="card shadow mb-4">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0">✅ Active Template</h5>
      </div>
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h4><?= htmlspecialchars($active_template['name']) ?></h4>
            <p class="text-muted mb-0">This template is currently being used for all ID cards.</p>
          </div>
          <div class="col-md-4 text-end">
            <span class="badge bg-success fs-6">ACTIVE</span>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Template Gallery -->
    <div class="card shadow">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">📋 Template Gallery</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <?php if ($templates->num_rows > 0): ?>
            <?php while ($template = $templates->fetch_assoc()): ?>
              <div class="col-md-6 col-lg-4 mb-4">
                <div class="template-card <?= $template['is_active'] ? 'active' : '' ?>">
                  <!-- Template Preview -->
                  <div class="template-preview">
                    <div class="preview-front" style="<?= $template['front_background'] ?>">
                      <div style="position: absolute; top: 5px; left: 5px; background: rgba(0,0,0,0.7); color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px;">FRONT</div>
                      <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: inherit;">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.8); border-radius: 5px; margin: 0 auto 5px auto;"></div>
                        <div style="font-weight: bold; font-size: 12px;">John Doe</div>
                        <div style="font-size: 10px;">ID: 2023001</div>
                      </div>
                    </div>
                    <div class="preview-back" style="<?= $template['back_background'] ?>">
                      <div style="position: absolute; top: 5px; left: 5px; background: rgba(0,0,0,0.7); color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px;">BACK</div>
                      <div style="position: absolute; bottom: 10px; right: 10px; width: 30px; height: 30px; background: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; font-size: 8px;">
                        QR
                      </div>
                    </div>
                  </div>
                  
                  <div class="template-actions">
                    <h6><?= htmlspecialchars($template['name']) ?></h6>
                    <div class="btn-group w-100">
                      <?php if(!$template['is_active']): ?>
                        <button class="btn btn-success btn-sm" onclick="activateTemplate(<?= $template['id'] ?>)">Activate</button>
                      <?php else: ?>
                        <button class="btn btn-success btn-sm" disabled>Active</button>
                      <?php endif; ?>
                      <button class="btn btn-warning btn-sm" onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">Edit</button>
                      <button class="btn btn-secondary btn-sm" onclick="duplicateTemplate(<?= $template['id'] ?>)">Copy</button>
                      <?php if(!$template['is_active']): ?>
                        <button class="btn btn-danger btn-sm" onclick="deleteTemplate(<?= $template['id'] ?>)">Delete</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="col-12 text-center py-4">
              <p class="text-muted">No templates found. Create your first template!</p>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="newTemplate()">
                + Create First Template
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Template Editor Modal -->
  <div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <form method="POST" id="templateForm" action="id_templates.php">
          <input type="hidden" name="template_id" id="template_id">
          <input type="hidden" name="save_template" value="1">
          <input type="file" id="imageUpload" name="background_image" accept="image/*" style="display: none;">
          
          <div class="modal-header">
            <h5 class="modal-title" id="templateModalLabel">Create New Template</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Template Name</label>
                  <input type="text" class="form-control" name="name" id="template_name" required>
                </div>
                
                <div class="card mb-3">
                  <div class="card-header">
                    <h6 class="mb-0">🖼️ Front Background</h6>
                  </div>
                  <div class="card-body">
                    <!-- Image Upload for Front -->
                    <div class="image-upload-container" onclick="openImageUpload('front')">
                      <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                      <div>Click to upload front background image</div>
                      <small class="text-muted">Recommended: 300x180px or larger</small>
                      <img id="frontImagePreview" class="image-preview" alt="Front background preview">
                    </div>
                    
                    <!-- Background Options -->
                    <div class="row">
                      <div class="col-4">
                        <div class="bg-option" onclick="setBackground('front', 'background: #ffffff; color: #000000;')">
                          <div style="width: 100%; height: 40px; background: #ffffff; border: 1px solid #ddd;"></div>
                          <small>White</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="bg-option" onclick="setBackground('front', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;')">
                          <div style="width: 100%; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                          <small>Blue Gradient</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="bg-option" onclick="setBackground('front', 'background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;')">
                          <div style="width: 100%; height: 40px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"></div>
                          <small>Pink Gradient</small>
                        </div>
                      </div>
                    </div>
                    
                    <textarea class="form-control mt-3" name="front_background" id="front_background" rows="2" placeholder="background: url('path/to/image.jpg'); background-size: cover;" readonly></textarea>
                  </div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Front Layout CSS</label>
                  <textarea class="form-control css-editor" name="front_css" id="front_css" rows="6" placeholder=".student-name { font-size: 24px; font-weight: bold; }"></textarea>
                  <small class="text-muted">CSS for front side layout and styling</small>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="card mb-3">
                  <div class="card-header">
                    <h6 class="mb-0">🖼️ Back Background</h6>
                  </div>
                  <div class="card-body">
                    <!-- Image Upload for Back -->
                    <div class="image-upload-container" onclick="openImageUpload('back')">
                      <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                      <div>Click to upload back background image</div>
                      <small class="text-muted">Recommended: 300x180px or larger</small>
                      <img id="backImagePreview" class="image-preview" alt="Back background preview">
                    </div>
                    
                    <!-- Background Options -->
                    <div class="row">
                      <div class="col-4">
                        <div class="bg-option" onclick="setBackground('back', 'background: #f8f9fa; color: #000000;')">
                          <div style="width: 100%; height: 40px; background: #f8f9fa; border: 1px solid #ddd;"></div>
                          <small>Light Gray</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="bg-option" onclick="setBackground('back', 'background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;')">
                          <div style="width: 100%; height: 40px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"></div>
                          <small>Blue Gradient</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="bg-option" onclick="setBackground('back', 'background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;')">
                          <div style="width: 100%; height: 40px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);"></div>
                          <small>Green Gradient</small>
                        </div>
                      </div>
                    </div>
                    
                    <textarea class="form-control mt-3" name="back_background" id="back_background" rows="2" placeholder="background: url('path/to/image.jpg'); background-size: cover;" readonly></textarea>
                  </div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Back Layout CSS</label>
                  <textarea class="form-control css-editor" name="back_css" id="back_css" rows="6" placeholder=".qr-code { position: absolute; bottom: 20px; right: 20px; }"></textarea>
                  <small class="text-muted">CSS for back side layout and styling</small>
                </div>
                
                <div class="card">
                  <div class="card-header">
                    <h6 class="mb-0">💡 CSS Classes Reference</h6>
                  </div>
                  <div class="card-body">
                    <small>
                      <strong>Front Side Classes:</strong><br>
                      .id-front - Main container<br>
                      .student-photo - Student photo<br>
                      .student-name - Student name<br>
                      .student-info - Info container<br>
                      .student-id - ID number<br>
                      .student-course - Course<br>
                      .student-year - Year level<br>
                      .student-blood - Blood type<br>
                      <br>
                      <strong>Back Side Classes:</strong><br>
                      .id-back - Main container<br>
                      .qr-code - QR code area<br>
                      .emergency-info - Emergency contact<br>
                      .school-info - School information<br>
                    </small>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Live Preview -->
            <div class="card mt-3">
              <div class="card-header">
                <h6 class="mb-0">👁️ Live Preview</h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <h6>Front Side</h6>
                    <div id="livePreviewFront" class="live-preview-container">
                      <!-- Front preview content will be inserted by JavaScript -->
                    </div>
                  </div>
                  <div class="col-md-6">
                    <h6>Back Side</h6>
                    <div id="livePreviewBack" class="live-preview-container">
                      <!-- Back preview content will be inserted by JavaScript -->
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Template</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
  <script>
    let livePreviewStyle = null;
    let currentUploadSide = 'front';

    function newTemplate() {
      document.getElementById('templateModalLabel').textContent = 'Create New Template';
      document.getElementById('template_id').value = '';
      document.getElementById('template_name').value = 'My New Template';
      document.getElementById('front_background').value = 'background: #ffffff; color: #000000;';
      document.getElementById('back_background').value = 'background: #f8f9fa; color: #000000;';
      document.getElementById('front_css').value = `.id-front {
  padding: 20px;
  position: relative;
  height: 100%;
}

.student-photo {
  width: 100px;
  height: 120px;
  border-radius: 8px;
  border: 3px solid rgba(255,255,255,0.8);
  position: absolute;
  top: 50px;
  left: 30px;
}

.student-name {
  font-size: 24px;
  font-weight: bold;
  position: absolute;
  top: 60px;
  left: 150px;
  margin: 0;
  text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

.student-info {
  position: absolute;
  top: 100px;
  left: 150px;
  font-size: 14px;
  line-height: 1.4;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}

.student-id {
  font-weight: bold;
  font-size: 16px;
}`;
      document.getElementById('back_css').value = `.id-back {
  padding: 20px;
  position: relative;
  height: 100%;
}

.qr-code {
  position: absolute;
  bottom: 30px;
  right: 30px;
  width: 80px;
  height: 80px;
  background: white;
  border: 2px solid rgba(0,0,0,0.3);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.emergency-info {
  position: absolute;
  top: 50px;
  left: 30px;
  right: 30px;
  background: rgba(255,255,255,0.9);
  padding: 15px;
  border-radius: 8px;
  color: #000;
}

.school-info {
  position: absolute;
  top: 180px;
  left: 30px;
  right: 30px;
  text-align: center;
  color: inherit;
}`;
      
      // Clear image previews
      document.getElementById('frontImagePreview').style.display = 'none';
      document.getElementById('backImagePreview').style.display = 'none';
      
      updateLivePreview();
    }

    function editTemplate(template) {
      document.getElementById('templateModalLabel').textContent = 'Edit Template: ' + template.name;
      document.getElementById('template_id').value = template.id;
      document.getElementById('template_name').value = template.name;
      document.getElementById('front_background').value = template.front_background;
      document.getElementById('back_background').value = template.back_background;
      document.getElementById('front_css').value = template.front_css;
      document.getElementById('back_css').value = template.back_css;
      
      // Show image previews if background contains image
      if (template.front_background.includes('url(')) {
        const frontUrl = template.front_background.match(/url\(['"]?(.*?)['"]?\)/);
        if (frontUrl) {
          document.getElementById('frontImagePreview').src = '../../' + frontUrl[1];
          document.getElementById('frontImagePreview').style.display = 'block';
        }
      }
      
      if (template.back_background.includes('url(')) {
        const backUrl = template.back_background.match(/url\(['"]?(.*?)['"]?\)/);
        if (backUrl) {
          document.getElementById('backImagePreview').src = '../../' + backUrl[1];
          document.getElementById('backImagePreview').style.display = 'block';
        }
      }
      
      updateLivePreview();
    }

    function openImageUpload(side) {
      currentUploadSide = side;
      document.getElementById('imageUpload').click();
    }

    function setBackground(side, css) {
      document.getElementById(side + '_background').value = css;
      document.getElementById(side + 'ImagePreview').style.display = 'none';
      updateLivePreview();
    }

    // Handle image upload
    document.getElementById('imageUpload').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const formData = new FormData();
        formData.append('background_image', file);
        
        fetch('id_templates.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const css = `background: url('${data.file_path}'); background-size: cover; background-position: center;`;
            document.getElementById(currentUploadSide + '_background').value = css;
            
            // Show preview
            const preview = document.getElementById(currentUploadSide + 'ImagePreview');
            preview.src = '../../' + data.file_path;
            preview.style.display = 'block';
            
            updateLivePreview();
          } else {
            alert('Upload failed: ' + data.error);
          }
        })
        .catch(error => {
          alert('Upload error: ' + error);
        });
        
        // Clear the file input
        e.target.value = '';
      }
    });

    function updateLivePreview() {
      const frontPreview = document.getElementById('livePreviewFront');
      const backPreview = document.getElementById('livePreviewBack');
      
      // Get CSS values
      const frontBackground = document.getElementById('front_background').value;
      const backBackground = document.getElementById('back_background').value;
      const frontCSS = document.getElementById('front_css').value;
      const backCSS = document.getElementById('back_css').value;
      
      // Clear previous content
      frontPreview.innerHTML = '';
      backPreview.innerHTML = '';
      
      // Remove previous style element
      if (livePreviewStyle) {
        livePreviewStyle.remove();
      }
      
      // Create new style element for live preview
      livePreviewStyle = document.createElement('style');
      livePreviewStyle.textContent = `
        #livePreviewFront {
          ${frontBackground}
          min-height: 280px;
          border-radius: 8px;
          position: relative;
          overflow: hidden;
        }
        #livePreviewBack {
          ${backBackground}
          min-height: 280px;
          border-radius: 8px;
          position: relative;
          overflow: hidden;
        }
        ${frontCSS}
        ${backCSS}
      `;
      document.head.appendChild(livePreviewStyle);
      
      // Add sample content to front preview
      frontPreview.innerHTML = `
        <div class="id-front">
          <div class="student-photo" style="background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px; border: 3px solid rgba(255,255,255,0.8);">PHOTO</div>
          <div class="student-name">John Doe</div>
          <div class="student-info">
            <div class="student-id">ID: 2023001</div>
            <div class="student-course">Computer Science</div>
            <div class="student-year">3rd Year</div>
            <div class="student-blood">Blood Type: O+</div>
          </div>
        </div>
      `;
      
      // Add sample content to back preview
      backPreview.innerHTML = `
        <div class="id-back">
          <div class="emergency-info">
            <strong>EMERGENCY CONTACT</strong><br>
            +1-555-0123<br>
            123 Main St, City
          </div>
          <div class="school-info">
            <strong>SCHOOL NAME</strong><br>
            Excellence in Education
          </div>
          <div class="qr-code">
            QR CODE
          </div>
        </div>
      `;
    }

    function activateTemplate(id) {
      if (confirm('Activate this template?')) {
        window.location.href = 'id_templates.php?action=activate&id=' + id;
      }
    }

    function duplicateTemplate(id) {
      window.location.href = 'id_templates.php?action=duplicate&id=' + id;
    }

    function deleteTemplate(id) {
      if (confirm('Are you sure you want to delete this template?')) {
        window.location.href = 'id_templates.php?action=delete&id=' + id;
      }
    }

    // Update preview when inputs change
    document.getElementById('front_background').addEventListener('input', updateLivePreview);
    document.getElementById('back_background').addEventListener('input', updateLivePreview);
    document.getElementById('front_css').addEventListener('input', updateLivePreview);
    document.getElementById('back_css').addEventListener('input', updateLivePreview);

    // Initialize when modal opens
    document.getElementById('templateModal').addEventListener('show.bs.modal', function() {
      setTimeout(updateLivePreview, 100);
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>