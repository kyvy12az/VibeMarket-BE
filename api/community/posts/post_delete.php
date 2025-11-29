<?php
require_once __DIR__ . '/../../../config/database.php';
header("Content-Type: application/json");

$id = intval($_POST['post_id']);

$stmt = $conn->prepare("UPDATE posts SET status = 'deleted' WHERE id = ?");
$stmt->bind_param("i", $id);

echo json_encode([
    "success" => $stmt->execute()
]);
