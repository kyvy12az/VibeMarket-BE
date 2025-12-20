<?php
session_start();
require_once '../../config/database.php';

// Detect base URL for local/production
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $base_url = '/VIBE_MARKET_BACKEND/VibeMarket-BE';
} else {
    $base_url = '';
}

// Check if code exists
if (!isset($_GET['code'])) {
    header('Location: ' . $base_url . '/panel/auth/login?msg=no_code');
    exit;
}

$code = $_GET['code'];

$token_url = "https://github.com/login/oauth/access_token";

// Exchange code for access token
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
    header('Location: ' . $base_url . '/panel/auth/login?msg=no_access_token');
    exit;
}

// Get user info from GitHub
$ch = curl_init("https://api.github.com/user");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$access_token}",
    "User-Agent: VibeMarket-Panel",
    "Accept: application/vnd.github.v3+json"
]);
$user_info = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    header('Location: ' . $base_url . '/panel/auth/login?msg=invalid_user');
    exit;
}

$user = json_decode($user_info, true);

if (empty($user['id']) || empty($user['login'])) {
    header('Location: ' . $base_url . '/panel/auth/login?msg=invalid_user');
    exit;
}

// Get user email if not public
$email = $user['email'] ?? null;
if (empty($email)) {
    $ch = curl_init("https://api.github.com/user/emails");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$access_token}",
        "User-Agent: VibeMarket-Panel",
        "Accept: application/vnd.github.v3+json"
    ]);
    $emails_info = curl_exec($ch);
    curl_close($ch);
    
    $emails = json_decode($emails_info, true);
    if (is_array($emails)) {
        foreach ($emails as $email_data) {
            if (!empty($email_data['primary']) && !empty($email_data['verified'])) {
                $email = $email_data['email'];
                break;
            }
        }
        if (empty($email) && !empty($emails[0]['email'])) {
            $email = $emails[0]['email'];
        }
    }
}

// Check if admin exists
$checkAdmin = $conn->prepare("SELECT * FROM admin WHERE github_id = ?");
$checkAdmin->bind_param("i", $user['id']);
$checkAdmin->execute();
$result = $checkAdmin->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $admin = $result->fetch_assoc();

    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['username'] = $user['login'];
    $_SESSION['email'] = $email ?? $admin['email'];
    $_SESSION['name'] = $user['name'] ?? $user['login'];
    $_SESSION['avatar'] = $user['avatar_url'] ?? '';
    $_SESSION['github_id'] = $user['id'];
    $_SESSION['user_role'] = 'admin';

    // Update admin info
    $update = $conn->prepare("UPDATE admin SET name = ?, username = ?, email = ?, avatar_url = ?, access_token = ?, last_login = NOW() WHERE id = ?");
    $update->bind_param("sssssi", 
        $user['name'], 
        $user['login'], 
        $email,
        $user['avatar_url'], 
        $access_token, 
        $admin['id']
    );
    $update->execute();

    header('Location: ' . $base_url . '/panel/dashboard');
    exit;
} else {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>GitHub OAuth</title></head><body>";
    echo "<h2>GitHub OAuth Success!</h2>";
    echo "<p>However, your GitHub account is not registered as an admin.</p>";
    echo "<p>Please add this GitHub ID to the admin table: <strong>" . $user['id'] . "</strong></p>";
    echo "<p>SQL: <code>INSERT INTO admin (github_id, username, email) VALUES ({$user['id']}, '{$user['login']}', '{$email}');</code></p>";
    echo "<p><a href='" . $base_url . "/panel/auth/login'>Back to login</a></p>";
    echo "</body></html>";
    exit;
}