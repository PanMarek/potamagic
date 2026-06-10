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

// Handle Metadata Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_band') {
        $band_name = trim($_POST['band_name'] ?? '');
        if (empty($band_name)) {
            $errors[] = "Band name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO bands (name) VALUES (?)");
                $stmt->execute([$band_name]);
                $_SESSION['flash_success'] = "Band '{$band_name}' added successfully.";
                header('Location: admin_metadata.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate key)
                    $errors[] = "Band '{$band_name}' already exists.";
                } else {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'add_mode') {
        $mode_name = trim($_POST['mode_name'] ?? '');
        if (empty($mode_name)) {
            $errors[] = "Mode name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO modes (name) VALUES (?)");
                $stmt->execute([$mode_name]);
                $_SESSION['flash_success'] = "Mode '{$mode_name}' added successfully.";
                header('Location: admin_metadata.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errors[] = "Mode '{$mode_name}' already exists.";
                } else {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle Metadata Deletion
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $target_id = intval($_GET['id'] ?? 0);
    
    if ($target_id > 0) {
        if ($action === 'delete_band') {
            try {
                $stmt = $pdo->prepare("DELETE FROM bands WHERE id = ?");
                $stmt->execute([$target_id]);
                $_SESSION['flash_success'] = "Band deleted successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to delete band: " . $e->getMessage();
            }
        } elseif ($action === 'delete_mode') {
            try {
                $stmt = $pdo->prepare("DELETE FROM modes WHERE id = ?");
                $stmt->execute([$target_id]);
                $_SESSION['flash_success'] = "Mode deleted successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to delete mode: " . $e->getMessage();
            }
        }
        header('Location: admin_metadata.php');
        exit;
    }
}

// Fetch lists
$bands = $pdo->query("SELECT * FROM bands ORDER BY id ASC")->fetchAll();
$modes = $pdo->query("SELECT * FROM modes ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-tags" style="color: var(--accent-color);"></i> POTA Metadata Administration</h2>
        <p style="color: var(--text-secondary);">Manage the system-wide operating Bands and Modes. Activators will choose from these values when logging activations.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 30px;">
        <ul style="list-style: none;">
            <?php foreach ($errors as $error): ?>
                <li><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- Bands Administration Segment -->
    <div class="segment">
        <h3 class="segment-title"><i class="fa-solid fa-wave-square" style="color: var(--accent-color);"></i> Operating Bands</h3>
        
        <form action="admin_metadata.php" method="POST" class="inline-flex-form">
            <input type="hidden" name="action" value="add_band">
            <input type="text" name="band_name" class="form-control" placeholder="e.g. 17m, 1.25m" required>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add</button>
        </form>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Band Name</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bands as $band): ?>
                        <tr>
                            <td><?= $band['id'] ?></td>
                            <td><strong><?= htmlspecialchars($band['name']) ?></strong></td>
                            <td style="text-align: right;">
                                <a href="admin_metadata.php?action=delete_band&id=<?= $band['id'] ?>" 
                                   class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem;"
                                   onclick="return confirm('Are you sure you want to delete the band <?= htmlspecialchars($band['name']) ?>?');">
                                    <i class="fa-solid fa-trash-can"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modes Administration Segment -->
    <div class="segment">
        <h3 class="segment-title"><i class="fa-solid fa-radio" style="color: var(--accent-color);"></i> Operating Modes</h3>
        
        <form action="admin_metadata.php" method="POST" class="inline-flex-form">
            <input type="hidden" name="action" value="add_mode">
            <input type="text" name="mode_name" class="form-control" placeholder="e.g. RTTY, PSK31" required>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add</button>
        </form>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mode Name</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modes as $mode): ?>
                        <tr>
                            <td><?= $mode['id'] ?></td>
                            <td><strong><?= htmlspecialchars($mode['name']) ?></strong></td>
                            <td style="text-align: right;">
                                <a href="admin_metadata.php?action=delete_mode&id=<?= $mode['id'] ?>" 
                                   class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem;"
                                   onclick="return confirm('Are you sure you want to delete the mode <?= htmlspecialchars($mode['name']) ?>?');">
                                    <i class="fa-solid fa-trash-can"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>