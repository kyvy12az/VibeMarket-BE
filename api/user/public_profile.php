<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();

// Lấy user_id từ URL parameter
$targetUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu user_id']);
    exit;
}

// Lấy token từ header (optional - để check follow status)
$headers = getallheaders();
$jwt = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
$currentUserId = null;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
        $currentUserId = $decoded->sub;
    } catch (Exception $e) {
        // Token không hợp lệ - không sao, vẫn cho xem profile công khai
        $currentUserId = null;
    }
}

// GET: Lấy thông tin profile công khai
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Lấy thông tin user cơ bản
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, u.avatar, u.bio, u.created_at,
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
    
    $stmt->bind_param("i", $targetUserId);
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
    
    $stmt_orders->bind_param("i", $targetUserId);
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
    
    $stmt_reviews->bind_param("i", $targetUserId);
    $stmt_reviews->execute();
    $reviews_result = $stmt_reviews->get_result();
    $review_stats = $reviews_result->fetch_assoc();
    $stmt_reviews->close();
    
    // Lấy số followers và following
    $followers_stats = ['followers' => 0];
    $following_stats = ['following' => 0];
    
    try {
        $stmt_followers = $conn->prepare("SELECT COUNT(*) as followers FROM user_followers WHERE following_id = ?");
        if ($stmt_followers !== false) {
            $stmt_followers->bind_param("i", $targetUserId);
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
    
    try {
        $stmt_following = $conn->prepare("SELECT COUNT(*) as following FROM user_followers WHERE follower_id = ?");
        if ($stmt_following !== false) {
            $stmt_following->bind_param("i", $targetUserId);
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
    
    // Check xem current user có đang follow target user không
    $isFollowing = false;
    if ($currentUserId && $currentUserId !== $targetUserId) {
        try {
            $stmt_check = $conn->prepare("SELECT 1 FROM user_followers WHERE follower_id = ? AND following_id = ?");
            if ($stmt_check !== false) {
                $stmt_check->bind_param("ii", $currentUserId, $targetUserId);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result();
                $isFollowing = $check_result->num_rows > 0;
                $stmt_check->close();
            }
        } catch (Exception $e) {
            error_log("Error checking follow status: " . $e->getMessage());
        }
    }
    
    // Lấy hoạt động gần đây (chỉ reviews và orders thành công)
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
            CONCAT('Đã mua hàng thành công') as content,
            o.created_at as time
        FROM orders o
        WHERE o.customer_id = ? AND o.payment_status = 'paid'
        
        ORDER BY time DESC
        LIMIT 10
    ");
    
    if ($stmt_activity === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt_activity->bind_param("ii", $targetUserId, $targetUserId);
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
        'recentActivity' => $recent_activity,
        'isFollowing' => $isFollowing,
        'isOwnProfile' => $currentUserId === $targetUserId
    ]);
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ']);
}

$conn->close();
