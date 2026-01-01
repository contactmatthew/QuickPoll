<?php
// Set headers first to prevent any output before JSON
header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';
require_once '../security.php';

$security = new Security();

// Check rate limit
$rateLimit = $security->checkRateLimit('create_poll', MAX_POLLS_PER_IP_PER_HOUR, 3600);
if (!$rateLimit['allowed']) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => $rateLimit['message']]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['title']) || empty(trim($input['title']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

$title = sanitizeInput($input['title']);
if (strlen($title) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is too long']);
    exit;
}

if (!isset($input['options']) || !is_array($input['options']) || count($input['options']) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'At least 2 options are required']);
    exit;
}

if (count($input['options']) > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Maximum 20 options allowed']);
    exit;
}

// Validate expiration settings
$expirationType = isset($input['expiration_type']) ? $input['expiration_type'] : 'days';
$expirationValue = isset($input['expiration_value']) ? intval($input['expiration_value']) : 7;

// Get current time from MySQL to ensure timezone consistency
$db = getDBConnection();
$timeResult = $db->query("SELECT NOW() as now");
$timeRow = $timeResult->fetch_assoc();
$currentTime = strtotime($timeRow['now']);

if ($expirationType === 'hours') {
    if ($expirationValue < 1 || $expirationValue > 24) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Hours must be between 1 and 24']);
        exit;
    }
    $expiresAt = date('Y-m-d H:i:s', $currentTime + ($expirationValue * 3600));
} else {
    if ($expirationValue < 1 || $expirationValue > 30) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Days must be between 1 and 30']);
        exit;
    }
    $expiresAt = date('Y-m-d H:i:s', $currentTime + ($expirationValue * 86400));
}

// Handle password (optional)
$passwordHash = null;
if (isset($input['password']) && !empty(trim($input['password']))) {
    $password = trim($input['password']);
    // Hash the password using bcrypt
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
}

$db = getDBConnection();
$uniqueId = generateUniqueID();

// Start transaction
$db->begin_transaction();

try {
    // Insert poll
    $stmt = $db->prepare("INSERT INTO polls (unique_id, title, password_hash, expires_at, expiration_type, expiration_value) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $uniqueId, $title, $passwordHash, $expiresAt, $expirationType, $expirationValue);
    $stmt->execute();
    $pollId = $db->insert_id;
    
    // Insert options
    $stmt = $db->prepare("INSERT INTO poll_options (poll_id, option_text, image_path) VALUES (?, ?, ?)");
    
    foreach ($input['options'] as $option) {
        $optionText = sanitizeInput($option['text']);
        if (empty(trim($optionText))) {
            continue;
        }
        
        $imagePath = null;
        if (isset($option['image']) && !empty($option['image'])) {
            // Handle base64 image
            $imageData = $option['image'];
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $imageData = base64_decode($imageData);
                $mimeType = $matches[1];
                
                // Map MIME type to extension
                $extensionMap = [
                    'jpeg' => 'jpg',
                    'jpg' => 'jpg',
                    'png' => 'png',
                    'gif' => 'gif',
                    'webp' => 'webp'
                ];
                $extension = isset($extensionMap[$mimeType]) ? $extensionMap[$mimeType] : 'jpg';
                
                if ($imageData !== false && strlen($imageData) > 0) {
                    // Verify it's actually an image
                    $imageInfo = @getimagesizefromstring($imageData);
                    if ($imageInfo !== false) {
                        $filename = $security->sanitizeFilename("option_" . uniqid() . ".$extension");
                        
                        // Get absolute path to uploads directory
                        $uploadDir = dirname(__DIR__) . '/' . UPLOAD_DIR;
                        $filepath = $uploadDir . $filename;
                        
                        // Create upload directory if it doesn't exist
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        if (file_put_contents($filepath, $imageData)) {
                            // Store relative path in database
                            $imagePath = UPLOAD_DIR . $filename;
                        }
                    }
                }
            }
        }
        
        $stmt->bind_param("iss", $pollId, $optionText, $imagePath);
        $stmt->execute();
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'poll_id' => $uniqueId,
        'message' => 'Poll created successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    // Log error but don't expose details to user
    error_log("Poll creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create poll. Please try again.']);
}

