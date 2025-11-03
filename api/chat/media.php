<?php
// Trả JSON sạch
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (function_exists('int_headers')) {
    int_headers();
}

function flush_extra_output_and_exit($payload, $httpCode = 200) {
    $extra = trim(ob_get_clean());
    if (!empty($extra)) {
        error_log("[media.php] Unexpected output before JSON: " . $extra);
    }
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    flush_extra_output_and_exit(['success' => false, 'message' => 'Chỉ hỗ trợ GET'], 405);
}

$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * max(0, $limit);

if ($conversation_id <= 0) {
    flush_extra_output_and_exit(['success' => false, 'message' => 'conversation_id không hợp lệ'], 400);
}

// --- Authenticate JWT (same as before) ---
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = '';
if (isset($headers['Authorization'])) $authHeader = $headers['Authorization'];
elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    flush_extra_output_and_exit(['success' => false, 'message' => 'Unauthorized'], 401);
}
$jwt = $matches[1];

$jwt_secret_candidates = [];
if (isset($JWT_SECRET)) $jwt_secret_candidates[] = $JWT_SECRET;
if (isset($SECRET_KEY)) $jwt_secret_candidates[] = $SECRET_KEY;
if (defined('JWT_SECRET') && JWT_SECRET) $jwt_secret_candidates[] = JWT_SECRET;
$jwt_secret = count($jwt_secret_candidates) ? $jwt_secret_candidates[0] : null;
if (!$jwt_secret) {
    error_log('[media.php] JWT secret not found in config/jwt.php');
    flush_extra_output_and_exit(['success' => false, 'message' => 'Server JWT configuration error'], 500);
}

try {
    $decoded = JWT::decode($jwt, new Key($jwt_secret, 'HS256'));
    $user_id = $decoded->sub ?? $decoded->user_id ?? $decoded->id ?? null;
    if (!$user_id) throw new Exception('Invalid token payload');
} catch (Exception $e) {
    flush_extra_output_and_exit(['success' => false, 'message' => 'Unauthorized: ' . $e->getMessage()], 401);
}

if (!isset($conn)) {
    flush_extra_output_and_exit(['success' => false, 'message' => 'Database connection not available'], 500);
}

try {
    // Count total (only non-deleted)
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE conversation_id = ? AND is_deleted = 0 AND (type IN ('image','video','file') OR (file_url IS NOT NULL AND file_url <> ''))");
    $countStmt->bind_param('i', $conversation_id);
    $countStmt->execute();
    $cntRes = $countStmt->get_result()->fetch_assoc();
    $total = intval($cntRes['total'] ?? 0);
    $countStmt->close();

    // Select media items (most recent first) — select only existing columns
    $stmt = $conn->prepare("
        SELECT id, `type`, file_url, content, sender_id, created_at
        FROM messages
        WHERE conversation_id = ? AND is_deleted = 0 AND (type IN ('image','video','file') OR (file_url IS NOT NULL AND file_url <> ''))
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $conversation_id, $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $fileUrl = $row['file_url'] ?? '';
        $items[] = [
            'id' => intval($row['id']),
            'type' => $row['type'] ?? (preg_match('/\.(mp4|webm|ogg)$/i', $fileUrl) ? 'video' : (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileUrl) ? 'image' : 'file')),
            'file_url' => $fileUrl,
            // original_name: try content field or basename(file_url)
            'original_name' => !empty($row['content']) ? $row['content'] : ($fileUrl ? basename($fileUrl) : null),
            'thumbnail_url' => null, // not available in schema
            'file_size' => null, // optional, not in schema
            'sender_id' => isset($row['sender_id']) ? intval($row['sender_id']) : null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
    $stmt->close();

    $payload = [
        'success' => true,
        'data' => $items,
        'meta' => [
            'conversation_id' => $conversation_id,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ],
    ];
    flush_extra_output_and_exit($payload, 200);
} catch (Exception $e) {
    error_log('[media.php] DB error: ' . $e->getMessage());
    flush_extra_output_and_exit(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()], 500);
}