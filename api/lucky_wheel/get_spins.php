<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
require_once '../../config/jwt.php';

// Verify JWT token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Token not provided']);
    exit();
}

$jwt = $matches[1];
$decoded = verify_jwt($jwt);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid token']);
    exit();
}

$user_id = $decoded['sub'] ?? $decoded['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid user ID']);
    exit();
}

try {
    // Get current date
    $today = date('Y-m-d');
    
    // Check if user has spin record for today
    $stmt = $conn->prepare("
        SELECT 
            spins_remaining, 
            total_spins_today, 
            last_reset_date,
            CASE 
                WHEN last_reset_date < ? THEN 1 
                ELSE 0 
            END as needs_reset
        FROM user_spins 
        WHERE user_id = ?
    ");
    
    $stmt->bind_param("si", $today, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // User doesn't have a spin record, create one
        $insert_stmt = $conn->prepare("
            INSERT INTO user_spins (user_id, spins_remaining, total_spins_today, last_reset_date) 
            VALUES (?, 1, 1, ?)
        ");
        $insert_stmt->bind_param("is", $user_id, $today);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        $spins_remaining = 1;
        $total_spins_today = 1;
    } else {
        $row = $result->fetch_assoc();
        
        // Check if we need to reset (new day)
        if ($row['needs_reset'] == 1) {
            $update_stmt = $conn->prepare("
                UPDATE user_spins 
                SET spins_remaining = 1, 
                    total_spins_today = 1, 
                    last_reset_date = ?
                WHERE user_id = ?
            ");
            $update_stmt->bind_param("si", $today, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $spins_remaining = 1;
            $total_spins_today = 1;
        } else {
            $spins_remaining = $row['spins_remaining'];
            $total_spins_today = $row['total_spins_today'];
        }
    }
    
    $stmt->close();
    
    // Calculate progress percentage
    $progress_percentage = $total_spins_today > 0 
        ? (($total_spins_today - $spins_remaining) / $total_spins_today) * 100 
        : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'spins_remaining' => (int)$spins_remaining,
            'total_spins_today' => (int)$total_spins_today,
            'progress_percentage' => round($progress_percentage, 2)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
