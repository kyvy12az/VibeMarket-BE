<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ================= CORS (UPLOAD SAFE) ================= */
// ⚠️ Upload cần CORS MỞ – nếu không $_FILES sẽ rỗng
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ================= CONFIG ================= */
$typeMap = [
    'products'       => 'products',
    'license_image'  => 'vendor/license',
    'idcard_image'   => 'vendor/idcard',
    'avatar'         => 'avatar',
];

$allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
$maxSize = 10 * 1024 * 1024; // 10MB

/* ================= VALIDATE ================= */
if (empty($_FILES)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No files uploaded',
        'debug'   => $_POST
    ]);
    exit;
}

/* ================= TYPE ================= */
$type = $_POST['type'] ?? 'products';
$subDir = $typeMap[$type] ?? 'products';

/* ================= PATH ================= */
// root = /api
$root = realpath(__DIR__ . '/../');
$uploadDir = $root . '/uploads/' . $subDir;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

/* ================= PROCESS ================= */
$paths = [];

foreach ($_FILES as $input) {

    // hỗ trợ cả single & multiple
    $isMulti = is_array($input['name']);
    $count = $isMulti ? count($input['name']) : 1;

    for ($i = 0; $i < $count; $i++) {

        $name = $isMulti ? $input['name'][$i] : $input['name'];
        $tmp  = $isMulti ? $input['tmp_name'][$i] : $input['tmp_name'];
        $size = $isMulti ? $input['size'][$i] : $input['size'];
        $err  = $isMulti ? $input['error'][$i] : $input['error'];

        if ($err !== UPLOAD_ERR_OK || $size > $maxSize) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) continue;

        $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $dest)) continue;

        // 👉 PATH CHUẨN ĐỂ LƯU DB
        $paths[] = '/uploads/' . $subDir . '/' . $filename;
    }
}

/* ================= RESULT ================= */
if (empty($paths)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Upload failed'
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'paths'   => $paths
], JSON_UNESCAPED_SLASHES);
