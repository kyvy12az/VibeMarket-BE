<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['status' => 'none']);
    exit;
}

$stmt = $conn->prepare("SELECT status FROM seller WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($status);
if ($stmt->fetch()) {
    echo json_encode(['status' => $status]);
} else {
    echo json_encode(['status' => 'none']);
}
$stmt->close();
$conn->close();