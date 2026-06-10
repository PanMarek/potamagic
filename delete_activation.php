<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if guest
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "You must be logged in to delete activations.";
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$activation_id = intval($_GET['id'] ?? 0);

if ($activation_id <= 0) {
    $_SESSION['flash_error'] = "Invalid activation ID.";
    header('Location: index.php');
    exit;
}

try {
    // Fetch activation details
    $stmt = $pdo->prepare("SELECT user_id FROM activations WHERE id = ?");
    $stmt->execute([$activation_id]);
    $act = $stmt->fetch();
    
    if (!$act) {
        $_SESSION['flash_error'] = "Activation not found.";
        header('Location: index.php');
        exit;
    }
    
    // Verify permissions
    if ($act['user_id'] != $user_id && $user_role !== 'admin') {
        $_SESSION['flash_error'] = "You do not have permission to delete this activation.";
        header('Location: index.php');
        exit;
    }
    
    // Begin delete transaction
    $pdo->beginTransaction();
    
    // Fetch and delete associated images from the disk
    $img_stmt = $pdo->prepare("SELECT image_path FROM activation_images WHERE activation_id = ?");
    $img_stmt->execute([$activation_id]);
    $images = $img_stmt->fetchAll();
    
    foreach ($images as $img) {
        $full_disk_path = __DIR__ . '/' . $img['image_path'];
        if (file_exists($full_disk_path)) {
            unlink($full_disk_path);
        }
    }
    
    // Delete activation (cascades to activation_images in DB)
    $del_stmt = $pdo->prepare("DELETE FROM activations WHERE id = ?");
    $del_stmt->execute([$activation_id]);
    
    $pdo->commit();
    $_SESSION['flash_success'] = "Activation deleted successfully.";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = "Failed to delete activation: " . $e->getMessage();
}

header('Location: index.php');
exit;
?>
