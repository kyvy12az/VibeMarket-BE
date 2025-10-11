<?php
session_start();
require_once '../../config/database.php';

if (!isset($_GET['code'])) {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php?msg=no_code');
    exit;
}

$code = $_GET['code'];
$token_url = "https://github.com/login/oauth/access_token";

$data = [
    "client_id" => $Github_ClientID,
    "client_secret" => $Github_SecretKey,
    "code" => $code,
    "redirect_uri" => $Github_RedirectURI
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
$response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($response, true);
$access_token = $token_data['access_token'] ?? null;

if (!$access_token) {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php?msg=no_access_token');
    exit;
}

$ch = curl_init("https://api.github.com/user");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token {$access_token}",
    "User-Agent: PHP-App"
]);
$user_info = curl_exec($ch);
curl_close($ch);

$user = json_decode($user_info, true);

if (empty($user['id']) || empty($user['login'])) {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php?msg=invalid_user');
    exit;
}
$checkAdmin = $conn->prepare("SELECT * FROM admin WHERE github_id = ?");
$checkAdmin->bind_param("i", $user['id']);
$checkAdmin->execute();
$result = $checkAdmin->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['username'] = $user['login'];
    $_SESSION['email'] = $admin['email'];
    $_SESSION['name'] = $user['name'] ?? "Không rõ";
    $_SESSION['avatar'] = $user['avatar_url'] ?? "Không rõ";
    $_SESSION['github_id'] = $user['id'];
    $_SESSION['user_role'] = 'admin';
    $update = $conn->prepare("UPDATE admin SET name = ?, username = ?, avatar_url = ?, last_login = NOW(), access_token = ? WHERE id = ?");
    $update->bind_param("ssssi", $user['name'], $user['login'], $user['avatar_url'], $access_token, $admin['id']);
    $update->execute();

    header("Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/dashboard.php");
} else {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php?msg=not_admin');
}
exit;
