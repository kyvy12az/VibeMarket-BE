<?php
require_once '../../config/database.php';
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$body     = json_decode(file_get_contents('php://input'), true);
$postId   = intval($body['post_id'] ?? 0);
$userId   = intval($body['user_id'] ?? 0);
$platform = trim($body['platform'] ?? 'internal');

if (!$postId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id or user_id']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO post_shares (post_id, user_id, platform) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $postId, $userId, $platform);
$ok = $stmt->execute();

if ($ok) {
    $conn->query("INSERT INTO activity_logs (user_id, type, reference_id) VALUES ($userId, 'share', $postId)");
}

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Đã ghi nhận chia sẻ' : 'Không thể ghi nhận chia sẻ'
]);
