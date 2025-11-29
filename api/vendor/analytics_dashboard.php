<?php
// Tắt hiển thị lỗi ra màn hình, chỉ log vào file
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set headers trước tiên
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once '../../config/database.php';

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    if (!$user_id) {
        throw new Exception('Thiếu user_id');
    }

    // Kiểm tra kết nối database
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Không thể kết nối database');
    }

    // Lấy seller_id từ user_id
    $stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare seller query error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Execute seller query error: ' . $stmt->error);
    }
    $stmt->bind_result($seller_id);
    
    if (!$stmt->fetch()) {
        $stmt->close();
        throw new Exception('Không tìm thấy seller');
    }
    $stmt->close();

    // ============= 1. DOANH THU THEO THÁNG =============
    $sql_revenue = "SELECT 
        MONTH(o.created_at) as month,
        COUNT(DISTINCT o.id) as orders,
        COUNT(DISTINCT o.customer_id) as customers,
        COALESCE(SUM(oi.price * oi.quantity), 0) as revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? 
      AND YEAR(o.created_at) = ?
      AND o.status = 'delivered'
    GROUP BY MONTH(o.created_at)
    ORDER BY MONTH(o.created_at)";

    $stmt = $conn->prepare($sql_revenue);
    if (!$stmt) {
        throw new Exception('Revenue query prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $seller_id, $year);
    if (!$stmt->execute()) {
        throw new Exception('Revenue query execute error: ' . $stmt->error);
    }
    $result = $stmt->get_result();

    $monthly_data = [];
    for ($i = 1; $i <= 12; $i++) {
        $monthly_data[$i] = [
            'month' => "T" . $i,
            'revenue' => 0,
            'orders' => 0,
            'customers' => 0
        ];
    }

    while ($row = $result->fetch_assoc()) {
        $month = (int)$row['month'];
        if ($month >= 1 && $month <= 12) {
            $monthly_data[$month] = [
                'month' => "T" . $month,
                'revenue' => round((float)$row['revenue'] / 1000000, 1),
                'orders' => (int)$row['orders'],
                'customers' => (int)$row['customers']
            ];
        }
    }
    $stmt->close();

    $revenue_data = array_values($monthly_data);

    // ============= 2. DOANH SỐ THEO DANH MỤC =============
    $sql_category = "SELECT 
        p.category,
        COUNT(DISTINCT oi.order_id) as order_count,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.price * oi.quantity) as total_revenue
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? 
      AND YEAR(o.created_at) = ?
      AND o.status = 'delivered'
    GROUP BY p.category
    ORDER BY total_revenue DESC
    LIMIT 5";

    $stmt = $conn->prepare($sql_category);
    if (!$stmt) {
        throw new Exception('Category query prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $seller_id, $year);
    if (!$stmt->execute()) {
        throw new Exception('Category query execute error: ' . $stmt->error);
    }
    $result = $stmt->get_result();

    $category_data = [];
    $total_category_revenue = 0;
    $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
    $color_index = 0;

    while ($row = $result->fetch_assoc()) {
        $revenue = (float)$row['total_revenue'];
        $total_category_revenue += $revenue;
        $category_data[] = [
            'name' => $row['category'] ?? 'Khác',
            'revenue' => $revenue,
            'color' => $colors[$color_index % count($colors)]
        ];
        $color_index++;
    }
    $stmt->close();

    // Calculate percentages
    foreach ($category_data as &$cat) {
        $cat['value'] = $total_category_revenue > 0 
            ? round(($cat['revenue'] / $total_category_revenue) * 100, 1) 
            : 0;
        unset($cat['revenue']);
    }

    // ============= 3. TRẠNG THÁI ĐƠN HÀNG =============
    $sql_status = "SELECT 
        o.status,
        COUNT(DISTINCT o.id) as count
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = ?
      AND YEAR(o.created_at) = ?
    GROUP BY o.status";

    $stmt = $conn->prepare($sql_status);
    if (!$stmt) {
        throw new Exception('Status query prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $seller_id, $year);
    if (!$stmt->execute()) {
        throw new Exception('Status query execute error: ' . $stmt->error);
    }
    $result = $stmt->get_result();

    $status_colors = [
        'pending' => '#f59e0b',
        'processing' => '#3b82f6',
        'shipped' => '#8b5cf6',
        'delivered' => '#10b981',
        'cancelled' => '#ef4444'
    ];

    $status_labels = [
        'pending' => 'Chờ xử lý',
        'processing' => 'Đang xử lý',
        'shipped' => 'Đang giao',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy'
    ];

    $order_status_data = [];
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        $order_status_data[] = [
            'status' => $status_labels[$status] ?? $status,
            'count' => (int)$row['count'],
            'color' => $status_colors[$status] ?? '#6b7280'
        ];
    }
    $stmt->close();

    // ============= 4. XU HƯỚNG TUẦN NÀY =============
    $sql_weekly = "SELECT 
        DAYOFWEEK(o.created_at) as day_of_week,
        COUNT(DISTINCT o.id) as orders,
        COALESCE(SUM(oi.price * oi.quantity), 0) as revenue
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = ?
      AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      AND o.status IN ('delivered', 'shipped', 'processing')
    GROUP BY DAYOFWEEK(o.created_at)
    ORDER BY DAYOFWEEK(o.created_at)";

    $stmt = $conn->prepare($sql_weekly);
    if (!$stmt) {
        throw new Exception('Weekly query prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $seller_id);
    if (!$stmt->execute()) {
        throw new Exception('Weekly query execute error: ' . $stmt->error);
    }
    $result = $stmt->get_result();

    $day_names = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
    $weekly_temp = [];
    for ($i = 1; $i <= 7; $i++) {
        $weekly_temp[$i] = ['orders' => 0, 'revenue' => 0];
    }

    while ($row = $result->fetch_assoc()) {
        $day = (int)$row['day_of_week'];
        if ($day >= 1 && $day <= 7) {
            $weekly_temp[$day] = [
                'orders' => (int)$row['orders'],
                'revenue' => round((float)$row['revenue'] / 1000000, 1)
            ];
        }
    }
    $stmt->close();

    $weekly_data = [];
    foreach ($weekly_temp as $day => $data) {
        $weekly_data[] = [
            'day' => $day_names[$day - 1],
            'orders' => $data['orders'],
            'revenue' => $data['revenue']
        ];
    }

    // ============= 5. TỔNG QUAN =============
    $sql_overview = "SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COUNT(DISTINCT o.customer_id) as total_customers,
        COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue,
        COALESCE(AVG(oi.price * oi.quantity), 0) as avg_order_value
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = ?
      AND YEAR(o.created_at) = ?
      AND o.status = 'delivered'";

    $stmt = $conn->prepare($sql_overview);
    if (!$stmt) {
        throw new Exception('Overview query prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $seller_id, $year);
    if (!$stmt->execute()) {
        throw new Exception('Overview query execute error: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $overview = $result->fetch_assoc();
    $stmt->close();

    // Response
    $response = [
        'success' => true,
        'year' => $year,
        'seller_id' => $seller_id,
        'overview' => [
            'total_orders' => (int)($overview['total_orders'] ?? 0),
            'total_customers' => (int)($overview['total_customers'] ?? 0),
            'total_revenue' => round((float)($overview['total_revenue'] ?? 0), 0),
            'avg_order_value' => round((float)($overview['avg_order_value'] ?? 0), 0)
        ],
        'revenue_data' => $revenue_data,
        'category_data' => $category_data,
        'order_status_data' => $order_status_data,
        'weekly_trends' => $weekly_data
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}