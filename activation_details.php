<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$activation_id = intval($_GET['id'] ?? 0);

if ($activation_id <= 0) {
    $_SESSION['flash_error'] = "Invalid activation ID.";
    header('Location: index.php');
    exit;
}

// Fetch activation joined with user
try {
    $stmt = $pdo->prepare("SELECT a.*, u.username, u.email 
                          FROM activations a 
                          JOIN users u ON a.user_id = u.id 
                          WHERE a.id = ?");
    $stmt->execute([$activation_id]);
    $act = $stmt->fetch();
} catch (PDOException $e) {
    $act = null;
}

if (!$act) {
    $_SESSION['flash_error'] = "Activation not found.";
    header('Location: index.php');
    exit;
}

// Fetch all photos for this activation
$stmt = $pdo->prepare("SELECT * FROM activation_images WHERE activation_id = ?");
$stmt->execute([$activation_id]);
$images = $stmt->fetchAll();

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$user_role = $is_logged_in ? $_SESSION['role'] : 'guest';

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
    <div>
        <span class="badge badge-registered" style="margin-bottom: 10px;"><?= htmlspecialchars($act['park_reference']) ?></span>
        <h2><?= htmlspecialchars($act['park_name']) ?></h2>
        <p style="color: var(--text-secondary);"><i class="fa-solid fa-calendar"></i> Activated on <?= date('F d, Y', strtotime($act['activation_date'])) ?> by de <strong><?= htmlspecialchars($act['username']) ?></strong></p>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
        <?php if ($is_logged_in && ($act['user_id'] == $user_id || $user_role === 'admin')): ?>
            <a href="edit_activation.php?id=<?= $act['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
            <a href="delete_activation.php?id=<?= $act['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this activation log permanently? This will remove all uploaded images from the server.');"><i class="fa-solid fa-trash-can"></i> Delete</a>
        <?php endif; ?>
    </div>
</div>

<div class="detail-layout">
    <!-- Main Column: Photos, Directions, and Localization Notes -->
    <div class="detail-main">
        <!-- Photo Gallery -->
        <div class="gallery-container">
            <?php if (empty($images)): ?>
                <div class="gallery-main-img" style="display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-image fa-4x" style="color: var(--text-muted);"></i>
                </div>
            <?php else: ?>
                <div class="gallery-main-img">
                    <img id="main-gallery-image" src="<?= htmlspecialchars($images[0]['image_path']) ?>" alt="Activation Photo">
                </div>
                
                <?php if (count($images) > 1): ?>
                    <div class="gallery-thumbs">
                        <?php foreach ($images as $index => $img): ?>
                            <div class="gallery-thumb <?= $index === 0 ? 'active' : '' ?>" data-large-src="<?= htmlspecialchars($img['image_path']) ?>">
                                <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Thumb <?= $index + 1 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Localization and Access Notes -->
        <div class="segment">
            <h3 class="segment-title"><i class="fa-solid fa-map-location-dot" style="color: var(--accent-color);"></i> Localization & Access Details</h3>
            
            <div class="card-details" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom: 25px; background-color: var(--bg-tertiary);">
                <div class="detail-row">
                    <span>Parking Coordinates</span>
                    <span><?= htmlspecialchars($act['parking_coords'] ?: 'Not specified') ?></span>
                </div>
                <div class="detail-row">
                    <span>Parking Conditions</span>
                    <span><?= htmlspecialchars($act['parking_conditions'] ?: 'Not specified') ?></span>
                </div>
                <div class="detail-row">
                    <span>Cellular Coverage</span>
                    <span><?= htmlspecialchars($act['cell_coverage'] ?: 'Not specified') ?></span>
                </div>
            </div>
            
            <?php if (!empty($act['latitude']) && !empty($act['longitude'])): ?>
                <div class="card-details" style="grid-template-columns: 1fr 1fr; margin-bottom: 25px; background-color: var(--bg-tertiary);">
                    <div class="detail-row">
                        <span>Park Latitude</span>
                        <span><?= number_format($act['latitude'], 6) ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Park Longitude</span>
                        <span><?= number_format($act['longitude'], 6) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <h4 style="margin-top: 15px; margin-bottom: 10px; color: #ffffff;">Directions & Site Notes</h4>
            <div style="color: var(--text-secondary); background-color: rgba(0,0,0,0.15); padding: 15px; border-radius: var(--radius-md); line-height: 1.7; white-space: pre-wrap; border: 1px solid var(--border-color);"><?= htmlspecialchars($act['localization_notes'] ?: 'No additional notes provided for this site.') ?></div>
        </div>
        
        <!-- Additional technical or general equipment notes -->
        <?php if (!empty($act['additional_equipment'])): ?>
            <div class="segment">
                <h3 class="segment-title"><i class="fa-solid fa-circle-nodes" style="color: var(--accent-color);"></i> Additional Gear & Accessories</h3>
                <div style="color: var(--text-secondary); background-color: rgba(0,0,0,0.15); padding: 15px; border-radius: var(--radius-md); line-height: 1.7; white-space: pre-wrap; border: 1px solid var(--border-color);"><?= htmlspecialchars($act['additional_equipment']) ?></div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar: Stats, Rig, Bands, Modes -->
    <div class="detail-sidebar">
        <!-- POTA Reference and API details -->
        <div class="segment" style="border-left: 4px solid var(--accent-color);">
            <h3 style="font-size: 1.15rem; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <span>POTA Program Details</span>
                <i class="fa-solid fa-earth-americas" style="color: var(--accent-color);"></i>
            </h3>
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 0.95rem;">
                <p><span style="color: var(--text-secondary);">Reference:</span> <strong><?= htmlspecialchars($act['park_reference']) ?></strong></p>
                <p><span style="color: var(--text-secondary);">Park Name:</span> <strong><?= htmlspecialchars($act['park_name']) ?></strong></p>
                <a href="https://pota.app/#/park/<?= htmlspecialchars($act['park_reference']) ?>" target="_blank" class="btn btn-secondary" style="font-size: 0.8rem; margin-top: 10px; width: 100%; text-align: center; justify-content: center; gap: 5px;"><i class="fa-solid fa-arrow-up-right-from-square"></i> View on POTA.app</a>
            </div>
        </div>
        
        <!-- Operating Stats -->
        <div class="segment">
            <h3 class="segment-title"><i class="fa-solid fa-tower-cell" style="color: var(--accent-color);"></i> Log Summary</h3>
            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem;">
                <p style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Total QSOs:</span>
                    <strong style="color: var(--accent-color); font-size: 1.1rem;"><?= $act['qso_count'] ?></strong>
                </p>
                <p style="display: flex; flex-direction: column; gap: 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Bands Used:</span>
                    <strong style="color: #ffffff;"><?= htmlspecialchars($act['bands']) ?></strong>
                </p>
                <p style="display: flex; flex-direction: column; gap: 4px; padding-bottom: 4px;">
                    <span style="color: var(--text-secondary);">Modes Used:</span>
                    <strong style="color: #ffffff;"><?= htmlspecialchars($act['modes']) ?></strong>
                </p>
            </div>
        </div>
        
        <!-- Radio Rig & Station Gear -->
        <div class="segment">
            <h3 class="segment-title"><i class="fa-solid fa-radio" style="color: var(--accent-color);"></i> Station Gear</h3>
            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem;">
                <p style="display: flex; flex-direction: column; gap: 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Transceiver:</span>
                    <strong><?= htmlspecialchars($act['transceiver'] ?: 'N/A') ?></strong>
                </p>
                <p style="display: flex; flex-direction: column; gap: 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Antenna:</span>
                    <strong><?= htmlspecialchars($act['antenna'] ?: 'N/A') ?></strong>
                </p>
                <p style="display: flex; flex-direction: column; gap: 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Power Source:</span>
                    <strong><?= htmlspecialchars($act['power_source'] ?: 'N/A') ?></strong>
                </p>
                <p style="display: flex; justify-content: space-between; padding-bottom: 4px;">
                    <span style="color: var(--text-secondary);">RF Power Output:</span>
                    <strong><?= $act['power_watts'] ? $act['power_watts'] . ' Watts' : 'N/A' ?></strong>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
