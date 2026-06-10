<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Restrict to Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_error'] = "Access denied. Administrator privileges required.";
    header('Location: index.php');
    exit;
}

$errors = [];

// Handle Image Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $target_id = intval($_GET['id'] ?? 0);
    
    if ($target_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT image_path FROM slider_images WHERE id = ?");
            $stmt->execute([$target_id]);
            $path = $stmt->fetchColumn();
            
            if ($path) {
                $full_disk_path = __DIR__ . '/' . $path;
                if (file_exists($full_disk_path)) {
                    unlink($full_disk_path);
                }
                
                $del_stmt = $pdo->prepare("DELETE FROM slider_images WHERE id = ?");
                $del_stmt->execute([$target_id]);
                $_SESSION['flash_success'] = "Slider image deleted.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Failed to delete slider image: " . $e->getMessage();
        }
        header('Location: admin_slider.php');
        exit;
    }
}

// Handle Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slider_image'])) {
    $file = $_FILES['slider_image'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed with error code: " . $file['error'];
    } else {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ($file['size'] > $max_size) {
            $errors[] = "Image file size exceeds the 5MB limit.";
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG, and WebP are supported.";
        }
        
        if (empty($errors)) {
            $upload_dir = __DIR__ . '/uploads/slider/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('slide_', true) . '.' . $ext;
            $dest_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                $relative_path = 'uploads/slider/' . $new_filename;
                try {
                    $stmt = $pdo->prepare("INSERT INTO slider_images (image_path) VALUES (?)");
                    $stmt->execute([$relative_path]);
                    $_SESSION['flash_success'] = "Hero slider image uploaded successfully!";
                    header('Location: admin_slider.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Failed to save slider image record: " . $e->getMessage();
                }
            } else {
                $errors[] = "Failed to save uploaded file to the slider folder.";
            }
        }
    }
}

// Fetch all slider images
$images = $pdo->query("SELECT * FROM slider_images ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-images" style="color: var(--accent-color);"></i> Hero Slider Management</h2>
        <p style="color: var(--text-secondary);">Upload, preview, and delete images displayed in the homepage slider header.</p>
    </div>
</div>

<div class="detail-layout">
    <!-- List of Slider Images -->
    <div class="detail-main">
        <div class="segment">
            <h3 class="segment-title"><i class="fa-solid fa-layer-group" style="color: var(--accent-color);"></i> Active Slider Images</h3>
            
            <?php if (empty($images)): ?>
                <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                    <i class="fa-solid fa-photo-film fa-3x" style="margin-bottom: 15px;"></i>
                    <p>No custom slider images uploaded. The system is showing default placeholder images.</p>
                </div>
            <?php else: ?>
                <div class="slider-thumbnail-grid">
                    <?php foreach ($images as $img): ?>
                        <div class="slider-thumbnail-card" style="border: 1px solid var(--border-color);">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Slide preview">
                            <div class="slider-thumbnail-actions">
                                <a href="admin_slider.php?action=delete&id=<?= $img['id'] ?>" 
                                   class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;" 
                                   onclick="return confirm('Are you sure you want to delete this slider image? This will delete the file from the server.');">
                                    <i class="fa-solid fa-trash-can"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Upload Form -->
    <div class="detail-sidebar">
        <div class="segment">
            <h3 class="segment-title"><i class="fa-solid fa-cloud-arrow-up" style="color: var(--accent-color);"></i> Upload Image</h3>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="padding: 10px; font-size: 0.85rem;">
                    <ul style="list-style: none;">
                        <?php foreach ($errors as $error): ?>
                            <li><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form action="admin_slider.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group">
                    <label for="slider_image" class="form-label">Select Hero Image</label>
                    <input type="file" name="slider_image" id="slider_image" class="form-control" accept="image/jpeg, image/png, image/webp" style="padding: 8px;" required>
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">Landscape orientation recommended (e.g. 1920x600 pixels). Max size: 5MB.</small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-circle-up"></i> Upload Slider Image</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>