<?php
require_once '../config.php';
$db = getDBConnection();
$now = date('Y-m-d H:i:s');
$stmt = $db->prepare("SELECT id, unique_id, winner_option_id FROM polls WHERE expires_at < ? AND is_expired = FALSE");
$stmt->bind_param("s", $now);
$stmt->execute();
$result = $stmt->get_result();
$expiredPolls = [];
while ($row = $result->fetch_assoc()) {
    $expiredPolls[] = $row;
}
foreach ($expiredPolls as $poll) {
    $stmt = $db->prepare("SELECT id, option_text FROM poll_options WHERE poll_id = ? ORDER BY vote_count DESC, id ASC LIMIT 1");
    $stmt->bind_param("i", $poll['id']);
    $stmt->execute();
    $winnerResult = $stmt->get_result();
    $winnerOptionId = null;
    if ($winnerResult->num_rows > 0) {
        $winner = $winnerResult->fetch_assoc();
        $winnerOptionId = $winner['id'];
    }
    $stmt = $db->prepare("UPDATE polls SET is_expired = TRUE, winner_option_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $winnerOptionId, $poll['id']);
    $stmt->execute();
}
$deleteDate = date('Y-m-d H:i:s', time() - (7 * 86400));
$stmt = $db->prepare("SELECT id FROM polls WHERE is_expired = TRUE AND expires_at < ?");
$stmt->bind_param("s", $deleteDate);
$stmt->execute();
$result = $stmt->get_result();
$pollsToDelete = [];
while ($row = $result->fetch_assoc()) {
    $pollsToDelete[] = $row['id'];
}
$uploadDir = dirname(__DIR__) . '/' . UPLOAD_DIR;
foreach ($pollsToDelete as $pollId) {
    $stmt = $db->prepare("SELECT image_path FROM poll_options WHERE poll_id = ? AND image_path IS NOT NULL");
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $imageResult = $stmt->get_result();
    while ($imageRow = $imageResult->fetch_assoc()) {
        $absolutePath = dirname(__DIR__) . '/' . $imageRow['image_path'];
        if (file_exists($absolutePath)) {
            @unlink($absolutePath);
        }
    }
    $stmt = $db->prepare("DELETE FROM polls WHERE id = ?");
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
}
echo "Cleanup completed. Expired polls processed: " . count($expiredPolls) . ", Deleted old polls: " . count($pollsToDelete) . "\n";
