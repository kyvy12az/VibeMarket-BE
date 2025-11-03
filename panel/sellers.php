<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_sellers_data':
            $draw = intval($_POST['draw']);
            $start = intval($_POST['start']);
            $length = intval($_POST['length']);
            $search_value = $_POST['search']['value'] ?? '';
            $status_filter = $_POST['status_filter'] ?? '';

            $where_conditions = [];
            $params = [];
            $param_types = '';

            if (!empty($search_value)) {
                $where_conditions[] = "(s.store_name LIKE ? OR s.business_address LIKE ? OR s.phone LIKE ? OR u.name LIKE ?)";
                $search_param = "%{$search_value}%";
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
                $param_types .= 'ssss';
            }

            if (!empty($status_filter)) {
                $where_conditions[] = "s.status = ?";
                $params[] = $status_filter;
                $param_types .= 's';
            }

            $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

            // Đếm tổng số bản ghi lọc
            $count_sql = "SELECT COUNT(*) as total FROM seller s LEFT JOIN users u ON s.user_id = u.id $where_clause";
            if (!empty($params)) {
                $count_stmt = $conn->prepare($count_sql);
                $count_stmt->bind_param($param_types, ...$params);
                $count_stmt->execute();
                $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
                $count_stmt->close();
            } else {
                $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
            }

            // Lấy dữ liệu seller
            $sql = "SELECT s.*, u.name as user_name, u.email as user_email 
                    FROM seller s 
                    LEFT JOIN users u ON s.user_id = u.id 
                    $where_clause 
                    ORDER BY s.created_at DESC 
                    LIMIT ?, ?";
            $params[] = $start;
            $params[] = $length;
            $param_types .= 'ii';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $avatar = !empty($row['avatar']) ? $row['avatar'] : 'img/avatars/default-shop-avatar.png';
                $status_badge = '<span class="badge bg-'
                    . ($row['status'] === 'approved' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger'))
                    . '">' . ucfirst($row['status']) . '</span>';

                $actions = '<div class="btn-group btn-group-sm">';
                if ($row['status'] === 'pending') {
                    $actions .= '<button class="btn btn-success btn-sm" onclick="verifySeller(' . $row['seller_id'] . ')">
                                    <i class="bx bx-check"></i> Xác minh
                                </button>';
                } elseif ($row['status'] === 'approved') {
                    $actions .= '<button class="btn btn-warning btn-sm" onclick="unverifySeller(' . $row['seller_id'] . ')">
                                    <i class="bx bx-x"></i> Hủy xác minh
                                </button>';
                }
                $actions .= '<button class="btn btn-outline-info btn-sm" onclick="viewSeller(' . $row['seller_id'] . ')">
                                <i class="bx bx-show"></i> Chi tiết
                            </button>';
                $actions .= '</div>';

                $data[] = [
                    $row['seller_id'],
                    '<img src="' . $avatar . '" class="rounded-circle" width="40" height="40" alt="Avatar">',
                    htmlspecialchars($row['store_name']),
                    '<div><strong>' . htmlspecialchars($row['user_name']) . '</strong><br><small class="text-muted">' . htmlspecialchars($row['user_email']) . '</small></div>',
                    htmlspecialchars($row['business_address']),
                    htmlspecialchars($row['phone']),
                    $status_badge,
                    date('d/m/Y', strtotime($row['created_at'])),
                    $actions
                ];
            }
            $stmt->close();

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $total_records,
                'recordsFiltered' => $total_records,
                'data' => $data
            ]);
            exit;

        case 'verify_seller':
            $seller_id = intval($_POST['seller_id']);
            $success = false;

            // Bắt đầu transaction
            $conn->begin_transaction();
            try {
                // Lấy user_id từ seller
                $get_user_stmt = $conn->prepare("SELECT user_id FROM seller WHERE seller_id = ?");
                $get_user_stmt->bind_param("i", $seller_id);
                $get_user_stmt->execute();
                $res = $get_user_stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $user_id = intval($row['user_id']);
                    $get_user_stmt->close();

                    // Cập nhật trạng thái seller
                    $stmt1 = $conn->prepare("UPDATE seller SET status = 'approved' WHERE seller_id = ?");
                    $stmt1->bind_param("i", $seller_id);
                    $stmt1->execute();
                    $stmt1->close();

                    // Cập nhật role của user sang 'seller'
                    $stmt2 = $conn->prepare("UPDATE users SET role = 'seller' WHERE id = ?");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $stmt2->close();

                    $conn->commit();
                    $success = true;
                } else {
                    $get_user_stmt->close();
                    $conn->rollback();
                    $success = false;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $success = false;
            }

            echo json_encode(['success' => $success]);
            exit;

        case 'unverify_seller':
            $seller_id = intval($_POST['seller_id']);
            $success = false;

            // Bắt đầu transaction
            $conn->begin_transaction();
            try {
                // Lấy user_id từ seller
                $get_user_stmt = $conn->prepare("SELECT user_id FROM seller WHERE seller_id = ?");
                $get_user_stmt->bind_param("i", $seller_id);
                $get_user_stmt->execute();
                $res = $get_user_stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $user_id = intval($row['user_id']);
                    $get_user_stmt->close();

                    // Cập nhật trạng thái seller về 'pending'
                    $stmt1 = $conn->prepare("UPDATE seller SET status = 'pending' WHERE seller_id = ?");
                    $stmt1->bind_param("i", $seller_id);
                    $stmt1->execute();
                    $stmt1->close();

                    // Cập nhật role của user về 'user'
                    $stmt2 = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $stmt2->close();

                    $conn->commit();
                    $success = true;
                } else {
                    $get_user_stmt->close();
                    $conn->rollback();
                    $success = false;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $success = false;
            }

            echo json_encode(['success' => $success]);
            exit;
    }
}

$page_title = 'Quản lý Cửa hàng';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title><?= $page_title ?> | VibeMarket Admin</title>
    <link href="css/app.css?v=<?= time(); ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css" rel="stylesheet">
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main">
            <?php include 'includes/navbar.php'; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0"><strong><?= $page_title ?></strong></h1>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="card-title mb-0">Danh sách cửa hàng</h5>
                                        </div>
                                        <div class="col-auto">
                                            <select class="form-select form-select-sm" id="statusFilter">
                                                <option value="">Tất cả trạng thái</option>
                                                <option value="approved">Đã duyệt</option>
                                                <option value="pending">Chờ duyệt</option>
                                                <option value="rejected">Từ chối</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <table id="sellersTable" class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Ảnh đại diện</th>
                                                <th>Tên cửa hàng</th>
                                                <th>Người dùng</th>
                                                <th>Địa chỉ</th>
                                                <th>Điện thoại</th>
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
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="js/app.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function () {
            const table = $('#sellersTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function (d) {
                        d.action = 'get_sellers_data';
                        d.status_filter = $('#statusFilter').val();
                        return d;
                    }
                },
                columns: [
                    { data: 0, width: '5%' },
                    { data: 1, orderable: false, width: '8%' },
                    { data: 2, width: '15%' },
                    { data: 3, width: '15%' },
                    { data: 4, width: '15%' },
                    { data: 5, width: '10%' },
                    { data: 6, width: '10%' },
                    { data: 7, width: '10%' },
                    { data: 8, orderable: false, width: '12%' }
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/vi.json'
                }
            });

            $('#statusFilter').on('change', function () {
                table.ajax.reload();
            });
        });

        function viewSeller(sellerId) {
            Swal.fire({
                icon: 'info',
                title: 'Chi tiết cửa hàng',
                text: 'Xem chi tiết cửa hàng: ' + sellerId,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }

        function verifySeller(sellerId) {
            Swal.fire({
                icon: 'question',
                title: 'Xác minh cửa hàng?',
                showCancelButton: true,
                confirmButtonText: 'Xác minh',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '',
                        type: 'POST',
                        data: { action: 'verify_seller', seller_id: sellerId },
                        dataType: 'json',
                        success: function (response) {
                            $('#sellersTable').DataTable().ajax.reload(null, false);
                            Swal.fire({
                                icon: response.success ? 'success' : 'error',
                                title: response.success ? 'Đã xác minh!' : 'Lỗi',
                                text: response.success ? '' : (response.message || 'Có lỗi xảy ra'),
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    });
                }
            });
        }

        function unverifySeller(sellerId) {
            Swal.fire({
                icon: 'warning',
                title: 'Hủy xác minh cửa hàng?',
                showCancelButton: true,
                confirmButtonText: 'Hủy xác minh',
                cancelButtonText: 'Đóng'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '',
                        type: 'POST',
                        data: { action: 'unverify_seller', seller_id: sellerId },
                        dataType: 'json',
                        success: function (response) {
                            $('#sellersTable').DataTable().ajax.reload(null, false);
                            Swal.fire({
                                icon: response.success ? 'warning' : 'error',
                                title: response.success ? 'Đã hủy xác minh!' : 'Lỗi',
                                text: response.success ? '' : (response.message || 'Có lỗi xảy ra'),
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>

</html>