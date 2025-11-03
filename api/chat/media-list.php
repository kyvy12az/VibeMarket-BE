<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ob_start();

require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function flush_and_exit_html($html, $code = 200) {
    $extra = trim(ob_get_clean());
    if (!empty($extra)) error_log("[media-list.php] Unexpected output: " . $extra);
    http_response_code($code);
    echo $html;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    flush_and_exit_html('<h1>405 Method Not Allowed</h1>', 405);
}

$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
$offset = ($page - 1) * $limit;

if ($conversation_id <= 0) {
    flush_and_exit_html('<h1>Invalid conversation_id</h1>', 400);
}

// Auth (same as media.php)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = '';
if (isset($headers['Authorization'])) $authHeader = $headers['Authorization'];
elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    flush_and_exit_html('<h1>Unauthorized</h1>', 401);
}
$jwt = $matches[1];

$jwt_secret_candidates = [];
if (isset($JWT_SECRET)) $jwt_secret_candidates[] = $JWT_SECRET;
if (isset($SECRET_KEY)) $jwt_secret_candidates[] = $SECRET_KEY;
if (defined('JWT_SECRET') && JWT_SECRET) $jwt_secret_candidates[] = JWT_SECRET;
$jwt_secret = count($jwt_secret_candidates) ? $jwt_secret_candidates[0] : null;
if (!$jwt_secret) {
    error_log('[media-list.php] JWT secret not found');
    flush_and_exit_html('<h1>Server configuration error</h1>', 500);
}

try {
    $decoded = JWT::decode($jwt, new Key($jwt_secret, 'HS256'));
    $user_id = $decoded->sub ?? $decoded->user_id ?? $decoded->id ?? null;
    if (!$user_id) throw new Exception('Invalid token payload');
} catch (Exception $e) {
    flush_and_exit_html('<h1>Unauthorized: ' . htmlspecialchars($e->getMessage()) . '</h1>', 401);
}

if (!isset($conn)) {
    flush_and_exit_html('<h1>Database not available</h1>', 500);
}

try {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM messages WHERE conversation_id = ? AND is_deleted = 0 AND (type IN ('image','video','file') OR (file_url IS NOT NULL AND file_url <> ''))");
    $countStmt->bind_param('i', $conversation_id);
    $countStmt->execute();
    $cntRes = $countStmt->get_result()->fetch_assoc();
    $total = intval($cntRes['total'] ?? 0);
    $countStmt->close();

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
        $items[] = $row;
    }
    $stmt->close();

    // Build HTML
    $base = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], '/');
    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Media list</title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:16px}
            .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}
            .card{background:#fff;border:1px solid #e6e9ee;padding:8px;border-radius:8px;overflow:hidden}
            .thumb{width:100%;height:120px;object-fit:cover;background:#000}
            .meta{font-size:13px;margin-top:8px;display:flex;justify-content:space-between;align-items:center}
            a{color:#0ea5e9;text-decoration:none}
            .file-icon{display:flex;flex-direction:column;align-items:center;justify-content:center;height:120px}
            .nav{margin-bottom:12px}
        </style></head><body>';
    $html .= '<div class="nav"><a href="javascript:history.back()">« Back</a> &nbsp;|&nbsp; Total: ' . intval($total) . '</div>';
    if (count($items) === 0) {
        $html .= '<p>No media found.</p>';
    } else {
        $html .= '<div class="grid">';
        foreach ($items as $it) {
            $id = intval($it['id']);
            $type = $it['type'] ?? '';
            $file_url = $it['file_url'] ?? '';
            $name = $it['content'] ? htmlspecialchars($it['content']) : htmlspecialchars(basename($file_url));
            $size = null;
            $created = $it['created_at'] ?? '';
            $file_href = htmlspecialchars($base . '/' . ltrim($file_url, '/'));

            $html .= '<div class="card">';
            if ($type === 'image' || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file_url)) {
                $html .= '<a href="' . $file_href . '" target="_blank"><img class="thumb" src="' . $file_href . '" alt="' . $name . '"></a>';
            } elseif ($type === 'video' || preg_match('/\.(mp4|webm|ogg)$/i', $file_url)) {
                $html .= '<video class="thumb" controls src="' . $file_href . '"></video>';
            } else {
                $html .= '<div class="file-icon"><strong>' . $name . '</strong></div>';
            }
            $html .= '<div class="meta"><div><div style="font-size:13px">' . $name . '</div><div style="color:#64748b;font-size:12px">' . htmlspecialchars($created) . '</div></div>';
            $html .= '<div><a href="' . $file_href . '" download>Download</a></div></div>';
            if ($size) {
                $html .= '<div style="font-size:12px;color:#64748b;margin-top:6px">'.round($size/1024,1).' KB</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    $pages = max(1, ceil($total / $limit));
    if ($pages > 1) {
        $html .= '<div style="margin-top:16px">';
        for ($i = 1; $i <= $pages; $i++) {
            if ($i == $page) {
                $html .= " <strong>$i</strong> ";
            } else {
                $html .= ' <a href="?conversation_id=' . $conversation_id . '&page=' . $i . '&limit=' . $limit . '">' . $i . '</a> ';
            }
        }
        $html .= '</div>';
    }

    $html .= '</body></html>';
    flush_and_exit_html($html, 200);

} catch (Exception $e) {
    error_log('[media-list.php] Error: ' . $e->getMessage());
    flush_and_exit_html('<h1>Server error</h1>', 500);
}
?>