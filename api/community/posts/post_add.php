<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/jwt.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$userId = get_user_id_from_token();
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Không xác thực"]);
    exit;
}

$content           = $_POST['content'] ?? '';
$tags              = isset($_POST['tags']) ? json_decode($_POST['tags'], true) : [];
$featuredProducts  = isset($_POST['featured_products'])
    ? json_decode($_POST['featured_products'], true)
    : [];

if (!$content) {
    echo json_encode(["success" => false, "message" => "Thiếu nội dung"]);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO posts (user_id, content, status)
        VALUES (?, ?, 'pending')
    ");
    $stmt->bind_param("is", $userId, $content);
    $stmt->execute();
    $postId = $stmt->insert_id;

    if (!empty($tags)) {
        $tagStmt = $conn->prepare("
            INSERT INTO post_tags (post_id, tag)
            VALUES (?, ?)
        ");
        foreach ($tags as $t) {
            $tag = trim($t);
            if ($tag === '') continue;

            $tagStmt->bind_param("is", $postId, $tag);
            $tagStmt->execute();
        }
    }

    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir  = "/uploads/posts/";
        $serverPath = __DIR__ . "/../../../uploads/posts/";

        if (!is_dir($serverPath)) {
            mkdir($serverPath, 0777, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if (!is_uploaded_file($tmp)) continue;

            $originalName = basename($_FILES['images']['name'][$i]);
            $name   = uniqid("post_", true) . "_" . $originalName;
            $target = $serverPath . $name;

            if (move_uploaded_file($tmp, $target)) {
                $url = $uploadDir . $name;

                $imgStmt = $conn->prepare("
                    INSERT INTO post_images (post_id, image_url)
                    VALUES (?, ?)
                ");
                $imgStmt->bind_param("is", $postId, $url);
                $imgStmt->execute();
            }
        }
    }

    if (!empty($featuredProducts)) {
        $fpStmt = $conn->prepare("
            INSERT INTO post_featured_products (post_id, product_id)
            VALUES (?, ?)
        ");

        foreach ($featuredProducts as $p) {
            $productId = isset($p['id']) ? (int)$p['id'] : 0;
            if ($productId <= 0) continue;

            $fpStmt->bind_param("ii", $postId, $productId);
            $fpStmt->execute();
        }
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "post_id" => $postId,
        "status" => "pending",
        "message" => "Bài viết của bạn đang chờ duyệt. Chúng tôi sẽ xem xét và phê duyệt trong thời gian sớm nhất."
    ]);
} catch (Exception $e) {
    $conn->rollback();

    echo json_encode([
        "success" => false,
        "message" => "Lỗi khi tạo bài viết",
        "error"   => $e->getMessage()
    ]);
}
