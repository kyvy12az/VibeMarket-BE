<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();
checkRateLimit(20, 60, 'update_conversation'); // 20 requests per minute

// Get JWT token
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Thiếu token']);
    exit;
}

if (!preg_match('/Bearer\s+(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
    exit;
}

$jwt = $matches[1];

try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->sub;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token hết hạn']);
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'PUT':
        case 'PATCH':
            updateConversation($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hỗ trợ']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function updateConversation($conn, $user_id)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['conversation_id'])) {
        throw new Exception('Dữ liệu không hợp lệ');
    }

    $conversation_id = (int)$input['conversation_id'];
    
    // Check if user is participant in this conversation
    $check_sql = "SELECT c.id, c.type, c.created_by 
                  FROM conversations c
                  INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
                  WHERE c.id = ? AND cp.user_id = ? AND cp.left_at IS NULL";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $conversation_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Không có quyền truy cập cuộc trò chuyện này');
    }
    
    $conversation = $result->fetch_assoc();
    
    // For private conversations, any participant can change background
    // For group conversations, only admin/creator can change background
    if ($conversation['type'] === 'group') {
        // Check if user is admin or creator
        $admin_sql = "SELECT role FROM conversation_participants 
                      WHERE conversation_id = ? AND user_id = ? AND (role = 'admin' OR role = 'creator')";
        $admin_stmt = $conn->prepare($admin_sql);
        $admin_stmt->bind_param('ii', $conversation_id, $user_id);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        
        if ($admin_result->num_rows === 0) {
            throw new Exception('Chỉ quản trị viên mới có thể thay đổi màu nền nhóm');
        }
    }

    $updates = [];
    $params = [];
    $types = '';

    // Handle background color update
    if (isset($input['background_color'])) {
        $background_color = $input['background_color'];
        
        // Validate color format
        $valid_colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', 
            '#43e97b', '#fa709a', '#ffecd2', '#a8edea', '#d299c2',
            '#e879f9', '#3b82f6', '#22c55e', '#f97316', '#f59e0b', '#06b6d4', '#8b5cf6'
        ];
        
        if ($background_color && !in_array($background_color, $valid_colors) && !preg_match('/^#[0-9a-f]{6}$/i', $background_color)) {
            throw new Exception('Màu nền không hợp lệ');
        }
        
        $updates[] = 'background_color = ?';
        $params[] = $background_color;
        $types .= 's';
    }

    // Handle message color update
    if (isset($input['message_color'])) {
        $message_color = $input['message_color'];
        
        if ($message_color && !preg_match('/^#[0-9a-f]{6}$/i', $message_color)) {
            throw new Exception('Màu tin nhắn không hợp lệ');
        }
        
        $updates[] = 'message_color = ?';
        $params[] = $message_color;
        $types .= 's';
    }

    // Handle message text color update
    if (isset($input['message_text_color'])) {
        $message_text_color = $input['message_text_color'];
        
        if ($message_text_color && !preg_match('/^#[0-9a-f]{6}$/i', $message_text_color)) {
            throw new Exception('Màu chữ tin nhắn không hợp lệ');
        }
        
        $updates[] = 'message_text_color = ?';
        $params[] = $message_text_color;
        $types .= 's';
    }

    // Handle other updates (name, avatar) if needed
    if (isset($input['name']) && $conversation['type'] === 'group') {
        $updates[] = 'name = ?';
        $params[] = $input['name'];
        $types .= 's';
    }

    if (isset($input['avatar'])) {
        $updates[] = 'avatar = ?';
        $params[] = $input['avatar'];
        $types .= 's';
    }

    if (empty($updates)) {
        throw new Exception('Không có dữ liệu để cập nhật');
    }

    // Update conversation
    $sql = "UPDATE conversations SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $params[] = $conversation_id;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật thành công',
            'data' => [
                'conversation_id' => $conversation_id,
                'background_color' => $input['background_color'] ?? null,
                'message_color' => $input['message_color'] ?? null,
                'message_text_color' => $input['message_text_color'] ?? null
            ]
        ]);
    } else {
        throw new Exception('Không thể cập nhật cuộc trò chuyện');
    }
}
?>