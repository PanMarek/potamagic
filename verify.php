<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$token = trim($_GET['token'] ?? '');
$success = false;
$message = '';

if (empty($token)) {
    $message = "Invalid verification request. No token provided.";
} else {
    // Lookup user by token
    $stmt = $pdo->prepare("SELECT id, username, is_verified FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        $success = true;
        $message = "Thank you, <strong>" . htmlspecialchars($user['username']) . "</strong>! Your email address has been verified successfully. You can now manage your activations and gear profiles.";
        
        // If the verified user is currently logged in, update their session state
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']) {
            $_SESSION['is_verified'] = 1;
            // Clear verification link since it's verified
            unset($_SESSION['last_verification_link']);
        }
    } else {
        $message = "The verification link is invalid, expired, or has already been used.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card" style="text-align: center;">
    <?php if ($success): ?>
        <h2 style="color: var(--success); margin-bottom: 20px;"><i class="fa-solid fa-circle-check fa-2xl"></i></h2>
        <h3>Account Verified!</h3>
        <p style="color: var(--text-secondary); margin: 20px 0; font-size: 1.1rem;">
            <?= $message ?>
        </p>
        <a href="index.php" class="btn btn-primary" style="margin-top: 10px;">Go to Dashboard</a>
    <?php else: ?>
        <h2 style="color: var(--error); margin-bottom: 20px;"><i class="fa-solid fa-circle-xmark fa-2xl"></i></h2>
        <h3>Verification Failed</h3>
        <p style="color: var(--text-secondary); margin: 20px 0; font-size: 1.1rem;">
            <?= htmlspecialchars($message) ?>
        </p>
        <a href="index.php" class="btn btn-secondary" style="margin-top: 10px;">Back to Home</a>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
