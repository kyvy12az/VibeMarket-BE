<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

int_headers();

$data = json_decode(file_get_contents("php://input"), true);

// Kiểm tra dữ liệu đầu vào
if (!isset($data['email'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng cung cấp đầy đủ thông tin']);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];

$stmt = $conn->prepare("SELECT id, name, email, phone, address, password, avatar, token, zalo_id, facebook_id, role, provider FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Email chưa được đăng ký trên hệ thống']);
    exit;
}

$stmt->bind_result($id, $name, $email_db, $phone, $address, $hashedPassword, $avatar, $token_db, $zalo_id, $facebook_id, $role, $provider);
$stmt->fetch();

if (!password_verify($password, $hashedPassword)) {
    http_response_code(401);
    echo json_encode(['error' => 'Thông tin đăng nhập không hợp lệ']);
    exit;
}

// Tạo JWT bằng helper
$payload = [
    'sub' => $id,
    'name' => $name,
    'email' => $email_db,
    'role' => $role
];
$jwt = create_jwt($payload);

// Lưu token vào DB và update provider nếu chưa có
if (empty($provider)) {
    $stmt_update = $conn->prepare("UPDATE users SET token = ?, provider = 'local' WHERE id = ?");
    $stmt_update->bind_param("si", $jwt, $id);
    $provider = 'local';
} else {
    $stmt_update = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt_update->bind_param("si", $jwt, $id);
}
$stmt_update->execute();
$stmt_update->close();

echo json_encode([
    'success' => true,
    'token' => $jwt,
    'user' => [
        'id' => $id,
        'name' => $name,
        'email' => $email_db,
        'phone' => $phone,
        'address' => $address,
        'avatar' => $avatar,
        'zalo_id' => $zalo_id,
        'facebook_id' => $facebook_id,
        'role' => $role,
        'provider' => $provider,
        'created_at' => null // Nếu muốn trả created_at thì SELECT thêm ở trên
    ]
]);

$stmt->close();
$conn->close();
