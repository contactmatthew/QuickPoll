<?php
require_once '../config.php';
require_once '../security.php';

header('Content-Type: application/json');

$security = new Security();

// Check rate limit
$rateLimit = $security->checkRateLimit('vote', MAX_VOTES_PER_IP_PER_MINUTE, 60);
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

if (!isset($input['poll_id']) || !isset($input['option_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Poll ID and Option ID are required']);
    exit;
}

$pollId = sanitizeInput($input['poll_id']);
$optionId = intval($input['option_id']);
$ip = getClientIP();

// Check if this is a view-only request
// ONLY check for view_token - direct links (without view_token) are NEVER view-only
$viewToken = isset($input['view_token']) ? sanitizeInput($input['view_token']) : (isset($_GET['view_token']) ? sanitizeInput($_GET['view_token']) : null);
$isViewOnly = false;

// Only treat as view-only if there's a valid view_token
// Direct links without view_token are NOT view-only, regardless of referrer
if ($viewToken) {
    // Verify the token
    $isViewOnly = verifyViewOnlyToken($viewToken, $pollId);
}
// No else clause - if no view_token, it's a direct link (isViewOnly stays false)

$db = getDBConnection();

// Check if poll exists and is not expired
$stmt = $db->prepare("SELECT id, expires_at, is_expired, password_hash FROM polls WHERE unique_id = ?");
$stmt->bind_param("s", $pollId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Poll not found']);
    exit;
}

$poll = $result->fetch_assoc();

// Check if poll is expired
if ($poll['is_expired'] || (strtotime($poll['expires_at']) < time())) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'This poll has expired']);
    exit;
}

// Password check: ONLY for view-only requests from homepage
// Direct shared links (without view_token) NEVER need password
if ($isViewOnly && !empty($poll['password_hash'])) {
    // Password is required for view-only requests - validate it
    if (!isset($input['password']) || empty(trim($input['password']))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Password is required to vote on this poll', 'requires_password' => true]);
        exit;
    }
    
    $providedPassword = trim($input['password']);
    if (!password_verify($providedPassword, $poll['password_hash'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Incorrect password', 'requires_password' => true]);
        exit;
    }
} elseif ($isViewOnly && empty($poll['password_hash'])) {
    // View-only mode but no password set - block voting
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Voting is disabled in view-only mode. Use the direct poll link to vote.']);
    exit;
}
// If NOT view-only (direct link), allow voting without password check

// Check if option belongs to poll
$stmt = $db->prepare("SELECT id FROM poll_options WHERE id = ? AND poll_id = ?");
$stmt->bind_param("ii", $optionId, $poll['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid option']);
    exit;
}

// Check if IP has already voted
$stmt = $db->prepare("SELECT id FROM votes WHERE poll_id = ? AND ip_address = ?");
$stmt->bind_param("is", $poll['id'], $ip);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You have already voted on this poll']);
    exit;
}

// Record vote
try {
    $db->begin_transaction();
    
    // Insert vote
    $stmt = $db->prepare("INSERT INTO votes (poll_id, option_id, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $poll['id'], $optionId, $ip);
    $stmt->execute();
    
    // Update vote count
    $stmt = $db->prepare("UPDATE poll_options SET vote_count = vote_count + 1 WHERE id = ?");
    $stmt->bind_param("i", $optionId);
    $stmt->execute();
    
    $db->commit();
    
    // Get updated results
    $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE poll_id = ? ORDER BY id");
    $stmt->bind_param("i", $poll['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $options = [];
    
    // Get base URL for images - detect HTTPS properly (defaults to HTTP for localhost, handles proxies like ngrok)
    $protocol = "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // For localhost, always use HTTP unless explicitly HTTPS
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $protocol = "http";
    } elseif (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
        $protocol = "https";
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = "https";
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        $protocol = "https";
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $protocol = "https";
    } elseif (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
        $protocol = "https";
    }
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $basePath = dirname(dirname($scriptPath));
    $basePath = '/' . trim($basePath, '/');
    $baseUrl = $protocol . "://" . $host . $basePath;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['image_path']) {
            if (strpos($row['image_path'], 'http://') !== 0 && strpos($row['image_path'], 'https://') !== 0) {
                // Normalize path - convert Windows backslashes to forward slashes
                $imagePath = str_replace('\\', '/', $row['image_path']);
                $imagePath = trim($imagePath, '/');
                $row['image_path'] = $baseUrl . '/' . $imagePath;
            }
        }
        $options[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Vote recorded successfully',
        'options' => $options,
        'user_vote_option_id' => $optionId
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to record vote']);
}

