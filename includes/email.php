<?php
/**
 * Email sending utilities
 * For local development, emails are logged to a file instead of sent
 * In production, replace with real email service (SendGrid, Mailgun, etc.)
 */

declare(strict_types=1);

/**
 * Send an email (mock for development, real implementation for production)
 */
function send_email(string $to, string $subject, string $body, string $htmlBody = ''): bool
{
    // In development (XAMPP), log to file instead of sending
    if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
        return log_email_locally($to, $subject, $body);
    }

    // In production, use real email service
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . e(APP_NAME) . " <noreply@nanny.app>\r\n";

    $emailBody = $htmlBody ?: nl2br(htmlspecialchars($body));

    return mail($to, $subject, $emailBody, $headers);
}

/**
 * Log email locally for development (XAMPP)
 */
function log_email_locally(string $to, string $subject, string $body): bool
{
    $logDir = __DIR__ . '/../storage/email_logs';
    
    // Create directory if it doesn't exist
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s');
    $separator = str_repeat('=', 80);

    $logEntry = "\n[$timestamp] TO: $to\nSUBJECT: $subject\n$separator\n$body\n$separator\n";

    return (bool) file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Send verification email with unique token
 */
function send_verification_email(int $userId, string $email, string $name): bool
{
    // Generate unique token
    $token = bin2hex(random_bytes(32));

    // Store token in database (prefer timestamped tokens for expiry checks).
    try {
        db()->prepare(
            'UPDATE users SET verification_token = ?, verification_sent_at = NOW() WHERE id = ?'
        )->execute([$token, $userId]);
    } catch (Throwable) {
        // Backward-compat for databases that do not yet have verification_sent_at.
        try {
            db()->prepare(
                'UPDATE users SET verification_token = ? WHERE id = ?'
            )->execute([$token, $userId]);
        } catch (Throwable) {
            return false;
        }
    }

    // Build verification link
    $verifyLink = url('auth/verify-email.php?token=' . $token);

    // Email body
    $subject = 'Verify Your ' . APP_NAME . ' Email';

    $htmlBody = "
    <html>
    <head><style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; }
        .header { background: linear-gradient(135deg, #1e5f9e 0%, #3b9be0 100%); color: white; padding: 20px; text-align: center; }
        .content { background: white; padding: 20px; }
        .button { display: inline-block; background: #1e5f9e; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
    </style></head>
    <body>
    <div class='container'>
        <div class='header'>
            <h1>" . e(APP_NAME) . "</h1>
        </div>
        <div class='content'>
            <p>Hi " . e($name) . ",</p>
            <p>Welcome to " . e(APP_NAME) . "! Please verify your email address to complete your registration.</p>
            <a href='" . $verifyLink . "' class='button'>Verify Email</a>
            <p>Or copy this link:</p>
            <p style='word-break: break-all; font-size: 12px; color: #666;'>" . $verifyLink . "</p>
            <p>This link expires in 24 hours.</p>
            <p>If you didn't create this account, please ignore this email.</p>
        </div>
        <div class='footer'>
            <p>&copy; " . date('Y') . " " . e(APP_NAME) . ". All rights reserved.</p>
        </div>
    </div>
    </body>
    </html>";

    $textBody = "
Welcome to " . APP_NAME . "!

Please verify your email address to complete your registration.

Verify Email: " . $verifyLink . "

This link expires in 24 hours.

If you didn't create this account, please ignore this email.

---
" . APP_NAME . " Team
";

    return send_email($email, $subject, $textBody, $htmlBody);
}

/**
 * Send password reset email with unique token
 */
function send_password_reset_email(int $userId, string $email, string $name): bool
{
    // Generate unique reset token
    $token = bin2hex(random_bytes(32));

    // Store token with 1-hour expiration
    $stmt = db()->prepare(
        'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
    );
    $stmt->execute([$userId, $token]);

    // Build reset link
    $resetLink = url('auth/reset.php?token=' . $token);

    // Email body
    $subject = 'Reset Your ' . APP_NAME . ' Password';

    $htmlBody = "
    <html>
    <head><style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; }
        .header { background: linear-gradient(135deg, #1e5f9e 0%, #3b9be0 100%); color: white; padding: 20px; text-align: center; }
        .content { background: white; padding: 20px; }
        .button { display: inline-block; background: #1e5f9e; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        .warning { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0; }
    </style></head>
    <body>
    <div class='container'>
        <div class='header'>
            <h1>" . e(APP_NAME) . "</h1>
        </div>
        <div class='content'>
            <p>Hi " . e($name) . ",</p>
            <p>We received a request to reset your password. Click the link below to set a new password.</p>
            <a href='" . $resetLink . "' class='button'>Reset Password</a>
            <p>Or copy this link:</p>
            <p style='word-break: break-all; font-size: 12px; color: #666;'>" . $resetLink . "</p>
            <div class='warning'>
                <strong>⚠️ Important:</strong> This link expires in 1 hour. If you didn't request a password reset, please ignore this email.
            </div>
        </div>
        <div class='footer'>
            <p>&copy; " . date('Y') . " " . e(APP_NAME) . ". All rights reserved.</p>
        </div>
    </div>
    </body>
    </html>";

    $textBody = "
We received a request to reset your password.

Reset Password: " . $resetLink . "

This link expires in 1 hour.

If you didn't request this, please ignore this email.

---
" . APP_NAME . " Team
";

    return send_email($email, $subject, $textBody, $htmlBody);
}
