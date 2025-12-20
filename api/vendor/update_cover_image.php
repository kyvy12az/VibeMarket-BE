<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ob_start();

try {
    // Include database and helper
    require_once '../../config/database.php';
    require_once '../../config/url_helper.php';
    
    if (!isset($conn)) {
        throw new Exception("Database connection failed");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    }

    if (!isset($_FILES['cover_image']) || !isset($_POST['user_id'])) {
        throw new Exception("Missing required data");
    }

    $user_id = intval($_POST['user_id']);
    $cover_image = $_FILES['cover_image'];

    if ($user_id <= 0) {
        throw new Exception("Invalid user ID");
    }

    // Check seller account
    $stmt = $conn->prepare("SELECT seller_id FROM seller WHERE user_id = ? AND status = 'approved'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception("Seller account not found or not approved");
    }
    $stmt->close();

    // Validate file upload
    if ($cover_image['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds PHP limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        throw new Exception($error_messages[$cover_image['error']] ?? "Upload error: " . $cover_image['error']);
    }

    // Validate file type
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $cover_image['tmp_name']);
    finfo_close($file_info);
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception("Unsupported file type: " . $mime_type);
    }

    // Check file size (5MB)
    if ($cover_image['size'] > 5 * 1024 * 1024) {
        throw new Exception("File size too large: " . round($cover_image['size'] / 1024 / 1024, 2) . "MB");
    }

    // Get upload directory using helper
    $upload_dir = getUploadDirectory('cover');

    // Generate unique filename
    $file_extension = strtolower(pathinfo($cover_image['name'], PATHINFO_EXTENSION));
    $filename = "cover_" . $user_id . "_" . time() . "_" . mt_rand(1000, 9999) . "." . $file_extension;
    $target_path = $upload_dir . $filename;

    // Get current cover image to delete old one
    $stmt = $conn->prepare("SELECT cover_image FROM seller WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_data = $result->fetch_assoc();
    $old_cover = $old_data ? $old_data['cover_image'] : null;
    $stmt->close();

    // Upload new file
    if (!move_uploaded_file($cover_image['tmp_name'], $target_path)) {
        throw new Exception("Failed to move uploaded file");
    }

    // Verify file was uploaded successfully
    if (!file_exists($target_path)) {
        throw new Exception("File upload verification failed");
    }

    // Update database - chỉ lưu filename
    $stmt = $conn->prepare("UPDATE seller SET cover_image = ? WHERE user_id = ?");
    $stmt->bind_param("si", $filename, $user_id);
    
    if (!$stmt->execute()) {
        if (file_exists($target_path)) {
            unlink($target_path);
        }
        throw new Exception("Failed to update database: " . $stmt->error);
    }
    $stmt->close();

    // Verify database update
    $stmt = $conn->prepare("SELECT cover_image FROM seller WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_data = $result->fetch_assoc();
    $new_cover = $new_data ? $new_data['cover_image'] : null;
    $stmt->close();
    
    if ($new_cover !== $filename) {
        if (file_exists($target_path)) {
            unlink($target_path);
        }
        throw new Exception("Database update verification failed");
    }

    // Delete old cover image using helper
    if ($old_cover && $old_cover !== $filename) {
        deleteOldFile($old_cover, 'cover');
    }

    ob_clean();

    // Create full URL using helper
    $full_cover_url = getFullImageUrl('vendor/covers/' . $filename);

    $conn->close();

    echo json_encode([
        "success" => true,
        "cover_url" => $full_cover_url,
        "filename" => $filename,
        "message" => "Cover image updated successfully"
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log("Cover upload error: " . $e->getMessage());
    
    if (isset($conn)) {
        $conn->close();
    }
    
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    error_log("Cover upload fatal error: " . $e->getMessage());
    
    if (isset($conn)) {
        $conn->close();
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Internal server error"
    ]);
}

ob_end_flush();
?>