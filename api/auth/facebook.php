<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

if (function_exists('init_headers')) {
    init_headers();
} elseif (function_exists('int_headers')) {
    int_headers();
} elseif (function_exists('set_headers')) {
    set_headers();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['accessToken'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu accessToken Facebook']);
    exit;
}

$accessToken = $data['accessToken'];

// Lấy thông tin user từ Facebook Graph API
$fbUrl = "https://graph.facebook.com/me?fields=id,name,email,picture.type(large)&access_token=$accessToken";
$response = file_get_contents($fbUrl);
$userInfo = json_decode($response, true);

if (isset($userInfo['id'])) {
    $fbId = $userInfo['id'];
    $name = $userInfo['name'];
    $email = $userInfo['email'] ?? generateRandomEmail();
    $avatar = $userInfo['picture']['data']['url'] ?? null;

    // Kiểm tra user đã tồn tại trong bảng users
    $stmt = $conn->prepare("SELECT id, name, email, avatar, role FROM users WHERE facebook_id = ?");
    $stmt->bind_param("s", $fbId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $user_id = $user['id'];
        $name = $user['name'];
        $email = $user['email'];
        $avatar = $user['avatar'] ?: $avatar;
        $role = $user['role'];
    } else {
        $role = 'user';
        $stmt = $conn->prepare("INSERT INTO users (name, facebook_id, avatar, password, email, role) VALUES (?, ?, ?, ?, ?, ?)");
        $hashedPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
        $stmt->bind_param("ssssss", $name, $fbId, $avatar, $hashedPassword, $email, $role);
        $stmt->execute();
        $user_id = $stmt->insert_id;
    }

    // Tạo JWT token
    $payload = [
        'sub' => $user_id,
        'name' => $name,
        'email' => $email,
        'facebook_id' => $fbId,
        'role' => $role
    ];
    $jwt = create_jwt($payload);

    // Lưu token vào DB
    $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->bind_param("si", $jwt, $user_id);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user_id,
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
            'facebook_id' => $fbId,
            'role' => $role
        ],
        'token' => $jwt
    ]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token Facebook không hợp lệ hoặc hết hạn']);
}
$conn->close();

function generateRandomEmail() {
    $random = rand(10000, 99999);
    return $random . '@facebook.com';
}