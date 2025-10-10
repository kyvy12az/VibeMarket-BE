<?php
// Database config
// $host = 'sql207.infinityfree.com';
// $db   = 'if0_40114539_vibemarket_db';
// $user = 'if0_40114539';
// $pass = 'kyvy19022006';
// $charset = 'utf8mb4';

// $host = 'localhost';
// $db   = 'vibemarket_db';
// $user = 'root';
// $pass = '';
// $charset = 'utf8mb4';

$host = 'localhost';
$db   = 'ha6a3b453c_vibemarket_db';
$user = 'ha6a3b453c_vibemarket_db';
$pass = 'ha6a3b453c_vibemarket_db';
$charset = 'utf8mb4';

// Dev/prod config
$isDev = (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '.test') !== false));
// $nodejs_server = $isDev ? "https://localhost:3000" : "https://vku-greenmap-nodejs-services.onrender.com";

// MySQLi connection
$conn = @new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Kết nối CSDL thất bại: ' . $conn->connect_error]));
}
$conn->set_charset($charset);

// Domain URL
$domain_url = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($domain_url, 'localhost') !== false) {
    $domain_url = 'http://' . $domain_url;
} else {
    $domain_url = 'https://' . $domain_url;
}

// Map GHN order status to Vietnamese
function GHNOrderStatus($status) {
    $statusMap = [
        'ready_to_pick' => 'Chờ lấy hàng',
        'picking' => 'Đang lấy hàng',
        'picked' => 'Đã lấy hàng',
        'storing' => 'Đang lưu kho',
        'transporting' => 'Đang vận chuyển',
        'sorting' => 'Đang phân loại',
        'delivering' => 'Đang giao hàng',
        'delivered' => 'Đã giao hàng',
        'delivery_fail' => 'Giao hàng thất bại',
        'returning' => 'Đang trả hàng',
        'returned' => 'Đã trả hàng',
        'cancelled' => 'Đã hủy',
        'exception' => 'Ngoại lệ',
        'damage' => 'Hư hỏng',
        'lost' => 'Thất lạc',
        'pending' => 'Chờ xử lý'
    ];
    return $statusMap[$status] ?? 'Không xác định';
}

// Set API headers (CORS, security, etc.)
function int_headers() {
    global $isDev;
    $env = $isDev ? 'development' : 'production';

    if (ob_get_length()) ob_end_clean();
    header_remove();

    // Base headers
    header("Content-Type: application/json; charset=UTF-8");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: no-referrer");
    header("Permissions-Policy: geolocation=(), microphone=()");
    header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");

    // CORS
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://greenmap.talentvku.id.vn',
        'https://vku-greenmap-nodejs-services.onrender.com',
        'https://vibe-market-mu.vercel.app',
        'https://vibemarket.infinityfreeapp.com',
        'https://komer.id.vn',
    ];

    if ($env === 'development') {
        header("Access-Control-Allow-Origin: *");
    } elseif (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
