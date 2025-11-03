<?php
// Trả JSON sạch
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (function_exists('int_headers')) {
    int_headers();
}
checkRateLimit(5, 10);

function flush_and_exit($payload, $code = 200) {
    $extra = trim(ob_get_clean());
    if (!empty($extra)) error_log("[leave.php] Unexpected output: " . $extra);
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// Only allow POST (client may call POST or fallback to GET; accept both but prefer POST)
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST' && $method !== 'GET') {
    flush_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

// read conversation_id (POST JSON body or GET query)
$conversation_id = 0;
$input = null;
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (isset($input['conversation_id'])) $conversation_id = intval($input['conversation_id']);
    // also allow query param fallback
    if ($conversation_id <= 0 && isset($_GET['conversation_id'])) $conversation_id = intval($_GET['conversation_id']);
} else {
    $conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
}

if ($conversation_id <= 0) {
    flush_and_exit(['success' => false, 'message' => 'conversation_id không hợp lệ'], 400);
}

// Auth: get Authorization header
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = '';
if (isset($headers['Authorization'])) $authHeader = $headers['Authorization'];
elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    flush_and_exit(['success' => false, 'message' => 'Unauthorized'], 401);
}

$jwt = $matches[1];

// decode JWT
$jwt_secret_candidates = [];
if (isset($JWT_SECRET)) $jwt_secret_candidates[] = $JWT_SECRET;
if (isset($SECRET_KEY)) $jwt_secret_candidates[] = $SECRET_KEY;
if (defined('JWT_SECRET') && JWT_SECRET) $jwt_secret_candidates[] = JWT_SECRET;
$jwt_secret = count($jwt_secret_candidates) ? $jwt_secret_candidates[0] : null;
if (!$jwt_secret) {
    error_log('[leave.php] JWT secret not found');
    flush_and_exit(['success' => false, 'message' => 'Server configuration error'], 500);
}

try {
    $decoded = JWT::decode($jwt, new Key($jwt_secret, 'HS256'));
    $user_id = $decoded->sub ?? $decoded->user_id ?? $decoded->id ?? null;
    if (!$user_id) throw new Exception('Invalid token payload');
} catch (Exception $e) {
    flush_and_exit(['success' => false, 'message' => 'Unauthorized: ' . $e->getMessage()], 401);
}

if (!isset($conn)) {
    flush_and_exit(['success' => false, 'message' => 'Database connection not available'], 500);
}

try {
    // check participant exists and not already left
    $check = $conn->prepare("SELECT id, left_at, role FROM conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
    $check->bind_param('ii', $conversation_id, $user_id);
    $check->execute();
    $res = $check->get_result();
    if (!$row = $res->fetch_assoc()) {
        $check->close();
        flush_and_exit(['success' => false, 'message' => 'Bạn không phải thành viên của cuộc trò chuyện này'], 404);
    }
    if (!is_null($row['left_at']) && $row['left_at'] !== '') {
        $check->close();
        flush_and_exit(['success' => false, 'message' => 'Bạn đã rời cuộc trò chuyện này trước đó'], 400);
    }
    $role = $row['role'];
    $check->close();

    // mark left_at for this participant
    $stmt = $conn->prepare("UPDATE conversation_participants SET left_at = NOW() WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
    $stmt->bind_param('ii', $conversation_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        flush_and_exit(['success' => false, 'message' => 'Không thể rời nhóm (đã rời hoặc lỗi)'], 500);
    }

    // update conversations.updated_at to reflect change
    $u = $conn->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $u->bind_param('i', $conversation_id);
    $u->execute();
    $u->close();

    // optional: if user was admin and no admins left, promote another member to admin
    if ($role === 'admin') {
        $promote = $conn->prepare("SELECT id, user_id FROM conversation_participants WHERE conversation_id = ? AND left_at IS NULL ORDER BY joined_at ASC LIMIT 1");
        $promote->bind_param('i', $conversation_id);
        $promote->execute();
        $pr = $promote->get_result();
        if ($p = $pr->fetch_assoc()) {
            // if there is at least one remaining participant, set role admin for first one
            $promote->close();
            $promoteStmt = $conn->prepare("UPDATE conversation_participants SET role = 'admin' WHERE conversation_id = ? AND user_id = ?");
            $promoteStmt->bind_param('ii', $conversation_id, $p['user_id']);
            $promoteStmt->execute();
            $promoteStmt->close();
        } else {
            $promote->close();
            // no participants left -> optionally delete conversation (skip by default)
            // $del = $conn->prepare("DELETE FROM conversations WHERE id = ?");
            // $del->bind_param('i', $conversation_id);
            // $del->execute();
            // $del->close();
        }
    }

    flush_and_exit(['success' => true, 'message' => 'Rời nhóm thành công']);
} catch (Exception $e) {
    error_log('[leave.php] Error: ' . $e->getMessage());
    flush_and_exit(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()], 500);
}
?>