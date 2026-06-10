<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if guest
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "You must be logged in to manage equipment profiles.";
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success_msg = '';

$profile_name = '';
$transceiver = '';
$antenna = '';
$power_source = '';
$power_watts = '';
$additional_equipment = '';
$edit_id = null;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $profile_name = trim($_POST['profile_name'] ?? '');
        $transceiver = trim($_POST['transceiver'] ?? '');
        $antenna = trim($_POST['antenna'] ?? '');
        $power_source = trim($_POST['power_source'] ?? '');
        $power_watts = !empty($_POST['power_watts']) ? intval($_POST['power_watts']) : null;
        $additional_equipment = trim($_POST['additional_equipment'] ?? '');
        $edit_id = !empty($_POST['edit_id']) ? intval($_POST['edit_id']) : null;
        
        if (empty($profile_name)) {
            $errors[] = "Profile Name is required.";
        }
        
        if (empty($errors)) {
            if ($edit_id) {
                // Update existing profile (verify ownership)
                try {
                    $stmt = $pdo->prepare("UPDATE equipment_profiles SET profile_name = ?, transceiver = ?, antenna = ?, power_source = ?, power_watts = ?, additional_equipment = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$profile_name, $transceiver, $antenna, $power_source, $power_watts, $additional_equipment, $edit_id, $user_id]);
                    $_SESSION['flash_success'] = "Equipment profile updated successfully!";
                    header('Location: equipment_profiles.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Failed to update profile: " . $e->getMessage();
                }
            } else {
                // Add new profile
                try {
                    $stmt = $pdo->prepare("INSERT INTO equipment_profiles (user_id, profile_name, transceiver, antenna, power_source, power_watts, additional_equipment) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $profile_name, $transceiver, $antenna, $power_source, $power_watts, $additional_equipment]);
                    $_SESSION['flash_success'] = "Equipment profile created successfully!";
                    header('Location: equipment_profiles.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Failed to create profile: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle Delete/Edit triggers from GET requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $target_id = intval($_GET['id'] ?? 0);
    
    if ($action === 'delete' && $target_id > 0) {
        // Delete profile (verify ownership)
        try {
            $stmt = $pdo->prepare("DELETE FROM equipment_profiles WHERE id = ? AND user_id = ?");
            $stmt->execute([$target_id, $user_id]);
            $_SESSION['flash_success'] = "Equipment profile deleted.";
            header('Location: equipment_profiles.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Failed to delete profile: " . $e->getMessage();
        }
    } elseif ($action === 'edit' && $target_id > 0) {
        // Load details for editing
        $stmt = $pdo->prepare("SELECT * FROM equipment_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$target_id, $user_id]);
        $edit_profile = $stmt->fetch();
        
        if ($edit_profile) {
            $edit_id = $edit_profile['id'];
            $profile_name = $edit_profile['profile_name'];
            $transceiver = $edit_profile['transceiver'];
            $antenna = $edit_profile['antenna'];
            $power_source = $edit_profile['power_source'];
            $power_watts = $edit_profile['power_watts'];
            $additional_equipment = $edit_profile['additional_equipment'];
        }
    }
}

// Fetch all profiles of the user
$stmt = $pdo->prepare("SELECT * FROM equipment_profiles WHERE user_id = ? ORDER BY profile_name ASC");
$stmt->execute([$user_id]);
$profiles = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 30px;">
    <h2><i class="fa-solid fa-screwdriver-wrench" style="color: var(--accent-color);"></i> My Equipment Profiles</h2>
    <p style="color: var(--text-secondary);">Create templates of your typical gear setups (e.g. QRP backpack, mobile HF, home station). Select them when logging activations to autofill your forms instantly.</p>
</div>

<div class="detail-layout">
    <!-- List of Existing Profiles -->
    <div class="detail-main">
        <div class="segment" style="padding: 20px;">
            <h3 class="segment-title"><i class="fa-solid fa-list" style="color: var(--accent-color);"></i> Saved Profiles</h3>
            
            <?php if (empty($profiles)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 30px 0;">No equipment profiles saved yet. Use the form on the right to create your first gear profile!</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Profile Name</th>
                                <th>Transceiver</th>
                                <th>Antenna</th>
                                <th>Power (W)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profiles as $prof): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($prof['profile_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($prof['transceiver'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($prof['antenna'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($prof['power_watts'] ? $prof['power_watts'] . 'W' : 'N/A') ?></td>
                                    <td>
                                        <a href="equipment_profiles.php?action=edit&id=<?= $prof['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; margin-right: 5px;"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                        <a href="equipment_profiles.php?action=delete&id=<?= $prof['id'] ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;" onclick="return confirm('Are you sure you want to delete this profile?');"><i class="fa-solid fa-trash-can"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add / Edit Profile Form -->
    <div class="detail-sidebar">
        <div class="segment">
            <h3 class="segment-title">
                <i class="fa-solid <?= $edit_id ? 'fa-pen-to-square' : 'fa-circle-plus' ?>" style="color: var(--accent-color);"></i>
                <?= $edit_id ? 'Edit Profile' : 'Add New Profile' ?>
            </h3>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="padding: 10px; font-size: 0.85rem;">
                    <ul style="list-style: none;">
                        <?php foreach ($errors as $error): ?>
                            <li><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form action="equipment_profiles.php" method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="edit_id" value="<?= htmlspecialchars($edit_id) ?>">
                
                <div class="form-group">
                    <label for="profile_name" class="form-label">Profile Name</label>
                    <input type="text" name="profile_name" id="profile_name" class="form-control" placeholder="e.g. QRP SOTA Kit, Home Station" value="<?= htmlspecialchars($profile_name) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="transceiver" class="form-label">Transceiver (Radio)</label>
                    <input type="text" name="transceiver" id="transceiver" class="form-control" placeholder="e.g. Icom IC-705, Yaesu FT-891" value="<?= htmlspecialchars($transceiver) ?>">
                </div>
                
                <div class="form-group">
                    <label for="antenna" class="form-label">Antenna</label>
                    <input type="text" name="antenna" id="antenna" class="form-control" placeholder="e.g. End Fed Half Wave, 2m Whip" value="<?= htmlspecialchars($antenna) ?>">
                </div>
                
                <div class="form-group">
                    <label for="power_source" class="form-label">Power Source</label>
                    <input type="text" name="power_source" id="power_source" class="form-control" placeholder="e.g. LiFePO4 battery, USB-PD powerbank" value="<?= htmlspecialchars($power_source) ?>">
                </div>
                
                <div class="form-group">
                    <label for="power_watts" class="form-label">Power Output (Watts)</label>
                    <input type="number" name="power_watts" id="power_watts" class="form-control" placeholder="e.g. 5, 10, 100" value="<?= htmlspecialchars($power_watts) ?>">
                </div>
                
                <div class="form-group">
                    <label for="additional_equipment" class="form-label">Additional Accessories</label>
                    <textarea name="additional_equipment" id="additional_equipment" class="form-control" rows="3" placeholder="e.g. Keyer, Mast, Antenna tuner..."><?= htmlspecialchars($additional_equipment) ?></textarea>
                </div>
                
                <div class="form-actions" style="margin-top: 10px;">
                    <button type="submit" class="btn btn-primary btn-full"><?= $edit_id ? 'Update Profile' : 'Save Profile' ?></button>
                    <?php if ($edit_id): ?>
                        <a href="equipment_profiles.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>