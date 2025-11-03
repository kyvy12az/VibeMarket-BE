<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load composer autoload (adjust path if project layout khác)
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;
use Dotenv\Dotenv;

// load .env from project root (one level up from /api)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

// CORS
$frontend = $_ENV['FRONTEND_URL'] ?? $_SERVER['FRONTEND_URL'] ?? getenv('FRONTEND_URL') ?: '*';
header("Access-Control-Allow-Origin: {$frontend}");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Basic checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded (expected field "file")']);
    exit;
}

$file = $_FILES['file'];

// Basic validation
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'pdf', 'zip'];
$maxSize = 20 * 1024 * 1024; // 20MB

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit;
}

// Read Cloudinary config from env
$cloudName = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? $_SERVER['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME') ?: null;
$apiKey = $_ENV['CLOUDINARY_API_KEY'] ?? $_SERVER['CLOUDINARY_API_KEY'] ?? getenv('CLOUDINARY_API_KEY') ?: null;
$apiSecret = $_ENV['CLOUDINARY_API_SECRET'] ?? $_SERVER['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET') ?: null;
$cloudFolder = $_ENV['CLOUDINARY_UPLOAD_FOLDER'] ?? $_SERVER['CLOUDINARY_UPLOAD_FOLDER'] ?? getenv('CLOUDINARY_UPLOAD_FOLDER') ?: 'chat';

// Debug if missing
error_log("upload.php env: cloud=" . var_export($cloudName, true) . " key_set=" . (!empty($apiKey) ? '1' : '0') . " secret_set=" . (!empty($apiSecret) ? '1' : '0'));

if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cloudinary not configured. Set CLOUDINARY_CLOUD_NAME / CLOUDINARY_API_KEY / CLOUDINARY_API_SECRET in .env']);
    exit;
}

// Init Cloudinary client (per quickstart)
try {
    $cloudinary = new Cloudinary([
        'cloud' => [
            'cloud_name' => $cloudName,
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ],
        'url' => ['secure' => true],
    ]);
} catch (Exception $e) {
    error_log("upload.php cloudinary init error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to initialize Cloudinary client']);
    exit;
}

// Upload
try {
    $options = [
        'folder' => $cloudFolder,
        'use_filename' => true,
        'unique_filename' => false,
        'resource_type' => 'auto'
    ];

    // upload accepts tmp file path
    $result = $cloudinary->uploadApi()->upload($file['tmp_name'], $options);

    if (empty($result) || !isset($result['secure_url'])) {
        throw new Exception('Invalid upload response from Cloudinary');
    }

    $secureUrl = $result['secure_url'];
    $publicId = $result['public_id'] ?? null;
    $resourceType = $result['resource_type'] ?? 'raw';

    // Return consistent JSON to frontend (add 'url' for compatibility)
    echo json_encode([
        'success' => true,
        'url' => $secureUrl,                // <-- thêm dòng này
        'data' => [
            'file_url' => $secureUrl,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
            'extension' => $ext,
            'provider' => 'cloudinary',
            'cloudinary' => [
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'format' => $result['format'] ?? null,
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
            ]
        ]
    ]);
} catch (Exception $e) {
    error_log("upload.php upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
    exit;
}