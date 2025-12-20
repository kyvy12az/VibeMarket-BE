<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

int_headers();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['name'], $data['email'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng cung cấp đầy đủ thông tin']);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$password = $data['password'];
$phone = isset($data['phone']) ? trim($data['phone']) : null;
$address = isset($data['address']) ? trim($data['address']) : null;
$zalo_id = isset($data['zalo_id']) ? trim($data['zalo_id']) : null;
$facebook_id = isset($data['facebook_id']) ? trim($data['facebook_id']) : null;
$role = isset($data['role']) ? $data['role'] : 'user';
$avatar = isset($data['avatar']) ? trim($data['avatar']) : null;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email không hợp lệ']);
    exit;
}

// Check email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Email đã tồn tại']);
    exit;
}
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, email, phone, address, password, avatar, zalo_id, facebook_id, role, provider) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'local')");
$stmt->bind_param("sssssssss", $name, $email, $phone, $address, $hashedPassword, $avatar, $zalo_id, $facebook_id, $role);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;

    $payload = [
        'sub' => $user_id,
        'name' => $name,
        'email' => $email,
        'role' => $role
    ];
    $jwt = create_jwt($payload);

    $stmt_update = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt_update->bind_param("si", $jwt, $user_id);
    $stmt_update->execute();
    $stmt_update->close();

    echo json_encode([
        'success' => true,
        'token' => $jwt,
        'user' => [
            'id' => $user_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'avatar' => $avatar,
            'zalo_id' => $zalo_id,
            'facebook_id' => $facebook_id,
            'role' => $role,
            'provider' => 'local'
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Đã xảy ra lỗi khi đăng ký']);
}
$stmt->close();
$conn->close();
