<?php
function checkRateLimit($limit = 5, $timeWindow = 60, $endpoint = null) {
    session_start();
    
    // Tạo key dựa trên IP và endpoint
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $endpoint = $endpoint ?? ($_SERVER['REQUEST_URI'] ?? 'default');
    $key = "rate_limit_{$ip}_{$endpoint}";
    
    // Lấy thông tin rate limit từ session
    $currentTime = time();
    $requests = $_SESSION[$key] ?? ['count' => 0, 'reset_time' => $currentTime + $timeWindow];
    
    // Reset nếu đã hết time window
    if ($currentTime >= $requests['reset_time']) {
        $requests = ['count' => 0, 'reset_time' => $currentTime + $timeWindow];
    }
    
    // Tăng counter
    $requests['count']++;
    $_SESSION[$key] = $requests;
    
    // Kiểm tra limit
    if ($requests['count'] > $limit) {
        $retryAfter = $requests['reset_time'] - $currentTime;
        
        http_response_code(429);
        header("Retry-After: $retryAfter");
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'message' => "Quá nhiều yêu cầu, thử lại sau: $retryAfter giây",
            'retry_after' => $retryAfter
        ]);
        
        exit;
    }
    
    return true;
}
