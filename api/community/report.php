<?php
require_once '../../config/database.php';
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$body   = json_decode(file_get_contents('php://input'), true);
$postId = intval($body['post_id'] ?? 0);
$userId = intval($body['user_id'] ?? 0);
$reason = trim($body['reason'] ?? '');

if (!$postId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id or user_id']);
    exit;
}

if ($reason === '') {
    $reason = 'Người dùng không ghi lý do';
}

$check = $conn->prepare("SELECT id FROM post_reports WHERE post_id = ? AND user_id = ? LIMIT 1");
$check->bind_param("ii", $postId, $userId);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Bạn đã báo cáo bài viết này rồi']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO post_reports (post_id, user_id, reason) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $postId, $userId, $reason);
$ok = $stmt->execute();

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Đã gửi báo cáo' : 'Không thể gửi báo cáo'
]);
