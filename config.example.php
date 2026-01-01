<?php
// QuickPoll Configuration
// Copy this file to config.php and update with your database credentials

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quickpoll');

// Security Settings
define('MAX_POLLS_PER_IP_PER_HOUR', 5); // Max polls created per IP per hour
define('MAX_VOTES_PER_IP_PER_MINUTE', 10); // Max votes per IP per minute
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB max image size
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', 'uploads/');
define('RATE_LIMIT_BLOCK_DURATION', 3600); // 1 hour block duration

// Timezone
date_default_timezone_set('UTC');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database connection
function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                error_log("Database connection failed: " . $conn->connect_error);
                die("Database connection failed");
            }
            $conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed");
        }
    }
    return $conn;
}

// Get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Generate unique ID (shorter - 8 characters)
function generateUniqueID() {
    // Use base62 encoding for shorter URLs (a-z, A-Z, 0-9)
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $id = '';
    for ($i = 0; $i < 8; $i++) {
        $id .= $chars[random_int(0, 61)];
    }
    return $id;
}

// Secret key for signing view-only tokens (change this in production)
define('VIEW_ONLY_SECRET', 'QuickPoll_ViewOnly_Secret_2025_ChangeInProduction');

// Base64URL encoding (URL-safe base64)
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

// Generate a signed token for view-only access (shortened)
function generateViewOnlyToken($pollId) {
    $timestamp = time();
    $data = $pollId . '|' . $timestamp;
    // Use SHA256 but truncate to 16 chars (128 bits) for shorter token
    $signature = substr(hash_hmac('sha256', $data, VIEW_ONLY_SECRET), 0, 16);
    return base64url_encode($data . '|' . $signature);
}

// Verify a view-only token
function verifyViewOnlyToken($token, $pollId) {
    try {
        $decoded = base64url_decode($token);
        if ($decoded === false) {
            return false;
        }
        
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return false;
        }
        
        $tokenPollId = $parts[0];
        $timestamp = intval($parts[1]);
        $signature = $parts[2];
        
        // Verify poll ID matches
        if ($tokenPollId !== $pollId) {
            return false;
        }
        
        // Verify token hasn't expired (5 minutes validity)
        if (time() - $timestamp > 300) {
            return false;
        }
        
        // Verify signature (compare truncated version)
        $data = $tokenPollId . '|' . $timestamp;
        $expectedSignature = substr(hash_hmac('sha256', $data, VIEW_ONLY_SECRET), 0, 16);
        
        return hash_equals($expectedSignature, $signature);
    } catch (Exception $e) {
        return false;
    }
}

// Check if request is from homepage (view-only mode)
function isViewOnlyRequest($pollId) {
    // Check for valid signed token first
    if (isset($_GET['view_token'])) {
        $token = sanitizeInput($_GET['view_token']);
        if (verifyViewOnlyToken($token, $pollId)) {
            return true;
        }
    }
    
    // Check HTTP referrer as secondary check
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        // Check if referer is from index.html
        if (strpos($referer, 'index.html') !== false || strpos($referer, '/QuickPoll/') !== false && strpos($referer, '/poll/') === false) {
            return true;
        }
    }
    
    return false;
}

