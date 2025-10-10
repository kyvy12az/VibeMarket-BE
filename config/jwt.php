<?php
// JWT config & helper for Vibe Market Backend

// Thư viện JWT: composer require firebase/php-jwt
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Secret key (nên lưu .env hoặc biến môi trường thực tế)
define('JWT_SECRET', 't9gdgfgfgghned((**BmzYf&5wT0e');
define('JWT_ALGO', 'HS256');
define('JWT_ISSUER', 'VibeMarket');
define('JWT_EXPIRE', 60 * 60 * 24 * 7); // 7 ngày

// Tạo JWT token
function create_jwt($payload = []) {
    $issuedAt = time();
    $expire = $issuedAt + JWT_EXPIRE;
    $token = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $expire,
        'iss' => JWT_ISSUER
    ]);
    return JWT::encode($token, JWT_SECRET, JWT_ALGO);
}

// Giải mã & kiểm tra JWT token
function verify_jwt($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, JWT_ALGO));
        return (array)$decoded;
    } catch (Exception $e) {
        return false;
    }
}
