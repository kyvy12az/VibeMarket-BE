<?php

require_once __DIR__ . '/../core/Model.php';

class PostModel extends Model
{
    protected $table = 'posts';

    public function getPosts($start, $length, $search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(p.content LIKE ? OR u.name LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param]);
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "SELECT p.*, u.name as user_name,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comments_count,
                (SELECT GROUP_CONCAT(image_url) FROM post_images WHERE post_id = p.id) as images
                FROM {$this->table} p
                LEFT JOIN users u ON p.user_id = u.id
                $where_clause 
                ORDER BY p.created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;

        return $this->fetchAll($sql, $params);
    }

    public function getPostsCount($search = '', $filters = [])
    {
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(p.content LIKE ? OR u.name LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param]);
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT COUNT(*) as total 
                FROM {$this->table} p
                LEFT JOIN users u ON p.user_id = u.id
                $where_clause";
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }

    public function getPostById($id)
    {
        $sql = "SELECT p.*, u.name as user_name, u.avatar as user_avatar,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comments_count
                FROM {$this->table} p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ?";
        $post = $this->fetchOne($sql, [$id]);
        
        if ($post) {
            // Get post images
            $images_sql = "SELECT image_url FROM post_images WHERE post_id = ? ORDER BY id";
            $images = $this->fetchAll($images_sql, [$id]);
            $post['images'] = array_column($images, 'image_url');
        }
        
        return $post;
    }

    public function updateStatus($postId, $status)
    {
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->query($sql, [$status, $postId]);
        return $stmt !== false;
    }

    public function deletePost($postId)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->query($sql, [$postId]);
        return $stmt !== false;
    }
}
