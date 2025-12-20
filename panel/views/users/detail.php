<link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.css" rel="stylesheet">

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0"><strong>Chi tiết người dùng</strong></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo $this->baseUrl('dashboard'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo $this->baseUrl('users'); ?>">Người dùng</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($user['name']); ?></li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="<?php echo $this->baseUrl('users/edit/' . $user['id']); ?>" class="btn btn-warning me-2">
                <i class="bx bx-edit me-1"></i> Chỉnh sửa
            </a>
            <a href="<?php echo $this->baseUrl('users'); ?>" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i> Quay lại
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar - User Info -->
        <div class="col-md-4 col-xl-3">
            <!-- Personal Info Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin cá nhân</h5>
                </div>
                <div class="card-body text-center">
                    <?php 
                    $avatar = $user['avatar'] ?? null;
                    $uploads_base = $this->getUploadsBaseUrl();
                    
                    if (empty($avatar)) {
                        $avatar = $this->baseUrl('img/avatars/default.jpg');
                    } elseif (filter_var($avatar, FILTER_VALIDATE_URL)) {
                        $avatar = $avatar;
                    } else {
                        if (strpos($avatar, 'uploads/') !== false) {
                            $avatar = $uploads_base . ltrim($avatar, '/');
                        } else {
                            $avatar = $uploads_base . 'uploads/avatars/' . ltrim($avatar, '/');
                        }
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($avatar); ?>" 
                         alt="Avatar" 
                         class="rounded-circle mb-2" 
                         style="width: 128px; height: 128px; object-fit: cover;"
                         onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                    
                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <div class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></div>

                    <div class="d-flex justify-content-center mb-3">
                        <?php
                        $role_colors = [
                            'admin' => 'danger',
                            'seller' => 'info',
                            'user' => 'secondary'
                        ];
                        $role_color = $role_colors[$user['role']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $role_color; ?> me-2"><?php echo ucfirst($user['role']); ?></span>
                        <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo $user['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>

                    <div class="row">
                        <div class="col">
                            <small class="text-muted">Số điện thoại</small>
                            <div><?php echo htmlspecialchars($user['phone'] ?? 'Chưa có'); ?></div>
                        </div>
                    </div>
                </div>
                <hr class="my-0">
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">ID</small>
                        <div><?php echo $user['id']; ?></div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Ngày đăng ký</small>
                        <div><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></div>
                    </div>
                    <?php if (isset($user['updated_at']) && $user['updated_at']): ?>
                    <div class="mb-2">
                        <small class="text-muted">Cập nhật lần cuối</small>
                        <div><?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['address'])): ?>
                    <div class="mb-2">
                        <small class="text-muted">Địa chỉ</small>
                        <div><?php echo htmlspecialchars($user['address']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Login Method Card -->
            <?php 
            $provider = $user['provider'] ?? 'local';
            $provider_icons = [
                'google' => ['icon' => 'bxl-google', 'color' => 'danger', 'name' => 'Google'],
                'zalo' => ['icon' => 'bx-message-square-dots', 'color' => 'primary', 'name' => 'Zalo'],
                'facebook' => ['icon' => 'bxl-facebook-circle', 'color' => 'primary', 'name' => 'Facebook'],
                'local' => ['icon' => 'bx-user-circle', 'color' => 'secondary', 'name' => 'Tài khoản mật khẩu']
            ];
            $provider_info = $provider_icons[$provider] ?? $provider_icons['local'];
            ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Phương thức đăng nhập</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class='bx <?php echo $provider_info['icon']; ?> fs-3 text-<?php echo $provider_info['color']; ?> me-3'></i>
                        <div class="flex-grow-1">
                            <div><strong><?php echo $provider_info['name']; ?></strong></div>
                            <small class="text-muted">
                                <?php 
                                if ($provider === 'local') {
                                    echo 'Đăng nhập bằng email và mật khẩu';
                                } else {
                                    echo 'Đăng nhập bằng ' . $provider_info['name'];
                                }
                                ?>
                            </small>
                            
                            <?php if ($provider === 'zalo' && !empty($user['zalo_id'])): ?>
                            <div class="mt-1">
                                <small class="text-muted">Zalo ID: </small>
                                <small class="text-break"><?php echo htmlspecialchars($user['zalo_id']); ?></small>
                            </div>
                            <?php elseif ($provider === 'facebook' && !empty($user['facebook_id'])): ?>
                            <div class="mt-1">
                                <small class="text-muted">Facebook ID: </small>
                                <small class="text-break"><?php echo htmlspecialchars($user['facebook_id']); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thống kê nhanh</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="bx bx-shopping-bag text-primary fs-3"></i>
                            <span class="ms-2">Tổng đơn hàng</span>
                        </div>
                        <h4 class="mb-0"><?php echo number_format($stats['total_orders']); ?></h4>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="bx bx-check-circle text-success fs-3"></i>
                            <span class="ms-2">Hoàn thành</span>
                        </div>
                        <h4 class="mb-0"><?php echo number_format($stats['completed_orders']); ?></h4>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="bx bx-time text-warning fs-3"></i>
                            <span class="ms-2">Đang xử lý</span>
                        </div>
                        <h4 class="mb-0"><?php echo number_format($stats['pending_orders']); ?></h4>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="bx bx-x-circle text-danger fs-3"></i>
                            <span class="ms-2">Đã hủy</span>
                        </div>
                        <h4 class="mb-0"><?php echo number_format($stats['cancelled_orders']); ?></h4>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bx bx-dollar text-info fs-3"></i>
                            <span class="ms-2">Tổng chi tiêu</span>
                        </div>
                        <h4 class="mb-0 text-info"><?php echo number_format($stats['total_spent']); ?>đ</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-8 col-xl-9">
            <!-- Activity Chart -->
            <?php if (!empty($monthly_activity)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Hoạt động 12 tháng gần đây</h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="userDetailTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                                <i class="bx bx-package me-1"></i> Đơn hàng (<?php echo count($recent_orders); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts" type="button" role="tab">
                                <i class="bx bx-message-square-detail me-1"></i> Bài viết (<?php echo count($posts); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="network-tab" data-bs-toggle="tab" data-bs-target="#network" type="button" role="tab">
                                <i class="bx bx-group me-1"></i> Mạng lưới (<?php echo $network_stats['followers'] + $network_stats['following']; ?>)
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="userDetailTabsContent">
                        <!-- Orders Tab -->
                        <div class="tab-pane fade show active" id="orders" role="tabpanel">
                            <?php if (empty($recent_orders)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bx bx-shopping-bag fs-1"></i>
                                    <p class="mb-0">Chưa có đơn hàng nào</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Mã đơn</th>
                                                <th>Ngày đặt</th>
                                                <th>Tổng tiền</th>
                                                <th>Trạng thái</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><strong>#<?php echo $order['id']; ?></strong></td>
                                                <td><?php echo $order['formatted_date']; ?></td>
                                                <td><?php echo number_format($order['total']); ?>đ</td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'processing' => 'info',
                                                        'shipped' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $status_labels = [
                                                        'pending' => 'Chờ xử lý',
                                                        'processing' => 'Đang xử lý',
                                                        'shipped' => 'Đã gửi',
                                                        'delivered' => 'Đã giao',
                                                        'cancelled' => 'Đã hủy'
                                                    ];
                                                    $status_color = $status_colors[$order['status']] ?? 'secondary';
                                                    $status_label = $status_labels[$order['status']] ?? $order['status'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_label; ?></span>
                                                </td>
                                                <td>
                                                    <a href="<?php echo $this->baseUrl('orders/view/' . $order['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bx bx-show"></i> Chi tiết
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Posts Tab -->
                        <div class="tab-pane fade" id="posts" role="tabpanel">
                            <?php if (empty($posts)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bx bx-message-square-detail fs-1 mb-3 d-block"></i>
                                    <p>Chưa có bài viết nào</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($posts as $post): ?>
                                        <div class="col-12 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <span class="badge bg-<?php 
                                                                echo $post['status'] === 'public' ? 'success' : 
                                                                    ($post['status'] === 'pending' ? 'warning' : 
                                                                    ($post['status'] === 'hidden' ? 'secondary' : 'danger')); 
                                                            ?>">
                                                                <?php 
                                                                $post_status = [
                                                                    'public' => 'Công khai',
                                                                    'pending' => 'Chờ duyệt',
                                                                    'hidden' => 'Ẩn',
                                                                    'deleted' => 'Đã xóa'
                                                                ];
                                                                echo $post_status[$post['status']] ?? $post['status']; 
                                                                ?>
                                                            </span>
                                                            <small class="text-muted ms-2"><?php echo $post['formatted_date']; ?></small>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="bx bx-heart me-1"></i><?php echo $post['likes_count']; ?>
                                                                <i class="bx bx-comment ms-3 me-1"></i><?php echo $post['comments_count']; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Network Tab -->
                        <div class="tab-pane fade" id="network" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="bx bx-user-plus fs-1 text-primary mb-2"></i>
                                            <h3 class="mb-0"><?php echo number_format($network_stats['followers']); ?></h3>
                                            <small class="text-muted">Người theo dõi</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="bx bx-user-check fs-1 text-success mb-2"></i>
                                            <h3 class="mb-0"><?php echo number_format($network_stats['following']); ?></h3>
                                            <small class="text-muted">Đang theo dõi</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Followers List -->
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="bx bx-user-plus me-2"></i>Người theo dõi</h6>
                                    <?php if (empty($followers_list)): ?>
                                        <div class="text-center py-3 text-muted">
                                            <small>Chưa có người theo dõi</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($followers_list as $follower): 
                                                $follower_avatar = $follower['avatar'] ?? null;
                                                $uploads_base = $this->getUploadsBaseUrl();
                                                
                                                if (empty($follower_avatar)) {
                                                    $follower_avatar = $this->baseUrl('img/avatars/default.jpg');
                                                } elseif (filter_var($follower_avatar, FILTER_VALIDATE_URL)) {
                                                    $follower_avatar = $follower_avatar;
                                                } else {
                                                    if (strpos($follower_avatar, 'uploads/') !== false) {
                                                        $follower_avatar = $uploads_base . ltrim($follower_avatar, '/');
                                                    } else {
                                                        $follower_avatar = $uploads_base . 'uploads/avatars/' . ltrim($follower_avatar, '/');
                                                    }
                                                }
                                            ?>
                                                <a href="<?php echo $this->baseUrl('users/detail/' . $follower['id']); ?>" class="list-group-item list-group-item-action">
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo htmlspecialchars($follower_avatar); ?>" 
                                                             class="rounded-circle me-3" 
                                                             width="40" 
                                                             height="40" 
                                                             alt="Avatar"
                                                             onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold"><?php echo htmlspecialchars($follower['name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($follower['email']); ?></small>
                                                        </div>
                                                        <small class="text-muted"><?php echo $follower['formatted_date']; ?></small>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Following List -->
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="bx bx-user-check me-2"></i>Đang theo dõi</h6>
                                    <?php if (empty($following_list)): ?>
                                        <div class="text-center py-3 text-muted">
                                            <small>Chưa theo dõi ai</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($following_list as $following): 
                                                $following_avatar = $following['avatar'] ?? null;
                                                $uploads_base = $this->getUploadsBaseUrl();
                                                
                                                if (empty($following_avatar)) {
                                                    $following_avatar = $this->baseUrl('img/avatars/default.jpg');
                                                } elseif (filter_var($following_avatar, FILTER_VALIDATE_URL)) {
                                                    $following_avatar = $following_avatar;
                                                } else {
                                                    if (strpos($following_avatar, 'uploads/') !== false) {
                                                        $following_avatar = $uploads_base . ltrim($following_avatar, '/');
                                                    } else {
                                                        $following_avatar = $uploads_base . 'uploads/avatars/' . ltrim($following_avatar, '/');
                                                    }
                                                }
                                            ?>
                                                <a href="<?php echo $this->baseUrl('users/detail/' . $following['id']); ?>" class="list-group-item list-group-item-action">
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo htmlspecialchars($following_avatar); ?>" 
                                                             class="rounded-circle me-3" 
                                                             width="40" 
                                                             height="40" 
                                                             alt="Avatar"
                                                             onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold"><?php echo htmlspecialchars($following['name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($following['email']); ?></small>
                                                        </div>
                                                        <small class="text-muted"><?php echo $following['formatted_date']; ?></small>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($monthly_activity)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function checkLibraries() {
    if (typeof Chart === 'undefined') {
        setTimeout(checkLibraries, 50);
        return;
    }

    const activityData = <?php echo json_encode($monthly_activity); ?>;
    const activityLabels = activityData.map(item => item.month);
    const orderCountData = activityData.map(item => parseInt(item.order_count));
    const revenueData = activityData.map(item => parseFloat(item.revenue));

    const activityCtx = document.getElementById('activityChart').getContext('2d');
    new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: activityLabels.reverse(),
            datasets: [{
                label: 'Số đơn hàng',
                data: orderCountData.reverse(),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.1,
                yAxisID: 'y'
            }, {
                label: 'Doanh thu (đ)',
                data: revenueData.reverse(),
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.1,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Số đơn hàng'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    },
                    title: {
                        display: true,
                        text: 'Doanh thu (đ)'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.datasetIndex === 1) {
                                    label += new Intl.NumberFormat('vi-VN').format(context.parsed.y) + 'đ';
                                } else {
                                    label += context.parsed.y;
                                }
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>
