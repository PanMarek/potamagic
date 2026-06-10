<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['role'] : 'guest';
$username = $is_logged_in ? $_SESSION['username'] : '';
$is_verified = $is_logged_in ? $_SESSION['is_verified'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POTA Activation Tracker</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <div class="container nav-container">
        <a href="index.php" class="logo">
            <i class="fa-solid fa-radio"></i> POTA<span>Tracker</span>
        </a>
        
        <button class="burger-menu" aria-label="Toggle Navigation">
            <span class="burger-bar"></span>
            <span class="burger-bar"></span>
            <span class="burger-bar"></span>
        </button>
        
        <ul class="nav-menu">
            <li><a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Home</a></li>
            
            <?php if ($is_logged_in): ?>
                <li><a href="add_activation.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'add_activation.php' ? 'active' : '' ?>"><i class="fa-solid fa-circle-plus"></i> Add Activation</a></li>
                <li><a href="equipment_profiles.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'equipment_profiles.php' ? 'active' : '' ?>"><i class="fa-solid fa-screwdriver-wrench"></i> Gear Profiles</a></li>
                
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="admin_users.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active' : '' ?>"><i class="fa-solid fa-users-gear"></i> Users</a></li>
                    <li><a href="admin_slider.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin_slider.php' ? 'active' : '' ?>"><i class="fa-solid fa-images"></i> Slider</a></li>
                    <li><a href="admin_metadata.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin_metadata.php' ? 'active' : '' ?>"><i class="fa-solid fa-tags"></i> Metadata</a></li>
                <?php endif; ?>
                
                <li class="nav-user-info"><span style="color: var(--text-secondary); margin-right: 5px;">73 de</span> <strong><?= htmlspecialchars($username) ?></strong></li>
                <li><a href="logout.php" class="nav-btn-secondary"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : '' ?>"><i class="fa-solid fa-right-to-bracket"></i> Login</a></li>
                <li><a href="register.php" class="nav-btn"><i class="fa-solid fa-user-plus"></i> Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

<div class="container main-content">
    <?php
    // If the user is logged in but unverified, show a warning banner
    if ($is_logged_in && !$is_verified) {
        echo '<div class="alert alert-info" style="margin-bottom: 30px;">';
        echo '<i class="fa-solid fa-envelope-circle-check"></i> ';
        echo 'Your account is registered but email verification is pending. Please check your inbox (or mail_log.txt in simulator mode). ';
        
        // Show simulated verification link if available in session
        if (isset($_SESSION['last_verification_link'])) {
            echo '<br><strong>Local Testing Link:</strong> <a href="' . $_SESSION['last_verification_link'] . '" style="color: inherit; text-decoration: underline;">Verify Account Now</a>';
        }
        echo '</div>';
    }
    
    // Global notification alerts helper
    if (isset($_SESSION['flash_success'])) {
        echo '<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> ' . $_SESSION['flash_success'] . '</div>';
        unset($_SESSION['flash_success']);
    }
    if (isset($_SESSION['flash_error'])) {
        echo '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> ' . $_SESSION['flash_error'] . '</div>';
        unset($_SESSION['flash_error']);
    }
    ?>
