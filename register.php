<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validations
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or Email is already registered.";
        } else {
            // Hash password and save
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_verified, verification_token) VALUES (?, ?, ?, 'registered', 0, ?)");
                $stmt->execute([$username, $email, $password_hash, $token]);
                
                // Send verification email
                if (sendVerificationEmail($email, $username, $token)) {
                    $_SESSION['flash_success'] = "Registration successful! A verification email has been sent. Please verify your account to unlock all features.";
                    
                    // Log the user in directly as unverified
                    $stmt = $pdo->prepare("SELECT id, role, is_verified FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['is_verified'] = $user['is_verified'];
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $errors[] = "Registration succeeded, but failed to send verification email.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h2 style="text-align: center; margin-bottom: 25px;"><i class="fa-solid fa-user-plus" style="color: var(--accent-color);"></i> Register Account</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="list-style: none;">
                <?php foreach ($errors as $error): ?>
                    <li><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form action="register.php" method="POST" class="form-grid">
        <div class="form-group form-group-full">
            <label for="username" class="form-label">Callsign / Username</label>
            <input type="text" name="username" id="username" class="form-control" placeholder="e.g. SP9AAA" value="<?= htmlspecialchars($username) ?>" required>
        </div>
        
        <div class="form-group form-group-full">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" name="email" id="email" class="form-control" placeholder="e.g. callsign@pota.app" value="<?= htmlspecialchars($email) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Min 6 characters" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
        
        <div class="form-group form-group-full" style="margin-top: 15px;">
            <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </div>
    </form>
    
    <p style="text-align: center; margin-top: 20px; color: var(--text-secondary); font-size: 0.9rem;">
        Already have an account? <a href="login.php">Login here</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
