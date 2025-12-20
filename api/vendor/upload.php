<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_FILES['file']) || !isset($_POST['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu file hoặc type']);
    exit;
}

$type = $_POST['type']; // 'license' hoặc 'idcard'
$file = $_FILES['file'];

// Validate type
if (!in_array($type, ['license', 'idcard'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type không hợp lệ']);
    exit;
}

// Set upload directory based on type
$uploadDir = '../../uploads/vendor/' . $type . '/';

// Create directory if not exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validate file
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Định dạng file không hợp lệ. Chỉ chấp nhận JPG, PNG, GIF, PDF']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File quá lớn. Tối đa 5MB']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $filename;

// Move file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Return relative URL
    $relativeUrl = '/uploads/vendor/' . $type . '/' . $filename;
    echo json_encode([
        'success' => true,
        'url' => $relativeUrl,
        'message' => 'Upload thành công'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi upload file']);
}
