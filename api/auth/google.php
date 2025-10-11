<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';
int_headers();

use Firebase\JWT\JWT;

// --- Cấu hình Google OAuth ---
$Google_ClientID = getenv('GOOGLE_CLIENT_ID') ?: '690084808144-mm016r153bdjg05p0pf4ft1rflt2nusa.apps.googleusercontent.com';
// $Google_SecretKey = getenv('GOOGLE_CLIENT_SECRET') ?: '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';
$Google_SecretKey = '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';
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false) {
    $Google_RedirectURI = 'http://localhost:8080/callback/google';
} else {
    $Google_RedirectURI = 'https://vibemarket.kyvydev.id.vn/callback/google';
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu authorization code']);
    exit;
}

$authorization_code = $data['code'];

try {
    // 1. Đổi code lấy access_token và id_token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = array(
        'client_id' => $Google_ClientID,
        'client_secret' => $Google_SecretKey,
        'code' => $authorization_code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $Google_RedirectURI
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $token_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($token_data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ),
    ));

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpcode !== 200) {
        // Debug lỗi Google trả về
        file_put_contents('google_oauth_error.log', $response);
        throw new Exception('Không thể đổi authorization code lấy token: ' . $response);
    }

    $token_response = json_decode($response, true);

    if (!$token_response || !isset($token_response['id_token'])) {
        throw new Exception('Token response không hợp lệ');
    }

    // 2. Giải mã id_token để lấy thông tin user
    $id_token = $token_response['id_token'];
    $jwt_payload = explode('.', $id_token)[1];
    $jwt_payload = json_decode(base64_decode(strtr($jwt_payload, '-_', '+/')), true);

    if (!$jwt_payload || !isset($jwt_payload['email'])) {
        throw new Exception('Không thể lấy thông tin email từ Google');
    }

    $email = $jwt_payload['email'];
    $name = $jwt_payload['name'] ?? ($jwt_payload['given_name'] . ' ' . $jwt_payload['family_name']);
    $avatar = $jwt_payload['picture'] ?? null;

    // 3. Kiểm tra user đã tồn tại chưa
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $status = 'active';
    $role = 'user';
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $status = $user['status'] ?? 'active';
        $role = $user['role'] ?? 'user';
        $name = $user['name'];
        if ($avatar) {
            $update_stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $update_stmt->bind_param("si", $avatar, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, avatar, password, role) VALUES (?, ?, ?, ?, ?)");
        $default_password = password_hash('google_auth_' . time(), PASSWORD_DEFAULT);
        $role = 'user';
        $stmt->bind_param("sssss", $name, $email, $avatar, $default_password, $role);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
        } else {
            throw new Exception('Không thể tạo tài khoản');
        }
    }

    $stmt->close();

    // 4. Tạo JWT cho FE
    $payload = [
        'iss' => JWT_ISSUER,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRE,
        'sub' => $user_id,
        'name' => $name,
        'email' => $email,
        'role' => $role
    ];

    $jwt_token = JWT::encode($payload, JWT_SECRET, 'HS256');

    // Lưu token vào DB nếu muốn
    $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->bind_param("si", $jwt_token, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Đăng nhập Google thành công',
        'token' => $jwt_token,
        'user' => [
            'id' => $user_id,
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
            'role' => $role,
            'status' => $status
        ]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Đăng nhập Google thất bại: ' . $e->getMessage()
    ]);
}

$conn->close();
