<?php
// Save uploads to specific folders and return absolute URLs

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
$frontend = $_ENV['FRONTEND_URL'] ?? $_SERVER['FRONTEND_URL'] ?? getenv('FRONTEND_URL') ?: '*';
header("Access-Control-Allow-Origin: {$frontend}");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($_FILES)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit;
}

// Xác định loại upload từ parameter hoặc default là products
$uploadType = $_POST['type'] ?? 'products'; // products, store_avatars, etc.

// uploads dir (one level up from /api)
$baseUploadsDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'uploads';
$uploadsDir = $baseUploadsDir . DIRECTORY_SEPARATOR . $uploadType;

if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0755, true)) {
        error_log("upload.php: failed to create uploads dir: {$uploadsDir}");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Cannot create uploads directory']);
        exit;
    }
}

// allowed extensions / mime types
$allowedExts = [
    'jpg','jpeg','png','gif','webp','bmp','svg',
    'mp4','mov','avi','mkv',
    'mp3','wav','ogg',
    'pdf','zip','rar','txt','csv',
    'doc','docx','xls','xlsx','ppt','pptx'
];
$maxSize = 20 * 1024 * 1024; // 20MB

$uploaded = [];
$errors = [];
$fullUrls = []; // will contain absolute URLs for DB storage

// build base URL depending on host
function get_base_url_for_storage() {
    $proto = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        $proto = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // local dev mapping
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $base = $proto . '://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE';
    } else {
        // production
        if (strpos($host, 'komer.id.vn') !== false) {
            $baseHost = 'komer.id.vn';
            $base = $proto . '://' . $baseHost;
        } else {
            $base = $proto . '://' . $host;
        }
    }
    return rtrim($base, '/');
}

// Normalize incoming files
foreach ($_FILES as $field => $info) {
    if (is_array($info['name'])) {
        $count = count($info['name']);
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $info['name'][$i],
                'type' => $info['type'][$i],
                'tmp_name' => $info['tmp_name'][$i],
                'error' => $info['error'][$i],
                'size' => $info['size'][$i],
            ];
            processFile($file, $uploadsDir, $uploadType, $allowedExts, $maxSize, $uploaded, $errors, $fullUrls);
        }
    } else {
        processFile($info, $uploadsDir, $uploadType, $allowedExts, $maxSize, $uploaded, $errors, $fullUrls);
    }
}

$response = [
    'success' => !empty($uploaded),
    'uploaded_count' => count($uploaded),
    'total_count' => count($uploaded) + count($errors),
    'data' => $uploaded,
    'urls' => $fullUrls, // array of absolute URLs for DB
    'upload_type' => $uploadType
];
if (!empty($errors)) $response['errors'] = $errors;

if (empty($uploaded)) {
    http_response_code(400);
}
echo json_encode($response);
exit;

// helper
function processFile($file, $uploadsDir, $uploadType, $allowedExts, $maxSize, &$uploaded, &$errors, &$fullUrls) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File {$file['name']}: upload error code " . ($file['error'] ?? 'n/a');
        return;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = "File {$file['name']}: not an uploaded file";
        return;
    }
    if ($file['size'] > $maxSize) {
        $errors[] = "File {$file['name']}: exceeds max size";
        return;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        $errors[] = "File {$file['name']}: invalid file type ({$ext})";
        return;
    }

    $baseName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $unique = time() . '_' . bin2hex(random_bytes(6));
    $newName = $baseName . '_' . $unique . ($ext ? ('.' . $ext) : '');
    $destPath = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $errors[] = "File {$file['name']}: failed to move uploaded file";
        return;
    }

    // relative path: uploads/products/filename.jpg
    $relativePath = 'uploads/' . $uploadType . '/' . $newName;

    // absolute URL for DB storage
    $baseUrl = get_base_url_for_storage();
    $absoluteUrl = $baseUrl . '/' . $relativePath;

    $uploaded[] = [
        'file_url' => $relativePath,
        'url' => $absoluteUrl,
        'original_name' => $file['name'],
        'file_size' => $file['size'],
        'mime_type' => $file['type'],
        'extension' => $ext,
        'provider' => 'local'
    ];

    $fullUrls[] = $absoluteUrl;
}