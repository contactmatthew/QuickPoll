<?php
require_once 'config.php';

class Security {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    // Rate limiting check
    public function checkRateLimit($actionType, $maxAttempts, $timeWindow = 3600) {
        $ip = getClientIP();
        $now = date('Y-m-d H:i:s');
        
        // Check if IP is blocked
        $stmt = $this->db->prepare("SELECT blocked_until FROM rate_limits WHERE ip_address = ? AND action_type = ? AND blocked_until > ?");
        $stmt->bind_param("sss", $ip, $actionType, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['allowed' => false, 'message' => 'Too many requests. Please try again later.'];
        }
        
        // Check attempt count
        $timeLimit = date('Y-m-d H:i:s', time() - $timeWindow);
        $stmt = $this->db->prepare("SELECT attempt_count, last_attempt FROM rate_limits WHERE ip_address = ? AND action_type = ? AND last_attempt > ?");
        $stmt->bind_param("sss", $ip, $actionType, $timeLimit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['attempt_count'] >= $maxAttempts) {
                // Block the IP
                $blockUntil = date('Y-m-d H:i:s', time() + RATE_LIMIT_BLOCK_DURATION);
                $stmt = $this->db->prepare("UPDATE rate_limits SET blocked_until = ? WHERE ip_address = ? AND action_type = ?");
                $stmt->bind_param("sss", $blockUntil, $ip, $actionType);
                $stmt->execute();
                return ['allowed' => false, 'message' => 'Rate limit exceeded. You are temporarily blocked.'];
            }
            
            // Increment attempt count
            $newCount = $row['attempt_count'] + 1;
            $stmt = $this->db->prepare("UPDATE rate_limits SET attempt_count = ?, last_attempt = ? WHERE ip_address = ? AND action_type = ?");
            $stmt->bind_param("isss", $newCount, $now, $ip, $actionType);
            $stmt->execute();
        } else {
            // Create new record
            $stmt = $this->db->prepare("INSERT INTO rate_limits (ip_address, action_type, attempt_count, last_attempt) VALUES (?, ?, 1, ?)");
            $stmt->bind_param("sss", $ip, $actionType, $now);
            $stmt->execute();
        }
        
        return ['allowed' => true];
    }
    
    // Validate image upload
    public function validateImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'No file uploaded'];
        }
        
        if ($file['size'] > MAX_IMAGE_SIZE) {
            return ['valid' => false, 'message' => 'File size exceeds 5MB limit'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            return ['valid' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed'];
        }
        
        // Additional security: verify it's actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'message' => 'File is not a valid image'];
        }
        
        return ['valid' => true, 'mimeType' => $mimeType];
    }
    
    // Sanitize filename
    public function sanitizeFilename($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return time() . '_' . $filename;
    }
}

