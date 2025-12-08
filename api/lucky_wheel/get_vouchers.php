<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
require_once '../../config/jwt.php';

// Verify JWT token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Token not provided']);
    exit();
}

$jwt = $matches[1];
$decoded = verify_jwt($jwt);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid token']);
    exit();
}

$user_id = $decoded['sub'] ?? $decoded['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid user ID']);
    exit();
}

// Get filter parameter (all, unused, used)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'unused';

try {
    
    // Build query based on filter
    $where_clause = "WHERE user_id = ?";
    
    if ($filter === 'unused') {
        $where_clause .= " AND is_used = 0 AND expires_at > NOW()";
    } elseif ($filter === 'used') {
        $where_clause .= " AND is_used = 1";
    } elseif ($filter === 'expired') {
        $where_clause .= " AND is_used = 0 AND expires_at <= NOW()";
    }
    
    // Get user vouchers
    $stmt = $conn->prepare("
        SELECT 
            id,
            voucher_code,
            prize_id,
            prize_name,
            voucher_type,
            discount_amount,
            min_order_value,
            is_used,
            used_at,
            expires_at,
            created_at
        FROM user_vouchers
        $where_clause
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $vouchers = [];
    while ($row = $result->fetch_assoc()) {
        $is_expired = strtotime($row['expires_at']) < time();
        
        $voucher = [
            'id' => (int)$row['id'],
            'voucher_code' => $row['voucher_code'],
            'prize_name' => $row['prize_name'],
            'voucher_type' => $row['voucher_type'],
            'discount_amount' => (float)$row['discount_amount'],
            'min_order_value' => (float)$row['min_order_value'],
            'is_used' => (bool)$row['is_used'],
            'is_expired' => $is_expired,
            'used_at' => $row['used_at'],
            'expires_at' => $row['expires_at'],
            'created_at' => $row['created_at']
        ];
        
        // Add formatted descriptions
        if ($row['voucher_type'] === 'discount') {
            $voucher['description'] = 'Giảm ' . number_format($row['discount_amount'], 0, ',', '.') . 'đ';
            if ($row['min_order_value'] > 0) {
                $voucher['description'] .= ' cho đơn từ ' . number_format($row['min_order_value'], 0, ',', '.') . 'đ';
            }
        } elseif ($row['voucher_type'] === 'freeship') {
            $voucher['description'] = 'Miễn phí vận chuyển';
            if ($row['min_order_value'] > 0) {
                $voucher['description'] .= ' cho đơn từ ' . number_format($row['min_order_value'], 0, ',', '.') . 'đ';
            }
        }
        
        $vouchers[] = $voucher;
    }
    
    $stmt->close();
    
    // Get counts by status
    $count_stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN is_used = 0 AND expires_at > NOW() THEN 1 END) as unused_count,
            COUNT(CASE WHEN is_used = 1 THEN 1 END) as used_count,
            COUNT(CASE WHEN is_used = 0 AND expires_at <= NOW() THEN 1 END) as expired_count,
            COUNT(*) as total_count
        FROM user_vouchers
        WHERE user_id = ?
    ");
    
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $counts = $count_result->fetch_assoc();
    $count_stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'vouchers' => $vouchers,
            'counts' => [
                'unused' => (int)$counts['unused_count'],
                'used' => (int)$counts['used_count'],
                'expired' => (int)$counts['expired_count'],
                'total' => (int)$counts['total_count']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
