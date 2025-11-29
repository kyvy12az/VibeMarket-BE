<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

define('JWT_SECRET', 't9gdgfgfgghned((**BmzYf&5wT0e');
define('JWT_ALGO', 'HS256');
define('JWT_ISSUER', 'VibeMarket');
define('JWT_EXPIRE', 60 * 60 * 24 * 7);
function create_jwt($payload = [])
{
    $issuedAt = time();
    $expire   = $issuedAt + JWT_EXPIRE;

    $token = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $expire,
        'iss' => JWT_ISSUER
    ]);

    return JWT::encode($token, JWT_SECRET, JWT_ALGO);
}
function verify_jwt($jwt)
{
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, JWT_ALGO));
        return (array)$decoded;
    } catch (Exception $e) {
        return false;
    }
}
function get_bearer_token()
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;

    if (!$authHeader) return null;
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }

    return null;
}
function get_user_id_from_token()
{
    $token = get_bearer_token();
    file_put_contents("debug_jwt.log", "RAW TOKEN = " . $token . "\n", FILE_APPEND);
    if (!$token) return null;
    $decoded = verify_jwt($token);
    file_put_contents("debug_jwt.log", "DECODED = " . print_r($decoded, true) . "\n", FILE_APPEND);
    if (!$decoded) return null;
    return $decoded['sub'] ?? $decoded['user_id'] ?? null;
}
