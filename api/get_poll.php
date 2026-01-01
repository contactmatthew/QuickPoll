<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Poll ID is required']);
    exit;
}

$pollId = sanitizeInput($_GET['id']);
$ip = getClientIP();

// Check for password in request (for verification)
$providedPassword = isset($_GET['password']) ? sanitizeInput($_GET['password']) : null;

// Check if this is a view-only request (from homepage)
// ONLY check for view_token - direct links (without view_token) are NEVER view-only
$viewToken = isset($_GET['view_token']) ? sanitizeInput($_GET['view_token']) : null;
$isViewOnly = false;

// Only treat as view-only if there's a valid view_token
// Direct links without view_token are NOT view-only, regardless of referrer
if ($viewToken) {
    // Verify the token
    $isViewOnly = verifyViewOnlyToken($viewToken, $pollId);
}
// No else clause - if no view_token, it's a direct link (isViewOnly stays false)

$db = getDBConnection();

// Get poll
$stmt = $db->prepare("SELECT id, unique_id, title, password_hash, created_at, expires_at, expiration_type, expiration_value, is_expired FROM polls WHERE unique_id = ?");
$stmt->bind_param("s", $pollId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Poll not found']);
    exit;
}

$poll = $result->fetch_assoc();

// Check if password is required (only for view-only requests from homepage)
$requiresPassword = $isViewOnly && !empty($poll['password_hash']);

// If password is required, verify it before returning poll data
if ($requiresPassword) {
    if (!$providedPassword) {
        // Password required but not provided - return error
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Password is required to view this poll',
            'requires_password' => true
        ]);
        exit;
    }
    
    // Verify password
    if (!password_verify($providedPassword, $poll['password_hash'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Incorrect password',
            'requires_password' => true
        ]);
        exit;
    }
}

// Check if poll is expired using MySQL NOW() for timezone consistency
$timeResult = $db->query("SELECT NOW() as now");
$timeRow = $timeResult->fetch_assoc();
$currentMySQLTime = strtotime($timeRow['now']);
$expiresAtTime = strtotime($poll['expires_at']);

$isExpired = $poll['is_expired'] || ($expiresAtTime < $currentMySQLTime);

if ($isExpired && !$poll['is_expired']) {
    // Mark as expired
    $stmt = $db->prepare("UPDATE polls SET is_expired = TRUE WHERE id = ?");
    $stmt->bind_param("i", $poll['id']);
    $stmt->execute();
    $poll['is_expired'] = true;
}

// Get base URL for images - more reliable method
// Detect HTTPS properly (defaults to HTTP for localhost, handles proxies like ngrok)
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

// Get the base path - use SCRIPT_NAME which is more reliable
$scriptPath = $_SERVER['SCRIPT_NAME']; // e.g., /QuickPoll/api/get_poll.php
$basePath = dirname(dirname($scriptPath)); // e.g., /QuickPoll

// Normalize the path - ensure it starts with /
$basePath = '/' . trim($basePath, '/');
$baseUrl = $protocol . "://" . $host . $basePath;

$options = [];
$totalVotes = 0;
$winner = null;

if ($isExpired) {
    // If expired, calculate winner first if not already stored
    if (!$poll['winner_option_id']) {
        // Get all options to find winner (get all to calculate total votes for tie-breaking)
        $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE poll_id = ? ORDER BY vote_count DESC, id ASC");
        $stmt->bind_param("i", $poll['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $allOptions = [];
        while ($row = $result->fetch_assoc()) {
            $allOptions[] = $row;
        }
        
        if (count($allOptions) > 0) {
            // Find winner (highest vote count)
            $tempWinner = $allOptions[0];
            // Store winner in database
            $stmt = $db->prepare("UPDATE polls SET winner_option_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $tempWinner['id'], $poll['id']);
            $stmt->execute();
            $poll['winner_option_id'] = $tempWinner['id'];
        }
    }
    
    // If winner exists, only get the winner option
    if ($poll['winner_option_id']) {
        // Get winner from database
        $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE id = ?");
        $stmt->bind_param("i", $poll['winner_option_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $winner = $result->fetch_assoc();
            // Process image path
            if ($winner['image_path']) {
                if (strpos($winner['image_path'], 'http://') !== 0 && strpos($winner['image_path'], 'https://') !== 0) {
                    $imagePath = str_replace('\\', '/', $winner['image_path']);
                    $imagePath = trim($imagePath, '/');
                    $winner['image_path'] = $baseUrl . '/' . $imagePath;
                }
            }
            $winner['percentage'] = 100; // Winner gets 100% display
            $options = [$winner]; // Only return winner - CRITICAL: Only winner in options array
            $totalVotes = $winner['vote_count']; // Set total votes for display
            // Ensure winner variable is set (it's already set above, but make sure it's the same reference)
        } else {
            // Winner option not found, return empty
            $options = [];
            $totalVotes = 0;
        }
    } else {
        // No winner (no votes), return empty options array
        $options = [];
        $totalVotes = 0;
    }
    // If expired, always set these
    $hasVoted = false;
    $userVoteOptionId = null;
} else {
    // If not expired, get all options
    $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE poll_id = ? ORDER BY id");
    $stmt->bind_param("i", $poll['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['image_path']) {
            // Ensure image path doesn't already start with http
            if (strpos($row['image_path'], 'http://') === 0 || strpos($row['image_path'], 'https://') === 0) {
                // Already a full URL, use as is
            } else {
                // Normalize path - convert Windows backslashes to forward slashes
                $imagePath = str_replace('\\', '/', $row['image_path']);
                // Remove leading slashes and ensure clean path
                $imagePath = trim($imagePath, '/');
                // Build full URL
                $row['image_path'] = $baseUrl . '/' . $imagePath;
            }
        }
        $options[] = $row;
        $totalVotes += $row['vote_count'];
    }
    
    // Check if user has voted
    $hasVoted = false;
    $userVoteOptionId = null;
    $stmt = $db->prepare("SELECT option_id FROM votes WHERE poll_id = ? AND ip_address = ?");
    $stmt->bind_param("is", $poll['id'], $ip);
    $stmt->execute();
    $voteResult = $stmt->get_result();
    if ($voteResult->num_rows > 0) {
        $hasVoted = true;
        $userVoteOptionId = $voteResult->fetch_assoc()['option_id'];
    }
    
    // Calculate percentages and find winner
    foreach ($options as &$option) {
        $option['percentage'] = $totalVotes > 0 ? round(($option['vote_count'] / $totalVotes) * 100, 1) : 0;
    }
    
    // Find winner
    if ($totalVotes > 0) {
        usort($options, function($a, $b) {
            return $b['vote_count'] - $a['vote_count'];
        });
        $winner = $options[0];
        
        // Re-sort by ID for display
        usort($options, function($a, $b) {
            return $a['id'] - $b['id'];
        });
    }
}

// Remove password hash from response for security
unset($poll['password_hash']);

// For expired polls, return winner in options but also include all options for rankings
$allOptionsForRankings = [];
if ($isExpired) {
    // Store all options for rankings display
    if (!$poll['winner_option_id']) {
        // If winner not stored, get all options to find winner
        $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE poll_id = ? ORDER BY vote_count DESC, id ASC");
        $stmt->bind_param("i", $poll['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['image_path']) {
                if (strpos($row['image_path'], 'http://') !== 0 && strpos($row['image_path'], 'https://') !== 0) {
                    $imagePath = str_replace('\\', '/', $row['image_path']);
                    $imagePath = trim($imagePath, '/');
                    $row['image_path'] = $baseUrl . '/' . $imagePath;
                }
            }
            $row['percentage'] = 0; // Will be calculated
            $allOptionsForRankings[] = $row;
        }
    } else {
        // Get all options for rankings
        $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE poll_id = ? ORDER BY vote_count DESC, id ASC");
        $stmt->bind_param("i", $poll['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalVotesForRankings = 0;
        while ($row = $result->fetch_assoc()) {
            if ($row['image_path']) {
                if (strpos($row['image_path'], 'http://') !== 0 && strpos($row['image_path'], 'https://') !== 0) {
                    $imagePath = str_replace('\\', '/', $row['image_path']);
                    $imagePath = trim($imagePath, '/');
                    $row['image_path'] = $baseUrl . '/' . $imagePath;
                }
            }
            $totalVotesForRankings += $row['vote_count'];
            $allOptionsForRankings[] = $row;
        }
        // Calculate percentages
        foreach ($allOptionsForRankings as &$opt) {
            $opt['percentage'] = $totalVotesForRankings > 0 ? round(($opt['vote_count'] / $totalVotesForRankings) * 100, 1) : 0;
        }
    }
    
    // Force only winner in options array for expired polls (main display)
    if (!empty($winner)) {
        $options = [$winner];
    } else {
        // No winner, return empty array
        $options = [];
    }
}

$response = [
    'success' => true,
    'poll' => $poll,
    'options' => $options, // For expired polls, this should only contain the winner
    'total_votes' => $totalVotes,
    'has_voted' => $hasVoted,
    'user_vote_option_id' => $userVoteOptionId,
    'is_expired' => $isExpired,
    'winner' => $winner,
    'is_view_only' => $isViewOnly, // Server-side flag - cannot be bypassed by URL manipulation
    'requires_password' => $requiresPassword // Indicates if password is required (only for view-only mode)
];

// Add all options for rankings if expired
if ($isExpired && !empty($allOptionsForRankings)) {
    $response['allOptions'] = $allOptionsForRankings;
}

echo json_encode($response);

