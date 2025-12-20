<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Chi tiết cửa hàng</strong></h1>
        <div>
            <a href="<?php echo $this->baseUrl('sellers/edit/' . $seller['seller_id']); ?>" class="btn btn-warning me-2">
                <i class="bx bx-edit me-1"></i> Chỉnh sửa
            </a>
            <a href="<?php echo $this->baseUrl('sellers'); ?>" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Seller Info Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <?php 
                            $avatar = $seller['avatar'] ?? null;
                            $uploads_base = $this->getUploadsBaseUrl();
                            
                            if (empty($avatar)) {
                                $avatar_url = $this->baseUrl('img/avatars/default.jpg');
                            } elseif (filter_var($avatar, FILTER_VALIDATE_URL)) {
                                $avatar_url = $avatar;
                            } else {
                                if (strpos($avatar, 'uploads/') !== false) {
                                    $avatar_url = $uploads_base . ltrim($avatar, '/');
                                } else {
                                    $avatar_url = $uploads_base . 'uploads/vendor/avatars/' . ltrim($avatar, '/');
                                }
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                                 class="rounded-circle mb-2" 
                                 width="120" 
                                 height="120" 
                                 alt="Avatar"
                                 style="object-fit: cover;"
                                 onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                            <div>
                                <?php
                                $status_colors = [
                                    'approved' => 'success',
                                    'pending' => 'warning',
                                    'rejected' => 'danger',
                                    'blocked' => 'dark'
                                ];
                                $status_labels = [
                                    'approved' => 'Đã duyệt',
                                    'pending' => 'Chờ duyệt',
                                    'rejected' => 'Từ chối',
                                    'blocked' => 'Bị khóa'
                                ];
                                $status_color = $status_colors[$seller['status']] ?? 'secondary';
                                $status_label = $status_labels[$seller['status']] ?? $seller['status'];
                                ?>
                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_label; ?></span>
                            </div>
                        </div>
                        <div class="col-md-10">
                            <h3 class="mb-3"><?php echo htmlspecialchars($seller['store_name']); ?></h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><i class="bx bx-id-card me-2 text-muted"></i><strong>ID:</strong> <?php echo $seller['seller_id']; ?></p>
                                    <p class="mb-2"><i class="bx bx-envelope me-2 text-muted"></i><strong>Email:</strong> <?php echo htmlspecialchars($seller['email'] ?? 'N/A'); ?></p>
                                    <p class="mb-2"><i class="bx bx-phone me-2 text-muted"></i><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($seller['phone'] ?? 'N/A'); ?></p>
                                    <p class="mb-2"><i class="bx bx-map me-2 text-muted"></i><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($seller['business_address'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><i class="bx bx-briefcase me-2 text-muted"></i><strong>Loại hình:</strong> <?php echo htmlspecialchars($seller['business_type'] ?? 'N/A'); ?></p>
                                    <p class="mb-2"><i class="bx bx-receipt me-2 text-muted"></i><strong>Mã số thuế:</strong> <?php echo htmlspecialchars($seller['tax_id'] ?? 'N/A'); ?></p>
                                    <p class="mb-2"><i class="bx bx-calendar me-2 text-muted"></i><strong>Năm thành lập:</strong> <?php echo htmlspecialchars($seller['establish_year'] ?? 'N/A'); ?></p>
                                    <p class="mb-2"><i class="bx bx-time me-2 text-muted"></i><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($seller['created_at'])); ?></p>
                                </div>
                            </div>
                            <?php if (!empty($seller['description'])): ?>
                            <div class="mt-3">
                                <strong><i class="bx bx-info-circle me-2 text-muted"></i>Mô tả:</strong>
                                <p class="text-muted mb-0 mt-1"><?php echo nl2br(htmlspecialchars($seller['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-package fs-1 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Sản phẩm</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total_products']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-shopping-bag fs-1 text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Đơn hàng</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total_orders']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-dollar-circle fs-1 text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Doanh thu</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total_revenue']); ?>đ</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Revenue Chart -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Doanh thu 6 tháng gần nhất</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Orders Status Chart -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Đơn hàng theo trạng thái</h5>
                </div>
                <div class="card-body">
                    <canvas id="ordersStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top 5 sản phẩm bán chạy</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Hình ảnh</th>
                                    <th>Tên sản phẩm</th>
                                    <th>Giá</th>
                                    <th>Đã bán</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Chưa có sản phẩm nào</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $product_image = $product['image'] ?? null;
                                        
                                        // Parse JSON nếu là JSON array
                                        if (!empty($product_image)) {
                                            $images_array = json_decode($product_image, true);
                                            if (is_array($images_array) && count($images_array) > 0) {
                                                $product_image = $images_array[0];
                                            }
                                        }
                                        
                                        if (empty($product_image)) {
                                            $product_image_url = 'https://via.placeholder.com/50x50?text=No+Image';
                                        } elseif (filter_var($product_image, FILTER_VALIDATE_URL)) {
                                            $product_image_url = $product_image;
                                        } else {
                                            // Nếu đã có /uploads/products/ thì concat trực tiếp
                                            if (strpos($product_image, '/uploads/products/') === 0) {
                                                $product_image_url = $uploads_base . $product_image;
                                            } else {
                                                $product_image_url = $uploads_base . '/uploads/products/' . ltrim($product_image, '/');
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($product_image_url); ?>" 
                                             width="50" 
                                             height="50" 
                                             alt="Product"
                                             style="object-fit: cover;"
                                             onerror="if(this.src!='https://via.placeholder.com/50x50?text=No+Image')this.src='https://via.placeholder.com/50x50?text=No+Image'">
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo number_format($product['price']); ?>đ</td>
                                    <td><span class="badge bg-primary"><?php echo number_format($product['total_sold']); ?></span></td>
                                    <td class="text-success fw-bold"><?php echo number_format($product['revenue']); ?>đ</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Wait for Chart.js to load
(function checkChartJS() {
    if (typeof Chart === 'undefined') {
        setTimeout(checkChartJS, 50);
        return;
    }

    // Revenue Chart Data
    const revenueData = <?php echo json_encode($revenue_by_month); ?>;
    const revenueLabels = revenueData.map(item => {
        const [year, month] = item.month.split('-');
        return `Tháng ${month}/${year}`;
    });
    const revenueValues = revenueData.map(item => parseFloat(item.revenue));
    const ordersCount = revenueData.map(item => parseInt(item.orders_count));

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Doanh thu (đ)',
                data: revenueValues,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Số đơn hàng',
                data: ordersCount,
                borderColor: 'rgb(255, 159, 64)',
                backgroundColor: 'rgba(255, 159, 64, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.datasetIndex === 0) {
                                label += new Intl.NumberFormat('vi-VN').format(context.parsed.y) + 'đ';
                            } else {
                                label += new Intl.NumberFormat('vi-VN').format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('vi-VN', {
                                notation: 'compact',
                                compactDisplay: 'short'
                            }).format(value) + 'đ';
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return value;
                        }
                    }
                }
            }
        }
    });

    // Orders Status Chart Data
    const ordersStatusData = <?php echo json_encode($stats['orders_by_status']); ?>;
    const statusLabels = {
        'pending': 'Chờ xử lý',
        'processing': 'Đang xử lý',
        'shipped': 'Đang giao',
        'delivered': 'Đã giao',
        'cancelled': 'Đã hủy'
    };
    const statusColors = {
        'pending': '#ffc107',
        'processing': '#17a2b8',
        'shipped': '#007bff',
        'delivered': '#28a745',
        'cancelled': '#dc3545'
    };

    const statusChartLabels = ordersStatusData.map(item => statusLabels[item.status] || item.status);
    const statusChartValues = ordersStatusData.map(item => parseInt(item.count));
    const statusChartColors = ordersStatusData.map(item => statusColors[item.status] || '#6c757d');

    // Orders Status Chart
    const statusCtx = document.getElementById('ordersStatusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusChartLabels,
            datasets: [{
                data: statusChartValues,
                backgroundColor: statusChartColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
})();
</script>
