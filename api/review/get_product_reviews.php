<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_end_clean();

// Set timezone cho Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

try {
    require_once '../../config/database.php';

    $product_id = $_GET['product_id'] ?? null;
    
    if (!$product_id) {
        throw new Exception('Thiếu product_id');
    }
    
    // Kiểm tra connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Không thể kết nối database');
    }
    
    // Lấy thống kê rating
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM product_reviews 
        WHERE product_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $product_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    $total_reviews = (int)$stats['total_reviews'];
    $avg_rating = $total_reviews > 0 ? round((float)$stats['avg_rating'], 1) : 0;
    
    // Tính phần trăm
    $rating_distribution = [];
    if ($total_reviews > 0) {
        for ($i = 5; $i >= 1; $i--) {
            $count = (int)$stats[
                $i === 5 ? 'five_star' : 
                ($i === 4 ? 'four_star' : 
                ($i === 3 ? 'three_star' : 
                ($i === 2 ? 'two_star' : 'one_star')))
            ];
            $rating_distribution[] = [
                'stars' => $i,
                'count' => $count,
                'percentage' => round(($count / $total_reviews) * 100, 1)
            ];
        }
    }
    
    // Lấy danh sách reviews
    $stmt = $conn->prepare("
        SELECT 
            pr.id,
            pr.rating,
            pr.comment,
            pr.images,
            pr.created_at,
            u.name as user_name,
            u.avatar
        FROM product_reviews pr
        INNER JOIN users u ON pr.user_id = u.id
        WHERE pr.product_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 50
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $product_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Tạo base URL đúng với backend path
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                "://" . $_SERVER['HTTP_HOST'] . "/VIBE_MARKET_BACKEND/VibeMarket-BE";
    
    // Lấy thời gian hiện tại theo timezone Việt Nam
    $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        // Parse images JSON
        $images = [];
        if (!empty($row['images'])) {
            // Xóa escape slashes trước khi decode
            $images_json = stripslashes($row['images']);
            $decoded = json_decode($images_json, true);
            
            if (is_array($decoded)) {
                $images = array_map(function($img) use ($base_url) {
                    // Xóa escape slashes nếu còn
                    $img = stripslashes($img);
                    
                    // Nếu là đường dẫn tương đối, thêm base URL
                    if (strpos($img, 'http') !== 0) {
                        return $base_url . $img;
                    }
                    return $img;
                }, $decoded);
            }
        }
        
        // Tính thời gian với DateTime để chính xác hơn
        try {
            $created_time = new DateTime($row['created_at'], new DateTimeZone('Asia/Ho_Chi_Minh'));
            $diff_interval = $now->diff($created_time);
            
            // Tính tổng số giây chênh lệch
            $total_seconds = (
                $diff_interval->y * 365 * 24 * 3600 +
                $diff_interval->m * 30 * 24 * 3600 +
                $diff_interval->d * 24 * 3600 +
                $diff_interval->h * 3600 +
                $diff_interval->i * 60 +
                $diff_interval->s
            );
            
            // Format thời gian
            if ($total_seconds < 60) {
                $time_ago = 'Vừa xong';
            } elseif ($total_seconds < 3600) {
                $minutes = floor($total_seconds / 60);
                $time_ago = $minutes . ' phút trước';
            } elseif ($total_seconds < 86400) {
                $hours = floor($total_seconds / 3600);
                $time_ago = $hours . ' giờ trước';
            } elseif ($total_seconds < 604800) {
                $days = floor($total_seconds / 86400);
                $time_ago = $days . ' ngày trước';
            } elseif ($total_seconds < 2592000) {
                $weeks = floor($total_seconds / 604800);
                $time_ago = $weeks . ' tuần trước';
            } elseif ($total_seconds < 31536000) {
                $months = floor($total_seconds / 2592000);
                $time_ago = $months . ' tháng trước';
            } else {
                $years = floor($total_seconds / 31536000);
                $time_ago = $years . ' năm trước';
            }
        } catch (Exception $e) {
            // Fallback về cách cũ nếu có lỗi
            $created_time_stamp = strtotime($row['created_at']);
            $now_stamp = time();
            $diff = $now_stamp - $created_time_stamp;
            
            if ($diff < 60) {
                $time_ago = 'Vừa xong';
            } elseif ($diff < 3600) {
                $time_ago = floor($diff / 60) . ' phút trước';
            } elseif ($diff < 86400) {
                $time_ago = floor($diff / 3600) . ' giờ trước';
            } elseif ($diff < 604800) {
                $time_ago = floor($diff / 86400) . ' ngày trước';
            } else {
                $time_ago = date('d/m/Y', $created_time_stamp);
            }
        }
        
        // Xử lý avatar URL
        $avatar_url = '';
        if (!empty($row['avatar'])) {
            $avatar = stripslashes($row['avatar']);
            if (strpos($avatar, 'http') !== 0) {
                $avatar_url = $base_url . $avatar;
            } else {
                $avatar_url = $avatar;
            }
        }
        
        $reviews[] = [
            'id' => (int)$row['id'],
            'rating' => (float)$row['rating'],
            'comment' => $row['comment'] ?? '',
            'images' => $images,
            'created_at' => $row['created_at'],
            'time_ago' => $time_ago,
            'user_name' => $row['user_name'] ?? 'Người dùng',
            'avatar_url' => $avatar_url,
            'likes' => 0
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_reviews' => $total_reviews,
            'avg_rating' => $avg_rating,
            'rating_distribution' => $rating_distribution
        ],
        'reviews' => $reviews,
        'current_time' => $now->format('Y-m-d H:i:s') // Debug: xem thời gian server
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename(__FILE__),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}