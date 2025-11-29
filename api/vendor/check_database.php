<?php
header("Content-Type: application/json; charset=utf-8");
require_once '../../config/database.php';

try {
    // Kiểm tra bảng orders
    $result = $conn->query("SHOW TABLES LIKE 'orders'");
    $orders_exists = $result->num_rows > 0;
    
    // Kiểm tra bảng order_items
    $result = $conn->query("SHOW TABLES LIKE 'order_items'");
    $order_items_exists = $result->num_rows > 0;
    
    $tables_info = [];
    
    if ($orders_exists) {
        $result = $conn->query("DESCRIBE orders");
        $tables_info['orders'] = [];
        while ($row = $result->fetch_assoc()) {
            $tables_info['orders'][] = $row['Field'];
        }
    }
    
    if ($order_items_exists) {
        $result = $conn->query("DESCRIBE order_items");
        $tables_info['order_items'] = [];
        while ($row = $result->fetch_assoc()) {
            $tables_info['order_items'][] = $row['Field'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders_exists' => $orders_exists,
        'order_items_exists' => $order_items_exists,
        'tables_info' => $tables_info
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}