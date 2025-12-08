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

// Get pagination parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    
    // Get spin history with voucher info
    $stmt = $conn->prepare("
        SELECT 
            sh.id,
            sh.prize_id,
            sh.prize_name,
            sh.prize_value,
            sh.prize_rarity,
            sh.spun_at,
            uv.voucher_code,
            uv.is_used,
            uv.used_at,
            uv.expires_at
        FROM spin_history sh
        LEFT JOIN user_vouchers uv ON sh.voucher_id = uv.id
        WHERE sh.user_id = ?
        ORDER BY sh.spun_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history_item = [
            'prize' => [
                'id' => $row['prize_id'],
                'name' => $row['prize_name'],
                'value' => $row['prize_value'],
                'rarity' => $row['prize_rarity']
            ],
            'timestamp' => strtotime($row['spun_at']) * 1000 // Convert to milliseconds for JS
        ];
        
        if ($row['voucher_code']) {
            $history_item['voucher'] = [
                'code' => $row['voucher_code'],
                'is_used' => (bool)$row['is_used'],
                'used_at' => $row['used_at'],
                'expires_at' => $row['expires_at']
            ];
        }
        
        $history[] = $history_item;
    }
    
    $stmt->close();
    
    // Get total count
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM spin_history 
        WHERE user_id = ?
    ");
    
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'history' => $history,
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset
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
