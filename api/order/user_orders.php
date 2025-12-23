<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// Giả sử bạn nhận user_id qua GET hoặc từ session/token (nên dùng token thực tế)
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
    exit;
}

$sql = "SELECT o.id, o.code, o.status, o.created_at as date, o.total, o.note
        FROM orders o
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$orders = [];
while ($row = $res->fetch_assoc()) {
    // Lấy sản phẩm trong đơn
    $items = [];
    $itemRes = $conn->query("SELECT p.name, oi.price, oi.quantity, p.image 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = {$row['id']}");
    while ($item = $itemRes->fetch_assoc()) {
        // Xử lý field image: có thể là JSON array, chuỗi phân tách bởi dấu phẩy, hoặc đường dẫn đơn
        $raw = $item['image'];
        $img = '';

        if ($raw) {
            $trim = trim($raw);
            // JSON array hoặc object
            if (isset($trim[0]) && ($trim[0] === '[' || $trim[0] === '{')) {
                $arr = json_decode($trim, true);
                if (is_array($arr) && count($arr) > 0) {
                    // lấy phần tử đầu tiên nếu là danh sách
                    $first = reset($arr);
                    if (is_string($first) && $first !== '') $img = $first;
                }
            }
            // Nếu chưa có ảnh, thử tách bằng dấu phẩy (CSV)
            if ($img === '' && strpos($trim, ',') !== false) {
                $parts = explode(',', $trim);
                $candidate = trim($parts[0], " \"'\\");
                if ($candidate !== '') $img = $candidate;
            }
            // Nếu vẫn chưa, dùng nguyên giá trị (có thể là đường dẫn đơn)
            if ($img === '') {
                $img = trim($trim, " \"'\\");
            }
        }

        // Build absolute URL using current host and project base (handles dev folder like /VibeMarket-BE/)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Derive project base from script path (e.g. /VibeMarket-BE/)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']); // e.g. /VibeMarket-BE/api/order
        $projectBaseDir = dirname(dirname($scriptDir)); // e.g. /VibeMarket-BE
        if ($projectBaseDir === DIRECTORY_SEPARATOR) $projectBaseDir = '/';
        $base = $protocol . $host . rtrim($projectBaseDir, '/') . '/';

        if (!$img) {
            $item['image'] = $base . 'placeholder.svg';
        } else {
            $item['image'] = (stripos($img, 'http') === 0) ? $img : $base . ltrim($img, '/');
        }
        $items[] = $item;
    }
    $orders[] = [
        'id' => $row['id'],
        'code' => $row['code'],
        'status' => $row['status'],
        'date' => $row['date'],
        'total' => (int)$row['total'],
        'note' => $row['note'],
        'items' => $items
    ];
}
echo json_encode(['success' => true, 'orders' => $orders]);
$conn->close();