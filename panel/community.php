<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php');
    exit;
}

$BASE_URL = "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header("Content-Type: application/json; charset=utf-8");

    $action = $_POST['action'];
    if ($action === 'get_posts_data') {
        $draw   = intval($_POST['draw'] ?? 0);
        $start  = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $search_value = $_POST['search']['value'] ?? '';

        $type_filter   = $_POST['type_filter'] ?? '';
        $status_filter = $_POST['status_filter'] ?? '';
        $typeMap = [
            'review'    => 1,
            'question'  => 2,
            'sharing'   => 3,
            'livestream' => 4,
        ];
        $where = " WHERE p.status != 'deleted' ";
        if ($search_value !== '') {
            $safe = $conn->real_escape_string($search_value);
            $where .= " AND (p.title LIKE '%$safe%' OR p.content LIKE '%$safe%') ";
        }
        if ($type_filter !== '' && isset($typeMap[$type_filter])) {
            $catId = intval($typeMap[$type_filter]);
            $where .= " AND p.category_id = $catId ";
        }
        if ($status_filter !== '') {
            if ($status_filter === 'reported') {
                $where .= " AND EXISTS (
                    SELECT 1 FROM post_reports r
                    WHERE r.post_id = p.id
                )";
            } else {
                $safeStatus = $conn->real_escape_string($status_filter);
                $where .= " AND p.status = '$safeStatus' ";
            }
        }
        $sql_count = "SELECT COUNT(*) AS count FROM posts p $where";
        $total_res = $conn->query($sql_count);
        $total_row = $total_res ? $total_res->fetch_assoc() : ['count' => 0];
        $total = (int)$total_row['count'];
        $sql = "
            SELECT 
                p.id,
                p.title,
                p.content,
                p.created_at,
                p.status,
                p.category_id,
                u.name   AS author_name,
                u.avatar AS author_avatar,
                (SELECT COUNT(*) FROM post_likes    WHERE post_id = p.id) AS likes,
                (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comments,
                (SELECT image_url FROM post_images  WHERE post_id = p.id LIMIT 1) AS thumbnail
            FROM posts p
            LEFT JOIN users u ON u.id = p.user_id
            $where
            ORDER BY p.id DESC
            LIMIT $start, $length
        ";

        $rows = $conn->query($sql);
        $data = [];

        while ($r = $rows->fetch_assoc()) {
            $thumbPath = $r['thumbnail'] ?: 'img/default_post.jpg';
            if (preg_match('/^https?:\/\//', $thumbPath)) {
                $thumbUrl = $thumbPath;
            } else {
                $thumbUrl = $BASE_URL . '/' . ltrim($thumbPath, '/');
            }
            $avatarPath = $r['author_avatar'] ?: 'img/avatars/default.jpg';
            if (preg_match('/^https?:\/\//', $avatarPath)) {
                $avatarUrl = $avatarPath;
            } else {
                $avatarUrl = $BASE_URL . '/' . ltrim($avatarPath, '/');
            }

            $typeLabel = 'Chia sẻ';
            switch ((int)$r['category_id']) {
                case 1:
                    $typeLabel = 'Review';
                    break;
                case 2:
                    $typeLabel = 'Câu hỏi';
                    break;
                case 3:
                    $typeLabel = 'Chia sẻ';
                    break;
                case 4:
                    $typeLabel = 'Livestream';
                    break;
            }

            $content_html = '
                <div class="d-flex align-items-start">
                    <img src="' . htmlspecialchars($thumbUrl) . '" 
                         width="60" height="60" 
                         class="rounded me-2" 
                         style="object-fit:cover">
                    <div>
                        <div class="fw-bold">' . htmlspecialchars($r['title']) . '</div>
                        <small class="text-muted">'
                . htmlspecialchars(mb_strimwidth($r['content'], 0, 60, '...')) .
                '</small>
                    </div>
                </div>';
            $author_html = '
                <div class="d-flex align-items-center">
                    <img src="' . htmlspecialchars($avatarUrl) . '" 
                         width="32" height="32" 
                         class="rounded-circle me-2"
                         style="object-fit:cover">
                    <span>' . htmlspecialchars($r['author_name'] ?? 'User') . '</span>
                </div>';
            $interaction_html = '
                <span class="badge bg-primary me-1">❤️ ' . (int)$r['likes'] . '</span>
                <span class="badge bg-success">💬 ' . (int)$r['comments'] . '</span>';
            $checked = $r['status'] === 'public' ? 'checked' : '';
            $status_html = '
                <div class="form-check form-switch">
                    <input class="form-check-input status-toggle" type="checkbox"
                           data-post-id="' . (int)$r['id'] . '" ' . $checked . '>
                </div>';
            $actions_html = '
                <button class="btn btn-sm btn-outline-danger" onclick="deletePost(' . (int)$r['id'] . ')">
                    <i class="bx bx-trash"></i>
                </button>';

            $data[] = [
                (int)$r['id'],
                $content_html,
                $author_html,
                '<span class="badge bg-info">' . $typeLabel . '</span>',
                $interaction_html,
                $status_html,
                date('d/m/Y', strtotime($r['created_at'])),
                $actions_html
            ];
        }

        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data
        ]);
        exit;
    }
    if ($action === 'toggle_status') {
        $id         = intval($_POST['post_id'] ?? 0);
        $new_status = $_POST['status'] ?? 'public';

        if (!$id || !in_array($new_status, ['public', 'hidden'], true)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE posts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok]);
        exit;
    }
    if ($action === 'delete_post') {
        $id = intval($_POST['post_id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Thiếu ID bài viết']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE posts SET status = 'deleted' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
    exit;
}

$stats = [
    'total_posts' => 0,
    'questions'   => 0,
    'reviews'     => 0,
    'reported'    => 0,
];

$res = $conn->query("SELECT COUNT(*) AS c FROM posts WHERE status != 'deleted'");
$stats['total_posts'] = (int)($res->fetch_assoc()['c'] ?? 0);

$res = $conn->query("SELECT COUNT(*) AS c FROM posts WHERE category_id = 1 AND status != 'deleted'");
$stats['reviews'] = (int)($res->fetch_assoc()['c'] ?? 0);

$res = $conn->query("SELECT COUNT(*) AS c FROM posts WHERE category_id = 2 AND status != 'deleted'");
$stats['questions'] = (int)($res->fetch_assoc()['c'] ?? 0);

$res = $conn->query("SELECT COUNT(DISTINCT post_id) AS c FROM post_reports");
$stats['reported'] = (int)($res->fetch_assoc()['c'] ?? 0);

?>
<?php include "includes/header.php"; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" />

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main">
            <?php include 'includes/navbar.php'; ?>

            <main class="content">
                <div class="container-fluid p-0">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0"><strong>Quản lý bài viết cộng đồng</strong></h1>
                    </div>
                    <div class="row mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Tổng bài viết</h5>
                                    <h1><?php echo number_format($stats['total_posts']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Câu hỏi</h5>
                                    <h1><?php echo number_format($stats['questions']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Reviews</h5>
                                    <h1><?php echo number_format($stats['reviews']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Báo cáo</h5>
                                    <h1 class="text-danger"><?php echo number_format($stats['reported']); ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tìm kiếm</label>
                                    <input id="searchInput" class="form-control" placeholder="Tìm tiêu đề, nội dung...">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Loại bài viết</label>
                                    <select id="typeFilter" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="review">Review</option>
                                        <option value="question">Câu hỏi</option>
                                        <option value="sharing">Chia sẻ</option>
                                        <option value="livestream">Livestream</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select id="statusFilter" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="public">Hiển thị</option>
                                        <option value="hidden">Đã ẩn</option>
                                        <option value="reported">Bị báo cáo</option>
                                    </select>
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <button id="resetFilters" class="btn btn-outline-secondary w-100">
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <table id="postsTable" class="table table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nội dung bài viết</th>
                                        <th>Tác giả</th>
                                        <th>Loại</th>
                                        <th>Tương tác</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        let postsTable;

        $(document).ready(function() {
            postsTable = $('#postsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/community.php',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_posts_data';
                        d.type_filter = $('#typeFilter').val();
                        d.status_filter = $('#statusFilter').val();
                        return d;
                    }
                },
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json"
                },
                drawCallback: function() {
                    bindEventHandlers();
                }
            });

            $('#searchInput').on('keyup', function() {
                postsTable.search(this.value).draw();
            });

            $('#typeFilter, #statusFilter').on('change', function() {
                postsTable.ajax.reload();
            });

            $('#resetFilters').on('click', function() {
                $('#searchInput').val('');
                $('#typeFilter').val('');
                $('#statusFilter').val('');
                postsTable.search('').ajax.reload();
            });
        });

        function bindEventHandlers() {
            $('.status-toggle').off('change').on('change', function() {
                const postId = $(this).data('post-id');
                const status = this.checked ? 'public' : 'hidden';

                fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/community.php', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: `action=toggle_status&post_id=${postId}&status=${status}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            this.checked = !this.checked;
                            alert('Không thể cập nhật trạng thái');
                        }
                    })
                    .catch(() => {
                        this.checked = !this.checked;
                        alert('Lỗi mạng');
                    });
            });
        }

        function deletePost(id) {
            if (!confirm("Xóa bài viết này? Hành động không thể hoàn tác!")) return;

            fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/community.php', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: `action=delete_post&post_id=${id}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        postsTable.ajax.reload();
                    } else {
                        alert('Không thể xóa bài viết');
                    }
                })
                .catch(() => alert('Lỗi mạng'));
        }
    </script>

</body>

</html>