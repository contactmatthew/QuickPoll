<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db = getDBConnection();

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? min(20, max(5, intval($_GET['per_page']))) : 10;
$offset = ($page - 1) * $perPage;

// Get total count of active polls (use MySQL NOW() for timezone consistency)
$countStmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as total
    FROM polls p
    WHERE p.expires_at > NOW() AND p.is_expired = FALSE
");
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalPolls = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalPolls / $perPage);

// Get active (non-expired) polls with pagination (use MySQL NOW() for timezone consistency)
$stmt = $db->prepare("
    SELECT 
        p.id,
        p.unique_id,
        p.title,
        p.created_at,
        p.expires_at,
        p.expiration_type,
        p.expiration_value,
        COUNT(DISTINCT v.id) as total_votes,
        COUNT(DISTINCT po.id) as option_count
    FROM polls p
    LEFT JOIN poll_options po ON p.id = po.poll_id
    LEFT JOIN votes v ON p.id = v.poll_id
    WHERE p.expires_at > NOW() AND p.is_expired = FALSE
    GROUP BY p.id, p.unique_id, p.title, p.created_at, p.expires_at, p.expiration_type, p.expiration_value
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$polls = [];
// Get current MySQL time for comparison
$currentTimeResult = $db->query("SELECT NOW() as now");
$currentTimeRow = $currentTimeResult->fetch_assoc();
$currentMySQLTime = strtotime($currentTimeRow['now']);

while ($row = $result->fetch_assoc()) {
    // Double-check expiration using MySQL time for consistency
    $expiresAt = strtotime($row['expires_at']);
    $timeRemaining = $expiresAt - $currentMySQLTime;
    
    // Only include polls that are still active (not expired)
    if ($timeRemaining > 0) {
        // Get base URL for poll link (clean URL)
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
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $basePath = dirname(dirname($scriptPath));
        $basePath = '/' . trim($basePath, '/');
        
        // Generate signed token for view-only access from homepage
        $viewToken = generateViewOnlyToken($row['unique_id']);
        $row['poll_url'] = $protocol . "://" . $host . $basePath . '/poll/' . $row['unique_id'];
        $row['poll_url_view'] = $row['poll_url'] . '?view_token=' . urlencode($viewToken);
        
        $polls[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'polls' => $polls,
    'count' => count($polls),
    'total' => $totalPolls,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => $totalPages
]);

