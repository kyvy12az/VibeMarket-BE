<?php
// Updated to use official Cloudinary PHP SDK (cloudinary/cloudinary_php)
// Requires: composer install (cloudinary/cloudinary_php, vlucas/phpdotenv)
// .env must contain CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET, FRONTEND_URL (optional)

require_once '../../vendor/autoload.php';
require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../config/file_security.php';
require_once '../../config/rate_limit.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Cloudinary\Cloudinary;

header('Content-Type: application/json; charset=utf-8');

// load .env
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

// CORS - use FRONTEND_URL from env if set, otherwise allow all (for dev)
$frontend = $_ENV['FRONTEND_URL'] ?? $_SERVER['FRONTEND_URL'] ?? getenv('FRONTEND_URL') ?: '*';
header("Access-Control-Allow-Origin: {$frontend}");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Accept");
header("Access-Control-Allow-Credentials: true");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Basic request logging for debug
error_log("upload.php START | METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'n/a') . " | URI=" . ($_SERVER['REQUEST_URI'] ?? ''));

// enforce POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ hỗ trợ POST']);
    exit;
}

// Authorization header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Thiếu token']);
    exit;
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
    exit;
}

$jwt = $matches[1];
try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->sub;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token hết hạn hoặc không hợp lệ']);
    exit;
}

try {
    // Debug incoming
    error_log("upload.php incoming headers: " . var_export(getallheaders(), true));
    error_log("upload.php _FILES: " . var_export($_FILES, true));
    error_log("upload.php CONTENT_LENGTH: " . ($_SERVER['CONTENT_LENGTH'] ?? 'n/a'));

    if (empty($_FILES)) {
        throw new Exception('Không có file nào được upload (PHP $_FILES trống). Kiểm tra Network tab & php.ini (post_max_size, upload_max_filesize, max_file_uploads).');
    }

    // Allowed types & limits
    $maxSize = 20 * 1024 * 1024;
    $maxFiles = 10;
    $allowedTypes = [
        'image/jpeg','image/png','image/gif','image/webp','image/bmp','image/svg+xml',
        'application/pdf','text/plain','text/csv',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip','application/x-rar-compressed',
        'video/mp4','video/avi','video/quicktime',
        'audio/mpeg','audio/wav','audio/ogg'
    ];

    $uploadedFiles = [];
    $errors = [];
    $files = [];

    // Normalize $_FILES (accept any field)
    foreach ($_FILES as $field => $fileInfo) {
        if (is_array($fileInfo['name'])) {
            $count = count($fileInfo['name']);
            if ($count > $maxFiles) {
                $errors[] = "Chỉ có thể upload tối đa {$maxFiles} files cùng lúc";
                $count = $maxFiles;
            }
            for ($i = 0; $i < $count; $i++) {
                if ($fileInfo['error'][$i] === UPLOAD_ERR_OK) {
                    $files[] = [
                        'name' => $fileInfo['name'][$i],
                        'tmp_name' => $fileInfo['tmp_name'][$i],
                        'size' => $fileInfo['size'][$i],
                        'type' => $fileInfo['type'][$i],
                        'error' => $fileInfo['error'][$i]
                    ];
                } else {
                    $errors[] = "File {$fileInfo['name'][$i]}: " . getUploadError($fileInfo['error'][$i]);
                }
            }
        } else {
            if ($fileInfo['error'] === UPLOAD_ERR_OK) {
                $files[] = [
                    'name' => $fileInfo['name'],
                    'tmp_name' => $fileInfo['tmp_name'],
                    'size' => $fileInfo['size'],
                    'type' => $fileInfo['type'],
                    'error' => $fileInfo['error']
                ];
            } else {
                $errors[] = "File {$fileInfo['name']}: " . getUploadError($fileInfo['error']);
            }
        }
    }

    if (empty($files)) {
        throw new Exception('Không có file hợp lệ để upload (tất cả file bị lỗi). Xem error_log để biết chi tiết.');
    }

    // Cloudinary config (robust read)
    $cloudName  = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? $_SERVER['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME') ?: null;
    $apiKey     = $_ENV['CLOUDINARY_API_KEY'] ?? $_SERVER['CLOUDINARY_API_KEY'] ?? getenv('CLOUDINARY_API_KEY') ?: null;
    $apiSecret  = $_ENV['CLOUDINARY_API_SECRET'] ?? $_SERVER['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET') ?: null;
    $cloudFolder = $_ENV['CLOUDINARY_UPLOAD_FOLDER'] ?? $_SERVER['CLOUDINARY_UPLOAD_FOLDER'] ?? getenv('CLOUDINARY_UPLOAD_FOLDER') ?: 'chat';

    error_log("upload.php env check: CLOUDINARY_CLOUD_NAME=" . var_export($cloudName, true) . " CLOUDINARY_API_KEY=" . var_export($apiKey, true) . " CLOUDINARY_API_SECRET_SET=" . (!empty($apiSecret) ? '1' : '0'));

    if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Cloudinary chưa được cấu hình. Vui lòng thiết lập CLOUDINARY_CLOUD_NAME / CLOUDINARY_API_KEY / CLOUDINARY_API_SECRET trong .env']);
        exit;
    }

    // Initialize Cloudinary client (SDK)
    $cloudinary = new Cloudinary([
        'cloud' => [
            'cloud_name' => $cloudName,
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ],
        'url' => ['secure' => true],
    ]);

    foreach ($files as $file) {
        try {
            if ($file['size'] > $maxSize) {
                $errors[] = "File {$file['name']}: Quá lớn (tối đa 20MB)";
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = "File {$file['name']}: Loại file không được hỗ trợ ({$mimeType})";
                continue;
            }

            if (strpos($mimeType, 'image/') === 0) {
                $imageValidation = FileUploadSecurity::validateImageFile(
                    $file['tmp_name'],
                    $file['name'],
                    $file['size']
                );

                if (!$imageValidation['valid']) {
                    $errors[] = "File {$file['name']}: " . implode(', ', $imageValidation['errors']);
                    continue;
                }
                $extension = $imageValidation['extension'];
            } else {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            }

            // Upload using SDK (server-side signed upload)
            $options = [
                'folder' => $cloudFolder,
                'use_filename' => true,
                'unique_filename' => false,
                'resource_type' => 'auto'
            ];

            // tmp file path is acceptable
            $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], $options);

            if (empty($uploadResult) || !isset($uploadResult['secure_url'])) {
                throw new Exception('Cloudinary trả về kết quả không hợp lệ');
            }

            $secureUrl = $uploadResult['secure_url'];
            $resourceType = $uploadResult['resource_type'] ?? 'raw';
            $fileType = getFileType($mimeType);

            $uploadedFiles[] = [
                'file_url' => $secureUrl,
                'file_type' => $fileType,
                'file_size' => $file['size'],
                'original_name' => $file['name'],
                'mime_type' => $mimeType,
                'extension' => $extension,
                'provider' => 'cloudinary',
                'cloudinary' => [
                    'public_id' => $uploadResult['public_id'] ?? null,
                    'resource_type' => $resourceType,
                    'width' => $uploadResult['width'] ?? null,
                    'height' => $uploadResult['height'] ?? null,
                    'format' => $uploadResult['format'] ?? null,
                ]
            ];
        } catch (Exception $e) {
            error_log("upload.php file upload error: " . $e->getMessage());
            $errors[] = "File {$file['name']}: " . $e->getMessage();
        }
    }

    $response = [
        'success' => !empty($uploadedFiles),
        'data' => $uploadedFiles,
        'uploaded_count' => count($uploadedFiles),
        'total_count' => count($files)
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = 'Một số file upload thất bại';
    }

    if (empty($uploadedFiles)) {
        http_response_code(400);
        $response['message'] = 'Không có file nào được upload thành công';
    }

    echo json_encode($response);
} catch (Exception $e) {
    error_log("upload.php FATAL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Helpers (unchanged)
function getUploadError($errorCode)
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File quá lớn (vượt quá upload_max_filesize)';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File quá lớn (vượt quá MAX_FILE_SIZE)';
        case UPLOAD_ERR_PARTIAL:
            return 'File chỉ được upload một phần';
        case UPLOAD_ERR_NO_FILE:
            return 'Không có file được upload';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Thiếu thư mục tạm';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Không thể ghi file';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload bị dừng bởi extension';
        default:
            return 'Lỗi không xác định';
    }
}

function getFileType($mimeType)
{
    if (strpos($mimeType, 'image/') === 0) {
        return 'image';
    } elseif (strpos($mimeType, 'video/') === 0) {
        return 'video';
    } elseif (strpos($mimeType, 'audio/') === 0) {
        return 'audio';
    } elseif (in_array($mimeType, [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ])) {
        return 'document';
    } elseif (in_array($mimeType, [
        'application/zip',
        'application/x-rar-compressed'
    ])) {
        return 'archive';
    } else {
        return 'file';
    }
}