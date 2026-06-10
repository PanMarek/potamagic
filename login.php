<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$login_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login_input) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Search by username or email
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$login_input, $login_input]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['is_verified'] = $user['is_verified'];
                
                // Clear verification link from session
                unset($_SESSION['last_verification_link']);
                
                header('Location: index.php');
                exit;
            } else {
                $error = "Invalid Callsign/Email or Password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h2 style="text-align: center; margin-bottom: 25px;"><i class="fa-solid fa-key" style="color: var(--accent-color);"></i> Member Login</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <form action="login.php" method="POST" class="form-grid">
        <div class="form-group form-group-full">
            <label for="login_input" class="form-label">Callsign / Username or Email</label>
            <input type="text" name="login_input" id="login_input" class="form-control" placeholder="e.g. SP9AAA or callsign@pota.app" value="<?= htmlspecialchars($login_input) ?>" required>
        </div>
        
        <div class="form-group form-group-full">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
        </div>
        
        <div class="form-group form-group-full" style="margin-top: 15px;">
            <button type="submit" class="btn btn-primary btn-full">Login</button>
        </div>
    </form>
    
    <p style="text-align: center; margin-top: 20px; color: var(--text-secondary); font-size: 0.9rem;">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
    
    <div style="background-color: var(--bg-tertiary); padding: 15px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-top: 25px; font-size: 0.85rem; color: var(--text-secondary);">
        <h4 style="margin-bottom: 5px; color: var(--text-primary);"><i class="fa-solid fa-circle-info" style="color: var(--accent-color);"></i> Demo Admin Account:</h4>
        <strong>Username:</strong> admin &nbsp;|&nbsp; <strong>Password:</strong> admin
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
