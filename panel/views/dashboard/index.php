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
                            <i class="bx bx-user text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
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
                            <i class="bx bx-package text-success" style="font-size: 3rem; opacity: 0.3;"></i>
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
                            <i class="bx bx-cart text-warning" style="font-size: 3rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header">
                    <h5 class="card-title mb-0">Doanh thu tháng</h5>
                </div>
                <div class="card-body d-flex">
                    <div class="align-self-center w-100">
                        <div class="py-3">
                            <h2 class="text-primary fw-bold"><?= number_format($stats['monthly_revenue'] ?? 0); ?> VNĐ</h2>
                            <p class="text-muted mb-0"><small>Tổng doanh thu tháng này</small></p>
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
                            <h2 class="text-success fw-bold"><?= $stats['today_orders'] ?? 0; ?> đơn</h2>
                            <p class="text-muted mb-0"><small>Đơn hàng trong ngày hôm nay</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                                <td><?= isset($order['created_at']) ? date('d/m/Y', strtotime($order['created_at'])) : ''; ?></td>
                                <td><?= number_format($order['total']); ?>đ</td>
                                <td>
                                    <?php
                                    $statusMap = [
                                        'pending' => ['label' => 'Chờ xử lý', 'color' => 'warning'],
                                        'processing' => ['label' => 'Đang xử lý', 'color' => 'info'],
                                        'shipping' => ['label' => 'Đang giao', 'color' => 'primary'],
                                        'delivered' => ['label' => 'Đã giao', 'color' => 'success'],
                                        'cancelled' => ['label' => 'Đã hủy', 'color' => 'danger'],
                                        'returned' => ['label' => 'Đã trả', 'color' => 'secondary']
                                    ];
                                    $status = $statusMap[$order['status']] ?? ['label' => 'Không xác định', 'color' => 'secondary'];
                                    ?>
                                    <span class="badge bg-<?= $status['color']; ?>">
                                        <?= $status['label']; ?>
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
                            <?php 
                            $avatar = $user['avatar'] ?? null;
                            
                            if (empty($avatar)) {
                                $avatarPath = 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=random&size=36';
                            } elseif (filter_var($avatar, FILTER_VALIDATE_URL)) {
                                $avatarPath = $avatar;
                            } else {
                                if (strpos($avatar, 'uploads/') !== false) {
                                    $avatarPath = $uploads_base . ltrim($avatar, '/');
                                } else {
                                    $avatarPath = $uploads_base . 'uploads/avatars/' . ltrim($avatar, '/');
                                }
                            }
                            ?>
                            <img src="<?= htmlspecialchars($avatarPath); ?>"
                                width="36" height="36" class="rounded-circle me-2" alt="<?= htmlspecialchars($user['name']); ?>"
                                onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']); ?>&background=random&size=36'">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
                    callback: function(value) {
                        return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
                    }
                }
            }
        }
    }
});
</script>
