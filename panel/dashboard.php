<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php');
    exit;
}

// Thống kê tổng quan
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result ? $result->fetch_assoc()['total'] : 0;
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $result ? $result->fetch_assoc()['total'] : 0;
$result = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $result ? $result->fetch_assoc()['total'] : 0;

// Doanh thu tháng này
$result = $conn->query("SELECT SUM(total) as revenue FROM orders WHERE status = 'delivered' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stats['monthly_revenue'] = $result ? ($result->fetch_assoc()['revenue'] ?? 0) : 0;

// Đơn hàng hôm nay
$result = $conn->query("SELECT COUNT(*) as today_orders FROM orders WHERE DATE(created_at) = CURDATE()");
$stats['today_orders'] = $result ? $result->fetch_assoc()['today_orders'] : 0;

// Biểu đồ doanh thu 12 tháng
$revenue_chart = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $result = $conn->query("SELECT SUM(total) as revenue FROM orders WHERE status = 'delivered' AND DATE_FORMAT(created_at, '%Y-%m') = '$month'");
    $revenue = $result ? ($result->fetch_assoc()['revenue'] ?? 0) : 0;
    $revenue_chart[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'revenue' => floatval($revenue)
    ];
}

// Đơn hàng gần đây
$recent_orders = [];
$result = $conn->query(
    "SELECT code, customer_name, created_at, total, status 
     FROM orders 
     ORDER BY created_at DESC 
     LIMIT 5"
);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Người dùng hoạt động gần đây (nếu chưa có bảng user_status thì chỉ lấy user mới nhất)
$active_users = [];
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $active_users[] = $row;
    }
}

$conn->close();
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
                        <h1 class="h3 mb-0"><strong>Dashboard</strong></h1>
                    </div>
                    <div class="row">
                        <div class="col-xl-4 col-md-4 d-flex">
                            <div class="card illustration flex-fill">
                                <div class="card-body p-0 d-flex flex-fill">
                                    <div class="row g-0 w-100">
                                        <div class="col-6">
                                            <div class="p-3 m-1">
                                                <h4 class="fw-bold"><?= number_format($stats['total_users']); ?></h4>
                                                <p class="mb-0 text-muted">Người dùng</p>
                                            </div>
                                        </div>
                                        <div class="col-6 align-self-end text-end">
                                            <i class="bx bx-user text-primary"
                                                style="font-size: 3rem; opacity: 0.3;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-4 d-flex">
                            <div class="card illustration flex-fill">
                                <div class="card-body p-0 d-flex flex-fill">
                                    <div class="row g-0 w-100">
                                        <div class="col-6">
                                            <div class="p-3 m-1">
                                                <h4 class="fw-bold"><?= number_format($stats['total_products']); ?></h4>
                                                <p class="mb-0 text-muted">Sản phẩm</p>
                                            </div>
                                        </div>
                                        <div class="col-6 align-self-end text-end">
                                            <i class="bx bx-package text-success"
                                                style="font-size: 3rem; opacity: 0.3;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-4 d-flex">
                            <div class="card illustration flex-fill">
                                <div class="card-body p-0 d-flex flex-fill">
                                    <div class="row g-0 w-100">
                                        <div class="col-6">
                                            <div class="p-3 m-1">
                                                <h4 class="fw-bold"><?= number_format($stats['total_orders']); ?></h4>
                                                <p class="mb-0 text-muted">Đơn hàng</p>
                                            </div>
                                        </div>
                                        <div class="col-6 align-self-end text-end">
                                            <i class="bx bx-cart text-warning"
                                                style="font-size: 3rem; opacity: 0.3;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Thống kê chi tiết -->
                    <div class="row mt-4">
                        <div class="col-md-6 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Doanh thu tháng</h5>
                                </div>
                                <div class="card-body d-flex">
                                    <div class="align-self-center w-100">
                                        <div class="py-3">
                                            <h2 class="text-primary fw-bold">
                                                <?= number_format($stats['monthly_revenue']); ?>đ
                                            </h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Đơn hàng hôm nay</h5>
                                </div>
                                <div class="card-body d-flex">
                                    <div class="align-self-center w-100">
                                        <div class="py-3">
                                            <h2 class="text-success fw-bold"><?= $stats['today_orders']; ?></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Biểu đồ doanh thu -->
                    <div class="row mt-4">
                        <div class="col-12 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Doanh thu theo tháng</h5>
                                </div>
                                <div class="card-body py-3">
                                    <div class="chart chart-sm">
                                        <canvas id="revenueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Bảng dữ liệu -->
                    <div class="row mt-4">
                        <div class="col-lg-8 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Đơn hàng gần đây</h5>
                                </div>
                                <table class="table table-hover my-0">
                                    <thead>
                                        <tr>
                                            <th>Mã đơn</th>
                                            <th>Khách hàng</th>
                                            <th>Ngày</th>
                                            <th>Tổng tiền</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($order['code']); ?></td>
                                                <td><?= htmlspecialchars($order['customer_name']); ?></td>
                                                <td><?= isset($order['created_at']) ? date('d/m/Y', strtotime($order['created_at'])) : ''; ?>
                                                </td>
                                                <td><?= number_format($order['total']); ?>đ</td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                    switch ($order['status']) {
                                                        case 'delivered':
                                                            echo 'success';
                                                            break;
                                                        case 'pending':
                                                            echo 'warning';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'danger';
                                                            break;
                                                        default:
                                                            echo 'secondary';
                                                    }
                                                    ?>">
                                                        <?= ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-4 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Người dùng mới</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($active_users as $user): ?>
                                        <div class="d-flex align-items-start mb-2">
                                            <img src="<?= !empty($user['avatar']) ? $user['avatar'] : 'img/avatars/default.jpg'; ?>"
                                                width="36" height="36" class="rounded-circle me-2" alt="Avatar">
                                            <div class="flex-grow-1">
                                                <small class="fw-bold"><?= htmlspecialchars($user['name']); ?></small>
                                                <small class="d-block text-muted">
                                                    <?= isset($user['created_at']) ? date('H:i d/m', strtotime($user['created_at'])) : 'Chưa xác định'; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Biểu đồ doanh thu
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenue_chart, 'month')); ?>,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: <?php echo json_encode(array_column($revenue_chart, 'revenue')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>