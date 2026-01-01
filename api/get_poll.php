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
$providedPassword = isset($_GET['password']) ? sanitizeInput($_GET['password']) : null;
$viewToken = isset($_GET['view_token']) ? sanitizeInput($_GET['view_token']) : null;
$isViewOnly = false;
if ($viewToken) {
    $isViewOnly = verifyViewOnlyToken($viewToken, $pollId);
}
$db = getDBConnection();
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
$requiresPassword = $isViewOnly && !empty($poll['password_hash']);
if ($requiresPassword) {
    if (!$providedPassword) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Password is required to view this poll',
            'requires_password' => true
        ]);
        exit;
    }
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
$timeResult = $db->query("SELECT NOW() as now");
$timeRow = $timeResult->fetch_assoc();
$currentMySQLTime = strtotime($timeRow['now']);
$expiresAtTime = strtotime($poll['expires_at']);
$isExpired = $poll['is_expired'] || ($expiresAtTime < $currentMySQLTime);
if ($isExpired && !$poll['is_expired']) {
    $stmt = $db->prepare("UPDATE polls SET is_expired = TRUE WHERE id = ?");
    $stmt->bind_param("i", $poll['id']);
    $stmt->execute();
    $poll['is_expired'] = true;
}
$protocol = "http";
$host = $_SERVER['HTTP_HOST'];
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
$scriptPath = $_SERVER['SCRIPT_NAME']; // e.g., /QuickPoll/api/get_poll.php
$basePath = dirname(dirname($scriptPath)); // e.g., /QuickPoll
$basePath = '/' . trim($basePath, '/');
$baseUrl = $protocol . "://" . $host . $basePath;
$options = [];
$totalVotes = 0;
$winner = null;
if ($isExpired) {
    if (!$poll['winner_option_id']) {
        $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE poll_id = ? ORDER BY vote_count DESC, id ASC");
        $stmt->bind_param("i", $poll['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $allOptions = [];
        while ($row = $result->fetch_assoc()) {
            $allOptions[] = $row;
        }
        if (count($allOptions) > 0) {
            $tempWinner = $allOptions[0];
            $stmt = $db->prepare("UPDATE polls SET winner_option_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $tempWinner['id'], $poll['id']);
            $stmt->execute();
            $poll['winner_option_id'] = $tempWinner['id'];
        }
    }
    if ($poll['winner_option_id']) {
        $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE id = ?");
        $stmt->bind_param("i", $poll['winner_option_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $winner = $result->fetch_assoc();
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
        } else {
            $options = [];
            $totalVotes = 0;
        }
    } else {
        $options = [];
        $totalVotes = 0;
    }
    $hasVoted = false;
    $userVoteOptionId = null;
} else {
    $stmt = $db->prepare("SELECT id, option_text, image_path, vote_count FROM poll_options WHERE poll_id = ? ORDER BY id");
    $stmt->bind_param("i", $poll['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['image_path']) {
            if (strpos($row['image_path'], 'http://') === 0 || strpos($row['image_path'], 'https://') === 0) {
            } else {
                $imagePath = str_replace('\\', '/', $row['image_path']);
                $imagePath = trim($imagePath, '/');
                $row['image_path'] = $baseUrl . '/' . $imagePath;
            }
        }
        $options[] = $row;
        $totalVotes += $row['vote_count'];
    }
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
    foreach ($options as &$option) {
        $option['percentage'] = $totalVotes > 0 ? round(($option['vote_count'] / $totalVotes) * 100, 1) : 0;
    }
    if ($totalVotes > 0) {
        usort($options, function($a, $b) {
            return $b['vote_count'] - $a['vote_count'];
        });
        $winner = $options[0];
        usort($options, function($a, $b) {
            return $a['id'] - $b['id'];
        });
    }
}
unset($poll['password_hash']);
$allOptionsForRankings = [];
if ($isExpired) {
    if (!$poll['winner_option_id']) {
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
        foreach ($allOptionsForRankings as &$opt) {
            $opt['percentage'] = $totalVotesForRankings > 0 ? round(($opt['vote_count'] / $totalVotesForRankings) * 100, 1) : 0;
        }
    }
    if (!empty($winner)) {
        $options = [$winner];
    } else {
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
if ($isExpired && !empty($allOptionsForRankings)) {
    $response['allOptions'] = $allOptionsForRankings;
}
echo json_encode($response);
