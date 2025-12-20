<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

// Helper function to generate full URL for images
function getFullImageUrl($imagePath) {
    if (empty($imagePath)) {
        return null;
    }
    
    // If already a full URL, return as is
    if (preg_match('/^https?:\/\//i', $imagePath)) {
        return $imagePath;
    }
    
    // Get base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Remove leading slash if exists
    $imagePath = ltrim($imagePath, '/');
    
    // If path doesn't start with VIBE_MARKET_BACKEND, add it
    if (!preg_match('/^VIBE_MARKET_BACKEND/i', $imagePath)) {
        $imagePath = 'VIBE_MARKET_BACKEND/VibeMarket-BE/' . $imagePath;
    }
    
    // Construct full URL
    return $protocol . '://' . $host . '/' . $imagePath;
}

try {
    // Get Authorization header (with fallback for different server configurations)
    $authHeader = '';
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    
    // Fallback to $_SERVER if getallheaders() doesn't work
    if (empty($authHeader)) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Missing or invalid token'
        ]);
        exit();
    }

    $token = $matches[1];
    $decoded = verify_jwt($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Invalid token'
        ]);
        exit();
    }

    $user_id = $decoded['sub'] ?? $decoded['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Invalid user ID in token'
        ]);
        exit();
    }
    
    // Get status filter from query params
    $status = $_GET['status'] ?? 'pending'; // Default to pending
    
    // Validate status
    $valid_statuses = ['pending', 'public', 'hidden', 'deleted'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'pending';
    }

    // Use mysqli connection from database.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Main query to get user's posts with specific status
    $query = "
        SELECT 
            p.id,
            p.user_id,
            p.content,
            p.status,
            p.created_at,
            p.updated_at,
            u.id as author_id,
            u.name as author_name,
            u.avatar as author_avatar
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ? AND p.status = ?
        ORDER BY p.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('is', $user_id, $status);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $posts = [];

    while ($row = $result->fetch_assoc()) {
        $post_id = (int)$row['id'];
        
        // Fetch images for this post
        $images = [];
        $image_query = "SELECT image_url FROM post_images WHERE post_id = ? ORDER BY id";
        $image_stmt = $conn->prepare($image_query);
        if ($image_stmt) {
            $image_stmt->bind_param('i', $post_id);
            if ($image_stmt->execute()) {
                $image_result = $image_stmt->get_result();
                while ($img_row = $image_result->fetch_assoc()) {
                    if (!empty($img_row['image_url'])) {
                        $fullImageUrl = getFullImageUrl($img_row['image_url']);
                        if ($fullImageUrl) {
                            $images[] = $fullImageUrl;
                        }
                    }
                }
            }
            $image_stmt->close();
        }
        
        // Get likes count
        $likes = 0;
        $likes_query = "SELECT COUNT(*) as cnt FROM post_likes WHERE post_id = ?";
        $likes_stmt = $conn->prepare($likes_query);
        if ($likes_stmt) {
            $likes_stmt->bind_param('i', $post_id);
            if ($likes_stmt->execute()) {
                $likes_result = $likes_stmt->get_result();
                $likes_row = $likes_result->fetch_assoc();
                $likes = (int)($likes_row['cnt'] ?? 0);
            }
            $likes_stmt->close();
        }
        
        // Get comments count
        $comments = 0;
        $comments_query = "SELECT COUNT(*) as cnt FROM post_comments WHERE post_id = ?";
        $comments_stmt = $conn->prepare($comments_query);
        if ($comments_stmt) {
            $comments_stmt->bind_param('i', $post_id);
            if ($comments_stmt->execute()) {
                $comments_result = $comments_stmt->get_result();
                $comments_row = $comments_result->fetch_assoc();
                $comments = (int)($comments_row['cnt'] ?? 0);
            }
            $comments_stmt->close();
        }
        
        // Check if user liked this post
        $is_liked = false;
        $liked_query = "SELECT COUNT(*) as cnt FROM post_likes WHERE post_id = ? AND user_id = ?";
        $liked_stmt = $conn->prepare($liked_query);
        if ($liked_stmt) {
            $liked_stmt->bind_param('ii', $post_id, $user_id);
            if ($liked_stmt->execute()) {
                $liked_result = $liked_stmt->get_result();
                $liked_row = $liked_result->fetch_assoc();
                $is_liked = ($liked_row['cnt'] ?? 0) > 0;
            }
            $liked_stmt->close();
        }

        // Process avatar URL
        $avatarUrl = null;
        if (!empty($row['author_avatar'])) {
            $avatarUrl = getFullImageUrl($row['author_avatar']);
        }

        $posts[] = [
            'id' => $post_id,
            'user_id' => (int)$row['user_id'],
            'title' => '',
            'content' => $row['content'] ?? '',
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'] ?? $row['created_at'],
            'author' => [
                'id' => (int)($row['author_id'] ?? $row['user_id']),
                'name' => $row['author_name'] ?? 'Unknown',
                'avatar' => $avatarUrl
            ],
            'likes' => $likes,
            'comments' => $comments,
            'shares' => 0,
            'saves' => 0,
            'views' => 0,
            'images' => $images,
            'is_liked' => $is_liked,
            'is_saved' => false,
            'tags' => [],
            'featured_products' => []
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'count' => count($posts),
        'status_filter' => $status
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
