<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if guest
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "You must be logged in to edit activations.";
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$activation_id = intval($_GET['id'] ?? 0);
$errors = [];

// Fetch activation details
$stmt = $pdo->prepare("SELECT * FROM activations WHERE id = ?");
$stmt->execute([$activation_id]);
$act = $stmt->fetch();

if (!$act) {
    $_SESSION['flash_error'] = "Activation not found.";
    header('Location: index.php');
    exit;
}

// Verify ownership or admin role
if ($act['user_id'] != $user_id && $user_role !== 'admin') {
    $_SESSION['flash_error'] = "You do not have permission to edit this activation.";
    header('Location: index.php');
    exit;
}

// Fetch equipment profiles of the user for the dropdown
$stmt = $pdo->prepare("SELECT * FROM equipment_profiles WHERE user_id = ? ORDER BY profile_name ASC");
$stmt->execute([$user_id]);
$profiles = $stmt->fetchAll();

// Fetch dynamic bands and modes from database
$bands_list = $pdo->query("SELECT * FROM bands ORDER BY id ASC")->fetchAll();
$modes_list = $pdo->query("SELECT * FROM modes ORDER BY id ASC")->fetchAll();

// Fetch current images for this activation
$stmt = $pdo->prepare("SELECT * FROM activation_images WHERE activation_id = ?");
$stmt->execute([$activation_id]);
$current_images = $stmt->fetchAll();

// Parse existing comma-separated bands/modes into arrays
$act_bands = array_map('trim', explode(',', $act['bands'] ?? ''));
$act_modes = array_map('trim', explode(',', $act['modes'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activation_date = $_POST['activation_date'] ?? '';
    $park_reference = strtoupper(trim($_POST['park_reference'] ?? ''));
    $park_name = trim($_POST['park_name'] ?? '');
    $qso_count = !empty($_POST['qso_count']) ? intval($_POST['qso_count']) : 0;
    
    // Checked bands and modes arrays
    $checked_bands = $_POST['bands'] ?? [];
    $checked_modes = $_POST['modes'] ?? [];
    
    $transceiver = trim($_POST['transceiver'] ?? '');
    $antenna = trim($_POST['antenna'] ?? '');
    $power_source = trim($_POST['power_source'] ?? '');
    $power_watts = !empty($_POST['power_watts']) ? intval($_POST['power_watts']) : null;
    $additional_equipment = trim($_POST['additional_equipment'] ?? '');
    
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $parking_coords = trim($_POST['parking_coords'] ?? '');
    $parking_conditions = trim($_POST['parking_conditions'] ?? '');
    $cell_coverage = trim($_POST['cell_coverage'] ?? '');
    $localization_notes = trim($_POST['localization_notes'] ?? '');
    
    $delete_image_ids = $_POST['delete_images'] ?? []; // IDs of photos to delete
    
    // Validation
    if (empty($activation_date)) {
        $errors[] = "Activation Date is required.";
    }
    if (empty($park_reference)) {
        $errors[] = "Park Reference is required.";
    }
    if (empty($park_name)) {
        $errors[] = "Park Name is required.";
    }
    if (empty($checked_bands)) {
        $errors[] = "At least one Band must be selected.";
    }
    if (empty($checked_modes)) {
        $errors[] = "At least one Mode must be selected.";
    }
    
    // Count how many photos will remain after deletions
    $remaining_photos_count = count($current_images) - count($delete_image_ids);
    
    // Check new uploads
    $new_file_count = 0;
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $new_file_count = count($_FILES['images']['name']);
    }
    
    if (($remaining_photos_count + $new_file_count) > 5) {
        $errors[] = "An activation can have a maximum of 5 images. Please delete existing ones first.";
    } else if ($new_file_count > 0) {
        // Validate each file
        $files = $_FILES['images'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        for ($i = 0; $i < $new_file_count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Failed uploading file: " . htmlspecialchars($files['name'][$i]);
                continue;
            }
            if ($files['size'][$i] > $max_size) {
                $errors[] = "File size exceeds 5MB: " . htmlspecialchars($files['name'][$i]);
            }
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($files['tmp_name'][$i]);
            if (!in_array($mime, $allowed_types)) {
                $errors[] = "Unsupported file format: " . htmlspecialchars($files['name'][$i]);
            }
        }
    }
    
    if (empty($errors)) {
        $bands_str = implode(', ', $checked_bands);
        $modes_str = implode(', ', $checked_modes);
        
        try {
            $pdo->beginTransaction();
            
            // 1. Update activation
            $stmt = $pdo->prepare("UPDATE activations SET 
                activation_date = ?, park_reference = ?, park_name = ?, qso_count = ?, bands = ?, modes = ?,
                transceiver = ?, antenna = ?, power_source = ?, power_watts = ?, additional_equipment = ?,
                latitude = ?, longitude = ?, parking_coords = ?, parking_conditions = ?, cell_coverage = ?, localization_notes = ?
                WHERE id = ?");
            
            $stmt->execute([
                $activation_date, $park_reference, $park_name, $qso_count, $bands_str, $modes_str,
                $transceiver, $antenna, $power_source, $power_watts, $additional_equipment,
                $latitude, $longitude, $parking_coords, $parking_conditions, $cell_coverage, $localization_notes,
                $activation_id
            ]);
            
            // 2. Handle image deletions
            if (!empty($delete_image_ids)) {
                foreach ($delete_image_ids as $del_img_id) {
                    // Fetch path to delete file from disk
                    $img_info_stmt = $pdo->prepare("SELECT image_path FROM activation_images WHERE id = ? AND activation_id = ?");
                    $img_info_stmt->execute([$del_img_id, $activation_id]);
                    $img_row = $img_info_stmt->fetch();
                    
                    if ($img_row) {
                        $full_disk_path = __DIR__ . '/' . $img_row['image_path'];
                        if (file_exists($full_disk_path)) {
                            unlink($full_disk_path);
                        }
                        // Delete row
                        $del_stmt = $pdo->prepare("DELETE FROM activation_images WHERE id = ?");
                        $del_stmt->execute([$del_img_id]);
                    }
                }
            }
            
            // 3. Process new image uploads
            if ($new_file_count > 0) {
                $upload_dir = __DIR__ . '/uploads/activations/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $files = $_FILES['images'];
                for ($i = 0; $i < $new_file_count; $i++) {
                    $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $new_filename = uniqid('img_', true) . '.' . $ext;
                    $dest_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $dest_path)) {
                        $relative_path = 'uploads/activations/' . $new_filename;
                        $img_stmt = $pdo->prepare("INSERT INTO activation_images (activation_id, image_path) VALUES (?, ?)");
                        $img_stmt->execute([$activation_id, $relative_path]);
                    } else {
                        throw new Exception("Error saving file: " . $files['name'][$i]);
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['flash_success'] = "Activation updated successfully!";
            header('Location: activation_details.php?id=' . $activation_id);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to update activation: " . $e->getMessage();
        }
    }
}

// Prepare arrays for initial form display in case of errors
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act_bands = $_POST['bands'] ?? [];
    $act_modes = $_POST['modes'] ?? [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card" style="max-width: 800px;">
    <h2><i class="fa-solid fa-pen-to-square" style="color: var(--accent-color);"></i> Edit Activation Details</h2>
    <p style="color: var(--text-secondary); margin-bottom: 25px;">Modify your activation records. Fields marked * are required.</p>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="list-style: none;">
                <?php foreach ($errors as $error): ?>
                    <li><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form action="edit_activation.php?id=<?= $activation_id ?>" method="POST" enctype="multipart/form-data" class="form-grid">
        <!-- Part 1: Core Details -->
        <h3 class="form-group-full" style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-top: 10px;"><i class="fa-solid fa-circle-info" style="color: var(--accent-color); font-size: 1.1rem;"></i> General Details</h3>
        
        <div class="form-group">
            <label for="activation_date" class="form-label">Activation Date *</label>
            <input type="date" name="activation_date" id="activation_date" class="form-control" value="<?= htmlspecialchars($_POST['activation_date'] ?? $act['activation_date']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="park_reference" class="form-label">Park ID / Reference *</label>
            <input type="text" name="park_reference" id="park_reference" class="form-control" placeholder="e.g. US-0001" value="<?= htmlspecialchars($_POST['park_reference'] ?? $act['park_reference']) ?>" required>
            <small id="lookup-status" class="form-text" style="font-size: 0.8rem; margin-top: 4px; display: block; font-weight: 500;"></small>
        </div>
        
        <div class="form-group form-group-full">
            <label for="park_name" class="form-label">Park Name *</label>
            <input type="text" name="park_name" id="park_name" class="form-control" value="<?= htmlspecialchars($_POST['park_name'] ?? $act['park_name']) ?>" required>
        </div>
        
        <!-- Part 2: QSO Summary -->
        <h3 class="form-group-full" style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-top: 20px;"><i class="fa-solid fa-radio" style="color: var(--accent-color); font-size: 1.1rem;"></i> QSO Summary</h3>
        
        <div class="form-group">
            <label for="qso_count" class="form-label">Total QSOs Count</label>
            <input type="number" name="qso_count" id="qso_count" class="form-control" min="0" value="<?= htmlspecialchars($_POST['qso_count'] ?? $act['qso_count']) ?>">
        </div>
        
        <div class="form-group" style="visibility: hidden; display: none;"></div>
        
        <div class="form-group form-group-full">
            <label class="form-label">Bands Used *</label>
            <div class="checkbox-group">
                <?php foreach ($bands_list as $band): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="bands[]" value="<?= htmlspecialchars($band['name']) ?>" <?= in_array($band['name'], $act_bands) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($band['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-group form-group-full">
            <label class="form-label">Modes Used *</label>
            <div class="checkbox-group">
                <?php foreach ($modes_list as $mode): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="modes[]" value="<?= htmlspecialchars($mode['name']) ?>" <?= in_array($mode['name'], $act_modes) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($mode['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Part 3: Equipment -->
        <h3 class="form-group-full" style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-top: 20px;"><i class="fa-solid fa-screwdriver-wrench" style="color: var(--accent-color); font-size: 1.1rem;"></i> Station Equipment</h3>
        
        <div class="form-group form-group-full">
            <label for="equipment_profile_id" class="form-label">Load Gear Profile Template (Overrides Current Values)</label>
            <select name="equipment_profile_id" id="equipment_profile_id" class="form-control">
                <option value="">-- Select Saved Profile --</option>
                <?php foreach ($profiles as $prof): ?>
                    <option value="<?= $prof['id'] ?>"
                        data-transceiver="<?= htmlspecialchars($prof['transceiver'] ?? '') ?>"
                        data-antenna="<?= htmlspecialchars($prof['antenna'] ?? '') ?>"
                        data-power-source="<?= htmlspecialchars($prof['power_source'] ?? '') ?>"
                        data-power-watts="<?= htmlspecialchars($prof['power_watts'] ?? '') ?>"
                        data-additional-equipment="<?= htmlspecialchars($prof['additional_equipment'] ?? '') ?>">
                        <?= htmlspecialchars($prof['profile_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="transceiver" class="form-label">Transceiver (Radio)</label>
            <input type="text" name="transceiver" id="transceiver" class="form-control" value="<?= htmlspecialchars($_POST['transceiver'] ?? $act['transceiver']) ?>">
        </div>
        
        <div class="form-group">
            <label for="antenna" class="form-label">Antenna</label>
            <input type="text" name="antenna" id="antenna" class="form-control" value="<?= htmlspecialchars($_POST['antenna'] ?? $act['antenna']) ?>">
        </div>
        
        <div class="form-group">
            <label for="power_source" class="form-label">Power Source</label>
            <input type="text" name="power_source" id="power_source" class="form-control" value="<?= htmlspecialchars($_POST['power_source'] ?? $act['power_source']) ?>">
        </div>
        
        <div class="form-group">
            <label for="power_watts" class="form-label">Power Output (Watts)</label>
            <input type="number" name="power_watts" id="power_watts" class="form-control" value="<?= htmlspecialchars($_POST['power_watts'] ?? $act['power_watts']) ?>">
        </div>
        
        <div class="form-group form-group-full">
            <label for="additional_equipment" class="form-label">Additional Equipment Notes</label>
            <textarea name="additional_equipment" id="additional_equipment" class="form-control" rows="3"><?= htmlspecialchars($_POST['additional_equipment'] ?? $act['additional_equipment']) ?></textarea>
        </div>
        
        <!-- Part 4: Localization -->
        <h3 class="form-group-full" style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-top: 20px;"><i class="fa-solid fa-location-dot" style="color: var(--accent-color); font-size: 1.1rem;"></i> Localization & Accessibility</h3>
        
        <div class="form-group">
            <label for="latitude" class="form-label">Latitude</label>
            <input type="number" step="any" name="latitude" id="latitude" class="form-control" value="<?= htmlspecialchars($_POST['latitude'] ?? $act['latitude']) ?>">
        </div>
        
        <div class="form-group">
            <label for="longitude" class="form-label">Longitude</label>
            <input type="number" step="any" name="longitude" id="longitude" class="form-control" value="<?= htmlspecialchars($_POST['longitude'] ?? $act['longitude']) ?>">
        </div>
        
        <div class="form-group">
            <label for="parking_coords" class="form-label">Parking Spot Coordinates</label>
            <input type="text" name="parking_coords" id="parking_coords" class="form-control" value="<?= htmlspecialchars($_POST['parking_coords'] ?? $act['parking_coords']) ?>">
        </div>
        
        <div class="form-group">
            <label for="parking_conditions" class="form-label">Parking Conditions</label>
            <input type="text" name="parking_conditions" id="parking_conditions" class="form-control" value="<?= htmlspecialchars($_POST['parking_conditions'] ?? $act['parking_conditions']) ?>">
        </div>
        
        <div class="form-group form-group-full">
            <label for="cell_coverage" class="form-label">Cellular Coverage Status</label>
            <input type="text" name="cell_coverage" id="cell_coverage" class="form-control" value="<?= htmlspecialchars($_POST['cell_coverage'] ?? $act['cell_coverage']) ?>">
        </div>
        
        <div class="form-group form-group-full">
            <label for="localization_notes" class="form-label">Directions & Access Notes (Useful for other activators)</label>
            <textarea name="localization_notes" id="localization_notes" class="form-control" rows="4"><?= htmlspecialchars($_POST['localization_notes'] ?? $act['localization_notes']) ?></textarea>
        </div>
        
        <!-- Part 5: Photos -->
        <h3 class="form-group-full" style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-top: 20px;"><i class="fa-solid fa-camera" style="color: var(--accent-color); font-size: 1.1rem;"></i> Activation Images</h3>
        
        <?php if (!empty($current_images)): ?>
            <div class="form-group form-group-full">
                <label class="form-label">Current Photos (Check to delete)</label>
                <div class="slider-thumbnail-grid">
                    <?php foreach ($current_images as $img): ?>
                        <div class="slider-thumbnail-card">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Activation pic">
                            <div class="slider-thumbnail-actions">
                                <label class="checkbox-label" style="font-size: 0.85rem; color: #f87171;">
                                    <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>">
                                    <span>Delete</span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="form-group form-group-full">
            <label for="images" class="form-label">Upload Additional Pictures (Max 5 total pictures allowed)</label>
            <input type="file" name="images[]" id="images" class="form-control" multiple accept="image/jpeg, image/png, image/webp" style="padding: 8px;">
            <small style="color: var(--text-muted); margin-top: 5px; display: block;">Supported formats: JPEG/JPG, PNG, WebP.</small>
        </div>
        
        <div class="form-group form-group-full" style="display: flex; gap: 15px; margin-top: 25px;">
            <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-square-check"></i> Save Changes</button>
            <a href="activation_details.php?id=<?= $activation_id ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
