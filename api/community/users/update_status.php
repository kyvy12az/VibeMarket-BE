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
    $input = json_decode(file_get_contents('php://input'), true);
    $status = $input['status'] ?? 'online'; // online hoặc offline
    
    // Validate status
    if (!in_array($status, ['online', 'offline'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    // Kiểm tra xem user đã có record trong user_status chưa
    $checkSql = "SELECT id FROM user_status WHERE user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Update existing record
        $sql = "UPDATE user_status SET status = ?, last_seen = NOW() WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $userId);
    } else {
        // Insert new record
        $sql = "INSERT INTO user_status (user_id, status, last_seen) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $status);
    }

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'status' => $status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage()
    ]);
}

$conn->close();
