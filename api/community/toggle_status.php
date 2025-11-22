<?php
require_once '../../config/database.php';
header("Content-Type: application/json");

$id = intval($_POST['post_id']);
$status = $_POST['status'];

$stmt = $conn->prepare("UPDATE posts SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);

echo json_encode([
    "success" => $stmt->execute(),
    "new_status" => $status
]);
