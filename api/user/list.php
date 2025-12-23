<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

int_headers();
// normalize headers and fallback to $_SERVER if Apache strips Authorization
$rawHeaders = function_exists('getallheaders') ? getallheaders() : [];
$headers = [];
foreach ($rawHeaders as $k => $v) {
    $headers[str_replace(' ', '-', ucwords(str_replace('-', ' ', strtolower($k))))] = $v;
}
// fallback variants
if (empty($headers) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (!isset($headers['Authorization'])) {
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
}

// debug helper: ?debug=1 => return headers and sample users for troubleshooting
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    header('Content-Type: application/json');
    $sample = [];
    $q = $conn->query("SELECT id, name, status FROM users LIMIT 5");
    if ($q) {
        while ($r = $q->fetch_assoc()) $sample[] = $r;
    }
    echo json_encode([
        'headers' => $headers,
        'server_authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null),
        'query' => $_GET,
        'db_sample' => $sample
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ']);
    exit();
}

// Xác thực JWT
// $headers variable prepared above
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Thiếu token']);
    exit;
}

if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
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
    echo json_encode(['success' => false, 'message' => 'Token hết hạn hoặc không hợp lệ']);
    exit;
}

try {
    $sql = "SELECT 
                id,
                name,
                avatar
            FROM users WHERE id != ? ORDER BY name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Normalize avatar url
        if (!function_exists('build_base_url')) {
            function build_base_url() {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $script = $_SERVER['SCRIPT_NAME'] ?? '';
                if (false !== ($pos = strpos($script, '/api/'))) {
                    $projectBase = substr($script, 0, $pos);
                } else {
                    $projectBase = dirname(dirname(dirname($script)));
                }
                $projectBase = rtrim($projectBase, '/');
                return $protocol . '://' . $host . ($projectBase ? $projectBase : '');
            }
        }

        if (!function_exists('normalize_avatar_url')) {
            function normalize_avatar_url($avatar) {
                $avatar = trim((string)($avatar ?? ''));
                if ($avatar === '') return '';
                if (stripos($avatar, 'data:') === 0) return $avatar;

                // scheme-relative
                if (strpos($avatar, '//') === 0) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:';
                    return $protocol . $avatar;
                }

                if (preg_match('#^https?://#i', $avatar)) return $avatar;

                $lower = strtolower($avatar);
                if (strpos($lower, 'googleusercontent.com') !== false || strpos($lower, 'lh3.googleusercontent.com') !== false || strpos($lower, 'zalo') !== false) {
                    return (stripos($avatar, 'http') === 0) ? $avatar : 'https://' . ltrim($avatar, '/');
                }

                $avatar = ltrim($avatar, '/');
                return rtrim(build_base_url(), '/') . '/' . $avatar;
            }
        }

        $users[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'avatar' => normalize_avatar_url($row['avatar']),
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $users,
        'total_users' => count($users)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi server: ' . $e->getMessage()
    ]);
}

$conn->close();
