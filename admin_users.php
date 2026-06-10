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

$current_admin_id = $_SESSION['user_id'];
$errors = [];
$search = trim($_GET['search'] ?? '');

// Handle Actions (Verify / Toggle Role / Delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $target_id = intval($_GET['id'] ?? 0);
    
    if ($target_id > 0) {
        if ($action === 'toggle_verify') {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 - is_verified WHERE id = ?");
                $stmt->execute([$target_id]);
                $_SESSION['flash_success'] = "Verification status updated.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to update verification status: " . $e->getMessage();
            }
        } elseif ($action === 'toggle_role') {
            if ($target_id == $current_admin_id) {
                $_SESSION['flash_error'] = "You cannot change your own administrator role.";
            } else {
                try {
                    // Get current role
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$target_id]);
                    $role = $stmt->fetchColumn();
                    $new_role = ($role === 'admin') ? 'registered' : 'admin';
                    
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $target_id]);
                    $_SESSION['flash_success'] = "User role changed to " . strtoupper($new_role) . ".";
                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = "Failed to change role: " . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            if ($target_id == $current_admin_id) {
                $_SESSION['flash_error'] = "You cannot delete your own administrator account.";
            } else {
                try {
                    // Delete user (cascade will delete activations/gear profiles)
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$target_id]);
                    $_SESSION['flash_success'] = "User account deleted successfully.";
                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = "Failed to delete user account: " . $e->getMessage();
                }
            }
        }
        header('Location: admin_users.php' . ($search !== '' ? '?search=' . urlencode($search) : ''));
        exit;
    }
}

// Fetch users
$query = "SELECT id, username, email, role, is_verified, created_at FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (username LIKE :search OR email LIKE :search)";
    $params['search'] = "%{$search}%";
}

$query .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $errors[] = "Failed to retrieve user list: " . $e->getMessage();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-users-gear" style="color: var(--accent-color);"></i> User Administration</h2>
        <p style="color: var(--text-secondary);">Manage system users, change roles, manually verify emails, or delete accounts.</p>
    </div>
</div>

<div class="segment" style="padding: 24px;">
    <!-- User Search -->
    <form action="admin_users.php" method="GET" class="inline-flex-form" style="max-width: 500px;">
        <input type="text" name="search" class="form-control" placeholder="Search by callsign/username or email..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($search !== ''): ?>
            <a href="admin_users.php" class="btn btn-secondary" title="Reset Search"><i class="fa-solid fa-rotate-left"></i></a>
        <?php endif; ?>
    </form>
    
    <?php if (empty($users)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 40px 0;">No users found matching the search criteria.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Callsign / Username</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role'] === 'admin' ? 'admin' : 'registered' ?>">
                                    <?= htmlspecialchars(strtoupper($user['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['is_verified'] ? 'verified' : 'unverified' ?>">
                                    <?= $user['is_verified'] ? 'VERIFIED' : 'UNVERIFIED' ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                            <td>
                                <!-- Verification Toggle -->
                                <a href="admin_users.php?action=toggle_verify&id=<?= $user['id'] ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
                                   class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; margin-right: 5px;" 
                                   title="<?= $user['is_verified'] ? 'Unverify Email' : 'Verify Email' ?>">
                                    <i class="fa-solid <?= $user['is_verified'] ? 'fa-envelope-open' : 'fa-envelope-circle-check' ?>"></i>
                                    <?= $user['is_verified'] ? 'Unverify' : 'Verify' ?>
                                </a>
                                
                                <!-- Role Toggle -->
                                <?php if ($user['id'] != $current_admin_id): ?>
                                    <a href="admin_users.php?action=toggle_role&id=<?= $user['id'] ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
                                       class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; margin-right: 5px;"
                                       title="Toggle User/Admin role">
                                        <i class="fa-solid fa-user-shield"></i> Role
                                    </a>
                                    
                                    <!-- Delete -->
                                    <a href="admin_users.php?action=delete&id=<?= $user['id'] ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
                                       class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;"
                                       onclick="return confirm('Are you sure you want to permanently delete the account de <?= htmlspecialchars($user['username']) ?>? This deletes all their activation logs and gear profiles.');">
                                        <i class="fa-solid fa-trash-can"></i> Delete
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">Self (Locked)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>