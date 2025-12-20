<?php

require_once __DIR__ . '/../core/Model.php';

class UserModel extends Model
{
    protected $table = 'users';

    public function getUsers($start, $length, $search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }

        if (!empty($filters['role'])) {
            $where_conditions[] = "role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT * FROM {$this->table} $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;

        return $this->fetchAll($sql, $params);
    }

    public function getUsersCount($search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }

        if (!empty($filters['role'])) {
            $where_conditions[] = "role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $sql = "SELECT COUNT(*) as total FROM {$this->table} $where_clause";
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }

    public function updateStatus($userId, $status)
    {
        return $this->update($userId, ['status' => $status]);
    }

    public function getUserById($id)
    {
        return $this->findById($id);
    }

    public function getStats()
    {
        return [
            'total' => $this->count(),
            'active' => $this->count("status = 'active'"),
            'vendors' => $this->count("role = 'seller'")
        ];
    }

    public function updateUser($userId, $data)
    {
        return $this->update($userId, $data);
    }

    public function emailExists($email, $excludeUserId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE email = ?";
        $params = [$email];

        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $result = $this->fetchOne($sql, $params);
        return !empty($result);
    }

    public function getUserDetails($userId)
    {
        // Order statistics
        $order_stats_sql = "SELECT 
            COUNT(o.id) as total_orders,
            COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN o.status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
            COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.total ELSE 0 END), 0) as total_spent
        FROM orders o
        WHERE o.customer_id = ?";
        $order_stats = $this->fetchOne($order_stats_sql, [$userId]);

        // Recent orders
        $recent_orders_sql = "SELECT o.id, o.code, o.status, o.total, o.created_at,
            DATE_FORMAT(o.created_at, '%d/%m/%Y %H:%i') as formatted_date
        FROM orders o
        WHERE o.customer_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 10";
        $recent_orders = $this->fetchAll($recent_orders_sql, [$userId]);

        // Monthly activity (last 12 months)
        $monthly_activity_sql = "SELECT 
            DATE_FORMAT(created_at, '%m/%Y') as month,
            DATE_FORMAT(created_at, '%Y-%m') as sort_month,
            COUNT(*) as order_count,
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as revenue
        FROM orders
        WHERE customer_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY sort_month DESC";
        $monthly_activity = $this->fetchAll($monthly_activity_sql, [$userId]);

        // User posts
        $posts_sql = "SELECT p.id, p.content, p.status, p.created_at,
            DATE_FORMAT(p.created_at, '%d/%m/%Y %H:%i') as formatted_date,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comments_count
        FROM posts p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 20";
        $posts = $this->fetchAll($posts_sql, [$userId]);

        // Network statistics
        $followers_sql = "SELECT COUNT(*) as count FROM user_followers WHERE following_id = ?";
        $following_sql = "SELECT COUNT(*) as count FROM user_followers WHERE follower_id = ?";
        
        $followers_result = $this->fetchOne($followers_sql, [$userId]);
        $following_result = $this->fetchOne($following_sql, [$userId]);
        
        $network_stats = [
            'followers' => $followers_result ? $followers_result['count'] : 0,
            'following' => $following_result ? $following_result['count'] : 0
        ];

        // Followers list
        $followers_list_sql = "SELECT u.id, u.name, u.email, u.avatar, uf.created_at,
            DATE_FORMAT(uf.created_at, '%d/%m/%Y') as formatted_date
        FROM user_followers uf
        JOIN users u ON uf.follower_id = u.id
        WHERE uf.following_id = ?
        ORDER BY uf.created_at DESC
        LIMIT 20";
        $followers_list = $this->fetchAll($followers_list_sql, [$userId]);

        // Following list
        $following_list_sql = "SELECT u.id, u.name, u.email, u.avatar, uf.created_at,
            DATE_FORMAT(uf.created_at, '%d/%m/%Y') as formatted_date
        FROM user_followers uf
        JOIN users u ON uf.following_id = u.id
        WHERE uf.follower_id = ?
        ORDER BY uf.created_at DESC
        LIMIT 20";
        $following_list = $this->fetchAll($following_list_sql, [$userId]);

        // Ensure stats have default values
        if (!$order_stats) {
            $order_stats = [
                'total_orders' => 0,
                'completed_orders' => 0,
                'pending_orders' => 0,
                'cancelled_orders' => 0,
                'total_spent' => 0
            ];
        }

        return [
            'stats' => $order_stats,
            'recent_orders' => $recent_orders ?? [],
            'monthly_activity' => $monthly_activity ?? [],
            'posts' => $posts ?? [],
            'network_stats' => $network_stats,
            'followers_list' => $followers_list ?? [],
            'following_list' => $following_list ?? []
        ];
    }
}
