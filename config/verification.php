<?php
/**
 * Email Verification Utilities
 * Handles email verification codes and confirmation for user registration
 */

require_once __DIR__ . '/database.php';

/**
 * Generate a unique verification code
 */
function generate_verification_code($user_id, $email) {
    global $conn;
    
    $code = bin2hex(random_bytes(16)); // 32-character hex string
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, email, code, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $user_id, $email, $code, $expires_at);
    
    if ($stmt->execute()) {
        $stmt->close();
        return $code;
    }
    $stmt->close();
    return false;
}

/**
 * Verify an email with the provided code
 */
function verify_email($code) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT user_id, email, expires_at FROM email_verifications WHERE code = ? AND verified_at IS NULL LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        return ['success' => false, 'message' => 'Invalid or expired verification code'];
    }
    
    if (strtotime($row['expires_at']) < time()) {
        return ['success' => false, 'message' => 'Verification code has expired'];
    }
    
    // Mark as verified
    $verify_stmt = $conn->prepare("UPDATE email_verifications SET verified_at = NOW() WHERE code = ?");
    $verify_stmt->bind_param('s', $code);
    $verify_stmt->execute();
    $verify_stmt->close();
    
    // Mark user as verified
    $user_stmt = $conn->prepare("UPDATE users SET email_verified = 1, verified_at = NOW() WHERE id = ?");
    $user_stmt->bind_param('i', $row['user_id']);
    $user_stmt->execute();
    $user_stmt->close();
    
    return ['success' => true, 'message' => 'Email verified successfully', 'user_id' => $row['user_id']];
}

/**
 * Send verification email (stub - implement with actual email service)
 */
function send_verification_email($email, $code) {
    // TODO: Implement email sending (e.g., using PHPMailer or similar)
    // For now, log the code to the verification log
    error_log("Verification code for $email: $code");
    return true;
}

/**
 * Check if user's email is verified
 */
function is_email_verified($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT email_verified FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row && $row['email_verified'] == 1;
}

/**
 * Delete expired verification codes (cleanup)
 */
function cleanup_expired_verifications() {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM email_verifications WHERE expires_at < NOW() AND verified_at IS NULL");
    $stmt->execute();
    $stmt->close();
}

?>
