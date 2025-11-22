<?php
header("Access-Control-Allow-Origin: http://localhost:8080"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
header("Content-Type: application/json; charset=utf-8");

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = $_POST['search']['value'] ?? '';

$type = $_POST['type_filter'] ?? '';
$status = $_POST['status_filter'] ?? '';

$where = " WHERE 1=1 ";
$param = [];
$types = "";

if ($search !== "") {
    $where .= " AND (p.title LIKE ? OR p.content LIKE ?)";
    $param[] = "%$search%";
    $param[] = "%$search%";
    $types .= "ss";
}

if ($type !== "") {
    $where .= " AND p.category_id = ?";
    $param[] = $type;
    $types .= "s";
}

if ($status !== "") {
    $where .= " AND p.status = ?";
    $param[] = $status;
    $types .= "s";
}

$totalSQL = "SELECT COUNT(*) AS total FROM posts";
$total = $conn->query($totalSQL)->fetch_assoc()['total'];

$filterSQL = "SELECT COUNT(*) AS total FROM posts p $where";

if ($param) {
    $st = $conn->prepare($filterSQL);
    $st->bind_param($types, ...$param);
    $st->execute();
    $filtered = $st->get_result()->fetch_assoc()['total'];
    $st->close();
} else {
    $filtered = $conn->query($filterSQL)->fetch_assoc()['total'];
}

$query = "
    SELECT p.*, u.name AS author_name, u.avatar AS author_avatar,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes,
        (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comments,
        (SELECT COUNT(*) FROM post_reports WHERE post_id = p.id) AS reports,
        (SELECT image_url FROM post_images WHERE post_id = p.id LIMIT 1) AS thumbnail
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    $where
    ORDER BY p.created_at DESC
    LIMIT ?, ?
";

$param2 = $param;
$param2[] = $start;
$param2[] = $length;
$types2 = $types . "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types2, ...$param2);
$stmt->execute();
$rs = $stmt->get_result();

$data = [];

while ($row = $rs->fetch_assoc()) {

    $contentHTML = '
        <div class="d-flex align-items-start">
            <img src="'.($row['thumbnail'] ?: '/uploads/default.jpg').'" 
                width="60" height="60" class="rounded me-2" style="object-fit: cover;">
            <div>
                <strong>'.$row['title'].'</strong><br>
                <small>'.substr($row['content'], 0, 80).'...</small>
            </div>
        </div>
    ';

    $authorHTML = '
        <div class="d-flex align-items-center">
            <img src="'.($row['author_avatar'] ?: '/uploads/default-user.jpg').'"
                class="rounded-circle me-2" width="35" height="35">
            <span>'.$row['author_name'].'</span>
        </div>
    ';

    $statusBadge = '<span class="badge bg-'.
        ($row['status'] === 'public' ? 'success' :
        ($row['status'] === 'hidden' ? 'secondary' :
        ($row['status'] === 'deleted' ? 'danger' : 'warning'))).'">
        '.$row['status'].'</span>';

    $actions = '
        <button onclick="viewPost('.$row['id'].')" class="btn btn-sm btn-primary"><i class="bx bx-show"></i></button>
        <button onclick="toggleStatus('.$row['id'].')" class="btn btn-sm btn-warning"><i class="bx bx-hide"></i></button>
        <button onclick="deletePost('.$row['id'].')" class="btn btn-sm btn-danger"><i class="bx bx-trash"></i></button>
    ';

    $data[] = [
        $row['id'],
        $contentHTML,
        $authorHTML,
        $row['category_id'] ?: "-",
        '<span class="badge bg-info">'.$row['likes'].'</span> / <span class="badge bg-primary">'.$row['comments'].'</span>',
        $statusBadge,
        date('d/m/Y H:i', strtotime($row['created_at'])),
        $actions
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $total,
    "recordsFiltered" => $filtered,
    "data" => $data
]);
