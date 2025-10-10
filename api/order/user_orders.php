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
        // Nếu image là JSON array, lấy ảnh đầu tiên
        $img = $item['image'];
        if ($img && ($img[0] === '[' || $img[0] === '{')) {
            $arr = json_decode($img, true);
            if (is_array($arr) && count($arr) > 0) {
                $img = $arr[0];
            }
        }
        // Đảm bảo trả về ảnh đầy đủ domain
        $item['image'] = (strpos($img, 'http') === 0) ? $img : 'http://localhost/' . ltrim($img, '/');
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