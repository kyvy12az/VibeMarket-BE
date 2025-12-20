<?php

require_once __DIR__ . '/../core/Model.php';

class ProductModel extends Model
{
    protected $table = 'products';

    public function getProducts($start, $length, $search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.seller_name LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }

        if (!empty($filters['category'])) {
            $where_conditions[] = "p.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['vendor'])) {
            $where_conditions[] = "p.seller_id = ?";
            $params[] = intval($filters['vendor']);
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "SELECT p.*, s.store_name, s.avatar AS seller_avatar
                FROM {$this->table} p
                LEFT JOIN seller s ON p.seller_id = s.seller_id
                $where_clause
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $length;
        $params[] = $start;

        return $this->fetchAll($sql, $params);
    }

    public function getProductsCount($search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.seller_name LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }

        if (!empty($filters['category'])) {
            $where_conditions[] = "p.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['vendor'])) {
            $where_conditions[] = "p.seller_id = ?";
            $params[] = intval($filters['vendor']);
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $sql = "SELECT COUNT(*) as total FROM {$this->table} p $where_clause";
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }

    public function getProductById($id)
    {
        $sql = "SELECT p.*, s.store_name, s.avatar AS seller_avatar
                FROM {$this->table} p
                LEFT JOIN seller s ON p.seller_id = s.seller_id
                WHERE p.id = ?";
        return $this->fetchOne($sql, [$id]);
    }

    public function updateStatus($productId, $status)
    {
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->query($sql, [$status, $productId]);
        return $stmt !== false;
    }

    public function deleteProduct($productId)
    {
        // Soft delete by setting status to 'deleted'
        $sql = "UPDATE {$this->table} SET status = 'deleted' WHERE id = ?";
        $stmt = $this->query($sql, [$productId]);
        return $stmt !== false;
    }

    public function updateVisibility($productId, $visibility)
    {
        $sql = "UPDATE {$this->table} SET visibility = ? WHERE id = ?";
        $stmt = $this->query($sql, [$visibility, $productId]);
        return $stmt !== false;
    }

    public function updateProduct($productId, $data)
    {
        $set_clauses = [];
        $params = [];

        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $productId;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $stmt = $this->query($sql, $params);
        return $stmt !== false;
    }

    public function getCategories()
    {
        $sql = "SELECT DISTINCT category FROM {$this->table} WHERE category IS NOT NULL ORDER BY category";
        return $this->fetchAll($sql);
    }

    public function getVendors()
    {
        $sql = "SELECT DISTINCT seller_id, seller_name FROM {$this->table} WHERE seller_id IS NOT NULL ORDER BY seller_name";
        return $this->fetchAll($sql);
    }
}
