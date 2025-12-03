<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

// Lấy token từ header
$headers = getallheaders();
$jwt = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$jwt) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không tồn tại']);
    exit;
}

// Decode JWT
try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $userId = $decoded->sub;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ']);
    exit;
}

// GET: Lấy thông tin profile
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Lấy thông tin user cơ bản
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.address, u.avatar, u.bio, u.created_at, u.role,
               IFNULL(up.points, 0) as points,
               IFNULL(up.level, 'Bronze Member') as level
        FROM users u
        LEFT JOIN user_points up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy người dùng']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Lấy thống kê đơn hàng
    $stmt_orders = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            IFNULL(SUM(total), 0) as total_spent
        FROM orders 
        WHERE customer_id = ?
    ");
    
    if ($stmt_orders === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt_orders->bind_param("i", $userId);
    $stmt_orders->execute();
    $orders_result = $stmt_orders->get_result();
    $order_stats = $orders_result->fetch_assoc();
    $stmt_orders->close();
    
    // Lấy số lượng reviews
    $stmt_reviews = $conn->prepare("SELECT COUNT(*) as total_reviews FROM product_reviews WHERE user_id = ?");
    
    if ($stmt_reviews === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt_reviews->bind_param("i", $userId);
    $stmt_reviews->execute();
    $reviews_result = $stmt_reviews->get_result();
    $review_stats = $reviews_result->fetch_assoc();
    $stmt_reviews->close();
    
    // Lấy số followers và following
    $followers_stats = ['followers' => 0];
    $following_stats = ['following' => 0];
    
    // Thử query followers - nếu lỗi thì bỏ qua
    try {
        $stmt_followers = $conn->prepare("SELECT COUNT(*) as followers FROM user_followers WHERE following_id = ?");
        if ($stmt_followers !== false) {
            $stmt_followers->bind_param("i", $userId);
            $stmt_followers->execute();
            $followers_result = $stmt_followers->get_result();
            if ($followers_result) {
                $followers_stats = $followers_result->fetch_assoc();
            }
            $stmt_followers->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching followers: " . $e->getMessage());
    }
    
    // Thử query following - nếu lỗi thì bỏ qua
    try {
        $stmt_following = $conn->prepare("SELECT COUNT(*) as following FROM user_followers WHERE follower_id = ?");
        if ($stmt_following !== false) {
            $stmt_following->bind_param("i", $userId);
            $stmt_following->execute();
            $following_result = $stmt_following->get_result();
            if ($following_result) {
                $following_stats = $following_result->fetch_assoc();
            }
            $stmt_following->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching following: " . $e->getMessage());
    }
    
    // Lấy thống kê chi tiêu theo tháng (6 tháng gần nhất)
    $stmt_spending = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%m') as month,
            COUNT(*) as orders,
            IFNULL(SUM(total), 0) as amount
        FROM orders 
        WHERE customer_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND payment_status = 'paid'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ");
    
    if ($stmt_spending === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt_spending->bind_param("i", $userId);
    $stmt_spending->execute();
    $spending_result = $stmt_spending->get_result();
    $spending_data = [];
    while ($row = $spending_result->fetch_assoc()) {
        $spending_data[] = [
            'month' => 'T' . $row['month'],
            'orders' => (int)$row['orders'],
            'amount' => (float)$row['amount']
        ];
    }
    $stmt_spending->close();
    
    // Lấy hoạt động gần đây
    $stmt_activity = $conn->prepare("
        SELECT 
            'review' as type,
            CONCAT('Đánh giá ', r.rating, ' sao cho ', p.name) as content,
            r.created_at as time
        FROM product_reviews r
        JOIN products p ON r.product_id = p.id
        WHERE r.user_id = ?
        
        UNION ALL
        
        SELECT 
            'order' as type,
            CONCAT('Đặt hàng thành công #', o.code) as content,
            o.created_at as time
        FROM orders o
        WHERE o.customer_id = ?
        
        ORDER BY time DESC
        LIMIT 10
    ");
    
    if ($stmt_activity === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt_activity->bind_param("ii", $userId, $userId);
    $stmt_activity->execute();
    $activity_result = $stmt_activity->get_result();
    $recent_activity = [];
    while ($row = $activity_result->fetch_assoc()) {
        $created_at = new DateTime($row['time']);
        $now = new DateTime();
        $diff = $now->diff($created_at);
        
        if ($diff->d > 0) {
            $time_ago = $diff->d . ' ngày trước';
        } elseif ($diff->h > 0) {
            $time_ago = $diff->h . ' giờ trước';
        } else {
            $time_ago = $diff->i . ' phút trước';
        }
        
        $recent_activity[] = [
            'type' => $row['type'],
            'content' => $row['content'],
            'time' => $time_ago
        ];
    }
    $stmt_activity->close();
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'address' => $user['address'],
            'avatar' => $user['avatar'],
            'bio' => $user['bio'],
            'joinDate' => date('F, Y', strtotime($user['created_at'])),
            'points' => (int)$user['points'],
            'level' => $user['level'],
            'stats' => [
                'totalOrders' => (int)$order_stats['total_orders'],
                'totalSpent' => (float)$order_stats['total_spent'],
                'reviews' => (int)$review_stats['total_reviews'],
                'followers' => (int)$followers_stats['followers'],
                'following' => (int)$following_stats['following']
            ]
        ],
        'spendingData' => $spending_data,
        'recentActivity' => $recent_activity
    ]);
}

// PUT: Cập nhật thông tin profile
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $updateFields = [];
    $params = [];
    $types = '';
    
    if (isset($data['name'])) {
        $updateFields[] = "name = ?";
        $params[] = trim($data['name']);
        $types .= 's';
    }
    
    if (isset($data['phone'])) {
        $updateFields[] = "phone = ?";
        $params[] = trim($data['phone']);
        $types .= 's';
    }
    
    if (isset($data['address'])) {
        $updateFields[] = "address = ?";
        $params[] = trim($data['address']);
        $types .= 's';
    }
    
    if (isset($data['bio'])) {
        $updateFields[] = "bio = ?";
        $params[] = trim($data['bio']);
        $types .= 's';
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'Không có dữ liệu để cập nhật']);
        exit;
    }
    
    $params[] = $userId;
    $types .= 'i';
    
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Không thể cập nhật thông tin']);
    }
    
    $stmt->close();
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ']);
}

$conn->close();
