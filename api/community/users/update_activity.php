<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Lấy user_id từ token
$userId = get_user_id_from_token();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Kiểm tra xem user đã có record trong user_status chưa
    $checkSql = "SELECT id FROM user_status WHERE user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Update last_seen và set status = online nếu đang offline
        $sql = "UPDATE user_status SET last_seen = NOW(), status = 'online' WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    } else {
        // Insert new record
        $sql = "INSERT INTO user_status (user_id, status, last_seen) VALUES (?, 'online', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    }

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Activity updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi cập nhật hoạt động: ' . $e->getMessage()
    ]);
}

$conn->close();
