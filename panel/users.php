<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php');
    exit;
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Xử lý AJAX DataTables
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_users_data':
            $draw = intval($_POST['draw']);
            $start = intval($_POST['start']);
            $length = intval($_POST['length']);
            $search_value = $_POST['search']['value'] ?? '';

            $role_filter = $_POST['role_filter'] ?? '';
            $status_filter = $_POST['status_filter'] ?? '';

            $where_conditions = [];
            $params = [];
            $param_types = '';

            if (!empty($search_value)) {
                $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
                $search_param = "%{$search_value}%";
                $params = array_merge($params, [$search_param, $search_param, $search_param]);
                $param_types .= 'sss';
            }

            if (!empty($role_filter)) {
                $where_conditions[] = "role = ?";
                $params[] = $role_filter;
                $param_types .= 's';
            }

            if (!empty($status_filter)) {
                $where_conditions[] = "status = ?";
                $params[] = $status_filter;
                $param_types .= 's';
            }

            $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

            // Đếm tổng số bản ghi lọc
            $count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
            if (!empty($params)) {
                $count_stmt = $conn->prepare($count_sql);
                $count_stmt->bind_param($param_types, ...$params);
                $count_stmt->execute();
                $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
            } else {
                $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
            }

            // Lấy dữ liệu user
            $sql = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $length;
            $params[] = $start;
            $param_types .= 'ii';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $data = [];
            foreach ($users as $user) {
                $avatar = $user['avatar'] ?? 'img/avatars/default.jpg';
                $status_toggle = '<div class="form-check form-switch">
                    <input class="form-check-input status-toggle" type="checkbox"
                        data-user-id="' . $user['id'] . '"
                        ' . ($user['status'] === 'active' ? 'checked' : '') . '>
                </div>';

                $actions = '<div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-sm" onclick="viewUser(' . $user['id'] . ')">
                        <i class="bx bx-show"></i>
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="editUser(' . $user['id'] . ')">
                        <i class="bx bx-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteUser(' . $user['id'] . ')">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>';

                $user_info = '<div class="d-flex align-items-center">
                    <img src="' . $avatar . '" class="rounded-circle me-2" width="40" height="40" alt="Avatar">
                    <div>
                        <div class="fw-bold">' . htmlspecialchars($user['name']) . '</div>
                        <small class="text-muted">' . htmlspecialchars($user['phone'] ?? '') . '</small>
                    </div>
                </div>';

                $role_badge = '<span class="badge bg-' . ($user['role'] === 'seller' ? 'info' : 'secondary') . '">' . ucfirst($user['role']) . '</span>';

                $order_total = $conn->query("SELECT COUNT(*) as c FROM orders WHERE customer_id = {$user['id']}")->fetch_assoc()['c'];
                $order_stats_html = "<span class='badge bg-primary'>📦 $order_total đơn</span>";

                $data[] = [
                    $user['id'],
                    $user_info,
                    htmlspecialchars($user['email']),
                    $role_badge,
                    $status_toggle,
                    // $user['status'] === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Banned</span>',
                    $order_stats_html,
                    date('d/m/Y', strtotime($user['created_at'])),
                    $actions
                ];
            }

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'],
                'recordsFiltered' => $total_records,
                'data' => $data
            ]);
            exit;

        case 'toggle_status':
            $user_id = intval($_POST['user_id']);
            $new_status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $user_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'new_status' => $new_status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
            }
            exit;

        case 'delete_user':
            $user_id = intval($_POST['user_id']);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
            }
            exit;
    }
}

// Thống kê tổng số user
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['active'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
$stats['vendors'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'seller'")->fetch_assoc()['count'];
?>

<?php include "includes/header.php"; ?>
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main">
            <?php include 'includes/navbar.php'; ?>

            <main class="content">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0"><strong>Quản lý người dùng</strong></h1>
                        <button class="btn btn-primary" onclick="exportUsers()">
                            <i class="bx bx-download me-1"></i> Xuất Excel
                        </button>
                    </div>

                    <div class="row mb-4">
                        <div class="col-sm-6 col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Tổng số</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-primary">
                                                <i class="bx bx-user"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['total']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Đang hoạt động</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-success">
                                                <i class="bx bx-check-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['active']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Cửa Hàng</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-info">
                                                <i class="bx bx-store"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['vendors']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <!-- <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Online</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-warning">
                                                <i class="bx bx-signal-5"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['online']); ?></h1>
                                </div>
                            </div>
                        </div> -->
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tìm kiếm</label>
                                    <input type="text" id="searchInput" class="form-control"
                                        placeholder="Tìm kiếm tên, email, SĐT...">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Vai trò</label>
                                    <select id="roleFilter" class="form-select">
                                        <option value="">Tất cả vai trò</option>
                                        <option value="user">User</option>
                                        <option value="seller">Seller</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Trạng thái</label>
                                    <select id="statusFilter" class="form-select">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="active">Active</option>
                                        <option value="banned">Banned</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" id="resetFilters" class="btn btn-outline-secondary">
                                        <i class="bx bx-refresh me-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Danh sách người dùng</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Người dùng</th>
                                            <th>Email</th>
                                            <th>Vai trò</th>
                                            <th>Trạng thái</th>
                                            <th>Thống kê</th>
                                            <th>Ngày tạo</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let usersTable;

        $(document).ready(function () {
            usersTable = $('#usersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/users.php',
                    type: 'POST',
                    data: function (d) {
                        d.action = 'get_users_data';
                        d.role_filter = $('#roleFilter').val();
                        d.status_filter = $('#statusFilter').val();
                        return d;
                    }
                },
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json",
                    processing: "Đang tải dữ liệu...",
                    emptyTable: "Không có dữ liệu",
                    info: "Hiển thị _START_ đến _END_ của _TOTAL_ bản ghi",
                    infoEmpty: "Hiển thị 0 đến 0 của 0 bản ghi",
                    infoFiltered: "(lọc từ _MAX_ bản ghi)",
                    lengthMenu: "Hiển thị _MENU_ bản ghi",
                    search: "Tìm kiếm:",
                    paginate: {
                        first: "Đầu",
                        last: "Cuối",
                        next: "Tiếp",
                        previous: "Trước"
                    }
                },
                columns: [{
                    data: 0,
                    name: 'id',
                    width: '5%'
                },
                {
                    data: 1,
                    name: 'user_info',
                    orderable: false,
                    width: '20%'
                },
                {
                    data: 2,
                    name: 'email',
                    width: '15%'
                },
                {
                    data: 3,
                    name: 'role',
                    width: '8%'
                },
                {
                    data: 4,
                    name: 'status',
                    orderable: false,
                    width: '8%'
                },
                {
                    data: 5,
                    name: 'stats',
                    orderable: false,
                    width: '12%'
                },
                {
                    data: 6,
                    name: 'created_at',
                    width: '10%'
                },
                {
                    data: 7,
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    width: '12%'
                }
                ],
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                order: [
                    [0, 'desc']
                ],
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'excel',
                    text: '<i class="bx bx-download"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 5, 7]
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="bx bx-file-blank"></i> PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 5, 7]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="bx bx-printer"></i> In',
                    className: 'btn btn-info btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 5, 7]
                    }
                }
                ],
                drawCallback: function () {
                    bindEventHandlers();
                }
            });

            $('#searchInput').on('keyup', function () {
                usersTable.search(this.value).draw();
            });

            $('#roleFilter, #statusFilter').on('change', function () {
                usersTable.ajax.reload();
            });

            $('#resetFilters').on('click', function () {
                $('#searchInput').val('');
                $('#roleFilter').val('');
                $('#statusFilter').val('');
                usersTable.search('').ajax.reload();
            });
        });

        function bindEventHandlers() {
            $('.status-toggle').off('change').on('change', function () {
                const userId = $(this).data('user-id');
                const status = this.checked ? 'active' : 'banned';
                const toggleElement = this;

                fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_status&user_id=${userId}&status=${status}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành công!',
                                text: 'Đã cập nhật trạng thái người dùng',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            toggleElement.checked = !toggleElement.checked;
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi!',
                                text: data.message || 'Có lỗi xảy ra'
                            });
                        }
                    })
                    .catch(error => {
                        toggleElement.checked = !toggleElement.checked;
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: 'Có lỗi xảy ra khi gửi yêu cầu'
                        });
                    });
            });
        }

        function deleteUser(userId) {
            Swal.fire({
                title: 'Bạn có chắc chắn?',
                text: "Hành động này không thể hoàn tác!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Có, xóa!',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_user&user_id=${userId}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã xóa!',
                                    text: 'Người dùng đã được xóa.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    usersTable.ajax.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Lỗi!',
                                    text: data.message || 'Có lỗi xảy ra'
                                });
                            }
                        });
                }
            });
        }

        function viewUser(userId) {
            window.open(`/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/user-detail.php?id=${userId}`, '_blank');
        }

        function editUser(userId) {
            window.location.href = `/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/user-edit.php?id=${userId}`;
        }

        function exportUsers() {
            window.open(`/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/users-export.php`, '_blank');
        }
    </script>
</body>

</html>