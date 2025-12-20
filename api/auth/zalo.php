<?php
// Thêm vào đầu file PHP (trước mọi echo/require)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../vendor/autoload.php';

// Sửa tên hàm header nếu bị sai
if (function_exists('init_headers')) {
    init_headers();
} elseif (function_exists('int_headers')) {
    int_headers();
} elseif (function_exists('set_headers')) {
    set_headers();
}

function generateRandomEmail()
{
    $random = rand(10000, 99999);
    return $random . '@vibemarket.vn';
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['zaloToken'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu zaloToken']);
    exit;
}
// Lấy App ID và Secret từ biến môi trường (hoặc file config)
$zaloAppID = getenv('ZALO_APP_ID') ?: '1382038683045766552';
$zaloAppSecretKey = getenv('ZALO_APP_SECRET') ?: 'UFE8BOTuHU3NBXGIqKvb';
$zaloToken = $data['zaloToken'];

// Lấy access token từ Zalo
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth.zaloapp.com/v4/access_token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded',
    'secret_key: ' . $zaloAppSecretKey
));
$params = array(
    'code' => $zaloToken,
    'app_id' => $zaloAppID,
    'grant_type' => 'authorization_code'
    // Không gửi code_verifier nếu không dùng PKCE
);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
$response = curl_exec($ch);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối Zalo: ' . curl_error($ch)]);
    curl_close($ch);
    $conn->close();
    exit;
}
$result = json_decode($response);
curl_close($ch);
file_put_contents('zalo_debug.log', print_r($response, true), FILE_APPEND);

if (!isset($result->error)) {
    $zaloAccessToken = $result->access_token;
    // Lấy thông tin user từ Zalo
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://graph.zalo.me/v2.0/me?fields=id,name,picture');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'access_token: ' . $zaloAccessToken
    ));
    $getUserResponse = curl_exec($ch);
    if ($getUserResponse === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi lấy thông tin user từ Zalo: ' . curl_error($ch)]);
        curl_close($ch);
        $conn->close();
        exit;
    }
    $userInfo = json_decode($getUserResponse, true);
    curl_close($ch);

    if (isset($userInfo['id'])) {
        $zaloId = $userInfo['id'];
        $name = $userInfo['name'];
        // Kiểm tra đường dẫn avatar
        $avatar = null;
        if (isset($userInfo['picture']['data']['url'])) {
            $avatar = $userInfo['picture']['data']['url'];
        } elseif (isset($userInfo['picture']['data'])) {
            $avatar = $userInfo['picture']['data'];
        } elseif (isset($userInfo['picture'])) {
            $avatar = $userInfo['picture'];
        }
        $email = generateRandomEmail();

        // Kiểm tra user đã tồn tại trong bảng users
        $stmt = $conn->prepare("SELECT id, name, email, avatar, phone, address, role FROM users WHERE zalo_id = ?");
        $stmt->bind_param("s", $zaloId);
        $stmt->execute();
        $resultUser = $stmt->get_result();

        if ($user = $resultUser->fetch_assoc()) {
            $user_id = $user['id'];
            $name = $user['name'];
            $email = $user['email'];
            $avatar = $user['avatar'] ?: $avatar;
            $role = $user['role'];
            $phone = $user['phone'];
            $address = $user['address'];
            
            // Update provider nếu chưa có
            $stmt_update = $conn->prepare("UPDATE users SET provider = 'zalo' WHERE id = ?");
            $stmt_update->bind_param("i", $user_id);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, zalo_id, avatar, password, email, role, provider) VALUES (?, ?, ?, ?, ?, ?, 'zalo')");
            $hashedPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
            $role = 'user';
            $stmt->bind_param("ssssss", $name, $zaloId, $avatar, $hashedPassword, $email, $role);
            $stmt->execute();
            $user_id = $stmt->insert_id;
            $phone = null;
            $address = null;
        }

        // Tạo JWT token
        $payload = [
            'sub' => $user_id,
            'name' => $name,
            'email' => $email,
            'zalo_id' => $zaloId,
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
                'zalo_id' => $zaloId,
                'phone' => $phone,
                'address' => $address,
                'role' => $role
            ],
            'token' => $jwt
        ]);
    } else {
        $errorMsg = isset($userInfo['message']) ? $userInfo['message'] : 'Token Zalo không hợp lệ hoặc hết hạn';
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => "Lỗi xác thực Zalo Access Token: " . $result->error]);
}
$conn->close();
ini_set('display_errors', 1);
error_reporting(E_ALL);
