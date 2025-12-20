<?php

require_once __DIR__ . '/../core/Model.php';

class SellerModel extends Model
{
    protected $table = 'seller';

    public function getSellers($start, $length, $search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(s.store_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR u.name LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "s.status = ?";
            $params[] = $filters['status'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "SELECT s.*, u.name as owner_name, u.email as owner_email 
                FROM {$this->table} s 
                LEFT JOIN users u ON s.user_id = u.id 
                $where_clause 
                ORDER BY s.created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;

        return $this->fetchAll($sql, $params);
    }

    public function getSellersCount($search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(s.store_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR u.name LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "s.status = ?";
            $params[] = $filters['status'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $sql = "SELECT COUNT(*) as total 
                FROM {$this->table} s 
                LEFT JOIN users u ON s.user_id = u.id 
                $where_clause";
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }

    public function getSellerById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE seller_id = ?";
        return $this->fetchOne($sql, [$id]);
    }

    public function updateStatus($sellerId, $status)
    {
        $sql = "UPDATE {$this->table} SET status = ? WHERE seller_id = ?";
        $stmt = $this->query($sql, [$status, $sellerId]);
        return $stmt !== false;
    }

    public function getStats()
    {
        $total_sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $approved_sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'approved'";
        $pending_sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'pending'";
        $blocked_sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'blocked'";
        
        $total = $this->fetchOne($total_sql);
        $approved = $this->fetchOne($approved_sql);
        $pending = $this->fetchOne($pending_sql);
        $blocked = $this->fetchOne($blocked_sql);
        
        return [
            'total' => $total ? $total['count'] : 0,
            'approved' => $approved ? $approved['count'] : 0,
            'pending' => $pending ? $pending['count'] : 0,
            'blocked' => $blocked ? $blocked['count'] : 0
        ];
    }

    public function updateSeller($sellerId, $data)
    {
        $set_clauses = [];
        $params = [];

        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $sellerId;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set_clauses) . " WHERE seller_id = ?";
        $stmt = $this->query($sql, $params);
        return $stmt !== false;
    }

    public function getTotalRevenue($sellerId)
    {
        $sql = "SELECT COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue 
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                INNER JOIN products p ON oi.product_id = p.id
                WHERE p.seller_id = ? AND o.status = 'delivered'";
        $result = $this->fetchOne($sql, [$sellerId]);
        return $result ? (float)$result['total_revenue'] : 0;
    }

    public function getSellerStats($sellerId)
    {
        // Total products
        $products_sql = "SELECT COUNT(*) as count FROM products WHERE seller_id = ?";
        $products = $this->fetchOne($products_sql, [$sellerId]);
        $total_products = $products ? $products['count'] : 0;

        // Total orders
        $orders_sql = "SELECT COUNT(DISTINCT o.id) as count 
                      FROM orders o
                      INNER JOIN order_items oi ON o.id = oi.order_id
                      INNER JOIN products p ON oi.product_id = p.id
                      WHERE p.seller_id = ?";
        $orders = $this->fetchOne($orders_sql, [$sellerId]);
        $total_orders = $orders ? $orders['count'] : 0;

        // Revenue
        $revenue = $this->getTotalRevenue($sellerId);

        // Orders by status
        $status_sql = "SELECT o.status, COUNT(DISTINCT o.id) as count 
                      FROM orders o
                      INNER JOIN order_items oi ON o.id = oi.order_id
                      INNER JOIN products p ON oi.product_id = p.id
                      WHERE p.seller_id = ?
                      GROUP BY o.status";
        $orders_by_status = $this->fetchAll($status_sql, [$sellerId]);

        return [
            'total_products' => $total_products,
            'total_orders' => $total_orders,
            'total_revenue' => $revenue,
            'orders_by_status' => $orders_by_status
        ];
    }

    public function getRevenueByMonth($sellerId, $months = 6)
    {
        $sql = "SELECT 
                    DATE_FORMAT(o.created_at, '%Y-%m') as month,
                    COALESCE(SUM(oi.price * oi.quantity), 0) as revenue,
                    COUNT(DISTINCT o.id) as orders_count
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                INNER JOIN products p ON oi.product_id = p.id
                WHERE p.seller_id = ? 
                AND o.status = 'delivered'
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
                ORDER BY month ASC";
        return $this->fetchAll($sql, [$sellerId, $months]);
    }

    public function getTopProducts($sellerId, $limit = 5)
    {
        $sql = "SELECT 
                    p.id,
                    p.name,
                    p.price,
                    p.image,
                    COALESCE(SUM(oi.quantity), 0) as total_sold,
                    COALESCE(SUM(oi.price * oi.quantity), 0) as revenue
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
                WHERE p.seller_id = ?
                GROUP BY p.id
                ORDER BY total_sold DESC
                LIMIT ?";
        return $this->fetchAll($sql, [$sellerId, $limit]);
    }
}
