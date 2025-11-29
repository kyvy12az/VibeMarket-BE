<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$BASE_URL = "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE";

// Lấy token từ header (optional - có thể cho phép anonymous)
$userId = get_user_id_from_token();

try {
    // Lấy limit từ query parameter (mặc định 20)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = max(1, min($limit, 50)); // Giới hạn từ 1-50

    // Lấy danh sách user đang online hoặc vừa mới offline (trong vòng 5 phút)
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.avatar,
            us.status,
            us.last_seen,
            CASE 
                WHEN us.status = 'online' THEN 'online'
                WHEN TIMESTAMPDIFF(MINUTE, us.last_seen, NOW()) <= 5 THEN 'away'
                ELSE 'offline'
            END as actual_status,
            TIMESTAMPDIFF(MINUTE, us.last_seen, NOW()) as minutes_ago
        FROM users u
        INNER JOIN user_status us ON u.id = us.user_id
        WHERE 
            us.status = 'online' 
            OR TIMESTAMPDIFF(MINUTE, us.last_seen, NOW()) <= 5
        ORDER BY 
            us.status DESC,
            us.last_seen DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $onlineUsers = [];
    
    while ($row = $result->fetch_assoc()) {
        $avatar = $row['avatar'];
        if (!empty($avatar) && !str_starts_with($avatar, "http")) {
            $avatar = $BASE_URL . $avatar;
        }

        // Tạo activity text dựa trên trạng thái
        $activity = "Đang hoạt động";
        if ($row['actual_status'] === 'away') {
            $minutes = (int)$row['minutes_ago'];
            if ($minutes < 1) {
                $activity = "Vừa hoạt động";
            } else {
                $activity = "Hoạt động " . $minutes . " phút trước";
            }
        } else if ($row['actual_status'] === 'online') {
            $activity = "Đang hoạt động";
        }

        $onlineUsers[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'avatar' => $avatar,
            'status' => $row['actual_status'],
            'activity' => $activity,
            'last_seen' => $row['last_seen']
        ];
    }

    echo json_encode([
        'success' => true,
        'users' => $onlineUsers,
        'total' => count($onlineUsers)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi lấy danh sách users: ' . $e->getMessage()
    ]);
}

$conn->close();
