<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
    
    // Start transaction
    $conn->begin_transaction();
    
    $today = date('Y-m-d');
    
    // 1. Check if user has spins remaining
    $stmt = $conn->prepare("
        SELECT 
            spins_remaining,
            CASE 
                WHEN last_reset_date < ? THEN 1 
                ELSE 0 
            END as needs_reset
        FROM user_spins 
        WHERE user_id = ?
        FOR UPDATE
    ");
    
    $stmt->bind_param("si", $today, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Initialize user spins
        $insert_stmt = $conn->prepare("
            INSERT INTO user_spins (user_id, spins_remaining, total_spins_today, last_reset_date) 
            VALUES (?, 1, 1, ?)
        ");
        $insert_stmt->bind_param("is", $user_id, $today);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        $spins_remaining = 1;
    } else {
        $row = $result->fetch_assoc();
        
        if ($row['needs_reset'] == 1) {
            // Reset spins for new day
            $update_stmt = $conn->prepare("
                UPDATE user_spins 
                SET spins_remaining = 1, total_spins_today = 1, last_reset_date = ?
                WHERE user_id = ?
            ");
            $update_stmt->bind_param("si", $today, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $spins_remaining = 1;
        } else {
            $spins_remaining = $row['spins_remaining'];
        }
    }
    
    $stmt->close();
    
    // Check if user has spins left
    if ($spins_remaining <= 0) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No spins remaining today'
        ]);
        exit();
    }
    
    // 2. Select random prize based on weighted probability
    $prize_stmt = $conn->prepare("
        SELECT 
            prize_id, prize_name, prize_icon, prize_value, 
            description, rarity, color, voucher_type,
            discount_amount, min_order_value, weight
        FROM prize_config 
        WHERE is_active = 1
    ");
    
    $prize_stmt->execute();
    $prizes_result = $prize_stmt->get_result();
    $prizes = $prizes_result->fetch_all(MYSQLI_ASSOC);
    $prize_stmt->close();
    
    if (empty($prizes)) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'No active prizes available'
        ]);
        exit();
    }
    
    // Weighted random selection
    $total_weight = array_sum(array_column($prizes, 'weight'));
    $random = mt_rand(1, $total_weight);
    $selected_prize = null;
    
    foreach ($prizes as $prize) {
        $random -= $prize['weight'];
        if ($random <= 0) {
            $selected_prize = $prize;
            break;
        }
    }
    
    if (!$selected_prize) {
        $selected_prize = $prizes[0];
    }
    
    // 3. Decrease spins remaining (unless it's an extra spin prize)
    if ($selected_prize['voucher_type'] === 'extra_spin') {
        // Don't decrease, just add to history
        $new_spins = $spins_remaining;
    } else {
        $new_spins = $spins_remaining - 1;
        $update_spins_stmt = $conn->prepare("
            UPDATE user_spins 
            SET spins_remaining = ?
            WHERE user_id = ?
        ");
        $update_spins_stmt->bind_param("ii", $new_spins, $user_id);
        $update_spins_stmt->execute();
        $update_spins_stmt->close();
    }
    
    // 4. Create voucher if applicable
    $voucher_id = null;
    $voucher_code = null;
    
    if (in_array($selected_prize['voucher_type'], ['discount', 'freeship'])) {
        // Generate unique voucher code
        $voucher_code = 'LW' . strtoupper(uniqid());
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $voucher_stmt = $conn->prepare("
            INSERT INTO user_vouchers 
            (user_id, voucher_code, prize_id, prize_name, voucher_type, 
             discount_amount, min_order_value, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $voucher_stmt->bind_param(
            "issssdds",
            $user_id,
            $voucher_code,
            $selected_prize['prize_id'],
            $selected_prize['prize_name'],
            $selected_prize['voucher_type'],
            $selected_prize['discount_amount'],
            $selected_prize['min_order_value'],
            $expires_at
        );
        
        $voucher_stmt->execute();
        $voucher_id = $conn->insert_id;
        $voucher_stmt->close();
    } elseif ($selected_prize['voucher_type'] === 'extra_spin') {
        // Add extra spin
        $add_spin_stmt = $conn->prepare("
            UPDATE user_spins 
            SET spins_remaining = spins_remaining + 1,
                total_spins_today = total_spins_today + 1
            WHERE user_id = ?
        ");
        $add_spin_stmt->bind_param("i", $user_id);
        $add_spin_stmt->execute();
        $add_spin_stmt->close();
        
        $new_spins += 1;
    }
    
    // 5. Record spin history
    $history_stmt = $conn->prepare("
        INSERT INTO spin_history 
        (user_id, prize_id, prize_name, prize_value, prize_rarity, voucher_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $history_stmt->bind_param(
        "issssi",
        $user_id,
        $selected_prize['prize_id'],
        $selected_prize['prize_name'],
        $selected_prize['prize_value'],
        $selected_prize['rarity'],
        $voucher_id
    );
    
    $history_stmt->execute();
    $history_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $response_prize = [
        'id' => $selected_prize['prize_id'],
        'name' => $selected_prize['prize_name'],
        'icon' => $selected_prize['prize_icon'],
        'value' => $selected_prize['prize_value'],
        'description' => $selected_prize['description'],
        'rarity' => $selected_prize['rarity'],
        'color' => $selected_prize['color']
    ];
    
    $response_data = [
        'prize' => $response_prize,
        'spins_remaining' => (int)$new_spins
    ];
    
    if ($voucher_code) {
        $response_data['voucher_code'] = $voucher_code;
        $response_data['expires_at'] = $expires_at;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Spin successful',
        'data' => $response_data
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
