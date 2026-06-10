<?php
// SMTP Mail Configuration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587); // 465 or 587
define('SMTP_USER', 'your-email@example.com');
define('SMTP_PASS', 'your-smtp-password');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_FROM', 'no-reply@pota.app');
define('SMTP_FROM_NAME', 'POTA Tracker');

// Set to true to bypass real SMTP sending and instead log verification links to `mail_log.txt`
// and display them in a screen notification banner for easy local testing.
define('SIMULATE_EMAIL', true);
?>
