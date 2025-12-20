<?php

require_once __DIR__ . '/../core/Model.php';

class OrderModel extends Model
{
    protected $table = 'orders';

    public function getOrders($start, $length, $search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(code LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['payment_method'])) {
            $where_conditions[] = "payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "SELECT * FROM {$this->table} $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;

        return $this->fetchAll($sql, $params);
    }

    public function getOrdersCount($search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(code LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['payment_method'])) {
            $where_conditions[] = "payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $sql = "SELECT COUNT(*) as total FROM {$this->table} $where_clause";
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }

    public function getOrderById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->fetchOne($sql, [$id]);
    }

    public function updateStatus($orderId, $status)
    {
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->query($sql, [$status, $orderId]);
        return $stmt !== false;
    }

    public function getOrderItems($orderId)
    {
        $sql = "SELECT oi.*, p.name, p.image 
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?";
        return $this->fetchAll($sql, [$orderId]);
    }
}
