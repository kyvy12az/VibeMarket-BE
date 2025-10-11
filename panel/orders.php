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
        case 'update_status':
            $order_id = intval($_POST['order_id']);
            $new_status = $_POST['order_status'];

            if ($new_status === 'delivered') {
                // Khi giao hàng thành công, cập nhật luôn trạng thái thanh toán
                $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = 'paid' WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'new_status' => $new_status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
            }
            exit;
    }
}

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_status_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(o.code LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($payment_status_filter) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_status_filter;
    $param_types .= 's';
}

if ($date_from) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$count_sql = "SELECT COUNT(*) as total FROM orders o $where_clause";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$sql = "SELECT o.* FROM orders o $where_clause ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Thống kê
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['processing'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'processing'")->fetch_assoc()['count'];
$stats['delivered'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'delivered'")->fetch_assoc()['count'];
$stats['cancelled'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'cancelled'")->fetch_assoc()['count'];
$today_revenue = $conn->query("SELECT SUM(total) as revenue FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'delivered'")->fetch_assoc()['revenue'] ?? 0;
?>
<?php include "includes/header.php"; ?>

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main">
            <?php include 'includes/navbar.php'; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0"><strong>Quản lý đơn hàng</strong></h1>
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="exportOrders()">
                                <i class="bx bx-download me-1"></i> Xuất Excel
                            </button>
                            <span class="badge bg-success fs-6">Doanh thu hôm nay:
                                <?php echo number_format($today_revenue); ?>đ</span>
                        </div>
                    </div>
                    <!-- Thống kê nhanh -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0 text-truncate">Tổng số</h6>
                                        <div class="stat text-primary"><i class="bx bx-shopping-bag"></i></div>
                                    </div>
                                    <h2 class="mt-3 mb-0"><?php echo number_format($stats['total']); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0 text-truncate">Chờ xử lý</h6>
                                        <div class="stat text-warning"><i class="bx bx-time"></i></div>
                                    </div>
                                    <h2 class="mt-3 mb-0"><?php echo number_format($stats['pending']); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0 text-truncate">Đang xử lý</h6>
                                        <div class="stat text-info"><i class="bx bx-loader"></i></div>
                                    </div>
                                    <h2 class="mt-3 mb-0"><?php echo number_format($stats['processing']); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0 text-truncate">Đã giao</h6>
                                        <div class="stat text-success"><i class="bx bx-check-circle"></i></div>
                                    </div>
                                    <h2 class="mt-3 mb-0"><?php echo number_format($stats['delivered']); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0 text-truncate">Đã hủy</h6>
                                        <div class="stat text-danger"><i class="bx bx-x-circle"></i></div>
                                    </div>
                                    <h2 class="mt-3 mb-0"><?php echo number_format($stats['cancelled']); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Tìm mã đơn, khách hàng..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <select name="status" class="form-select">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="payment_status" class="form-select">
                                        <option value="">Thanh toán</option>
                                        <option value="pending" <?php echo $payment_status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo $payment_status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="failed" <?php echo $payment_status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="date" name="date_from" class="form-control"
                                        value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="date" name="date_to" class="form-control"
                                        value="<?php echo $date_to; ?>">
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bx bx-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Bảng dữ liệu -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Danh sách đơn hàng</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Mã đơn</th>
                                            <th>Khách hàng</th>
                                            <th>Điện thoại</th>
                                            <th>Địa chỉ</th>
                                            <th>Tổng tiền</th>
                                            <th>Trạng thái</th>
                                            <th>Thanh toán</th>
                                            <th>Ngày đặt</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr data-order-id="<?php echo $order['id']; ?>">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['code']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-bold">
                                                            <?php echo htmlspecialchars($order['customer_name']); ?></div>
                                                        <small
                                                            class="text-muted"><?php echo htmlspecialchars($order['email'] ?? ''); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['phone']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['address']); ?>
                                                </td>
                                                <td>
                                                    <strong style="color: red;"><?php echo number_format($order['total']); ?>đ</strong>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm order_status-select"
                                                        data-order-id="<?php echo $order['id']; ?>"
                                                        style="min-width: 120px;">
                                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing
                                                        </option>
                                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                    switch ($order['payment_status']) {
                                                        case 'paid':
                                                            echo 'success';
                                                            break;
                                                        case 'failed':
                                                            echo 'danger';
                                                            break;
                                                        default:
                                                            echo 'warning';
                                                    }
                                                    ?>">
                                                        <?php
                                                        if ($order['payment_status'] == 'paid')
                                                            echo 'Đã thanh toán';
                                                        elseif ($order['payment_status'] == 'failed')
                                                            echo 'Thất bại';
                                                        else
                                                            echo 'Chờ thanh toán';
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
                                                        <br><small
                                                            class="text-muted"><?php echo date('H:i', strtotime($order['created_at'])); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary btn-sm"
                                                            onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                            <i class="bx bx-show"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Phân trang -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link"
                                                    href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&payment_status=<?php echo urlencode($payment_status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('.order_status-select').forEach(select => {
            select.addEventListener('change', function () {
                const orderId = this.dataset.orderId;
                const newStatus = this.value;
                const originalStatus = this.getAttribute('data-original-order_status') || this.querySelector('option[selected]')?.value;
                Swal.fire({
                    title: 'Xác nhận thay đổi',
                    text: `Bạn có chắc muốn đổi trạng thái đơn hàng thành "${newStatus}"?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Có, cập nhật!',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/orders.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=update_status&order_id=${orderId}&order_status=${newStatus}`
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Thành công!',
                                        text: 'Đã cập nhật trạng thái đơn hàng',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                    this.setAttribute('data-original-order_status', newStatus);
                                } else {
                                    this.value = originalStatus;
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Lỗi!',
                                        text: data.message || 'Có lỗi xảy ra'
                                    });
                                }
                            });
                    } else {
                        this.value = originalStatus;
                    }
                });
            });
        });
        function viewOrder(orderId) {
            window.open(`/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/order-detail.php?id=${orderId}`, '_blank');
        }
        function exportOrders() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open(`/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/orders-export.php?${params.toString()}`, '_blank');
        }
    </script>
</body>

</html>