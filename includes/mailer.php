<?php
require_once __DIR__ . '/../config/smtp.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $username, $token) {
    // Determine the base URL dynamically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    // If running in a subdirectory, build the proper verification link
    $verifyUrl = "{$protocol}://{$host}{$uri}/verify.php?token={$token}";
    
    $subject = "Verify your POTA Activation Tracker Account";
    $body = "Hi {$username},\n\nThank you for registering at POTA Activation Tracker!\n\nPlease click the link below to verify your email address:\n{$verifyUrl}\n\n73,\nPOTA Tracker Team";
    
    if (SIMULATE_EMAIL) {
        // Log the email to mail_log.txt
        $logFile = __DIR__ . '/../mail_log.txt';
        $logContent = "========================================\n";
        $logContent .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "To: {$email}\n";
        $logContent .= "Subject: {$subject}\n";
        $logContent .= "Verification Link: {$verifyUrl}\n";
        $logContent .= "----------------------------------------\n";
        $logContent .= $body . "\n";
        $logContent .= "========================================\n\n";
        
        file_put_contents($logFile, $logContent, FILE_APPEND);
        
        // Save token to session so register.php can show a friendly banner
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['last_verification_link'] = $verifyUrl;
        return true;
    }
    
    // Otherwise, try to send using PHPMailer
    $exceptionFile = __DIR__ . '/../lib/PHPMailer/Exception.php';
    $phpmailerFile = __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
    $smtpFile = __DIR__ . '/../lib/PHPMailer/SMTP.php';
    
    if (!file_exists($exceptionFile) || !file_exists($phpmailerFile) || !file_exists($smtpFile)) {
        // Fallback: if PHPMailer files aren't physically present, log the error and default to simulation
        $logFile = __DIR__ . '/../mail_log.txt';
        $warning = "Warning: PHPMailer library files were not found. Falling back to Simulated Email logging.\n";
        file_put_contents($logFile, $warning, FILE_APPEND);
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['last_verification_link'] = $verifyUrl;
        return true;
    }
    
    require_once $exceptionFile;
    require_once $phpmailerFile;
    require_once $smtpFile;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE == 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log SMTP error to mail_log.txt
        $logFile = __DIR__ . '/../mail_log.txt';
        $logContent = "SMTP Error: " . $mail->ErrorInfo . "\n";
        file_put_contents($logFile, $logContent, FILE_APPEND);
        return false;
    }
}
?>
