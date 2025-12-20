<?php
$statusMap = [
    'pending' => ['label' => 'Chờ xử lý', 'color' => 'warning'],
    'processing' => ['label' => 'Đang xử lý', 'color' => 'info'],
    'shipped' => ['label' => 'Đã gửi', 'color' => 'primary'],
    'shipping' => ['label' => 'Đang giao', 'color' => 'info'],
    'delivered' => ['label' => 'Đã giao', 'color' => 'success'],
    'cancelled' => ['label' => 'Đã hủy', 'color' => 'danger'],
    'returned' => ['label' => 'Đã trả', 'color' => 'secondary']
];

$paymentMap = [
    'cod' => 'Tiền mặt khi nhận hàng',
    'vnpay' => 'VNPay',
    'momo' => 'MoMo',
    'zalopay' => 'ZaloPay',
    'banking' => 'Chuyển khoản ngân hàng'
];

$status_info = $statusMap[$order['status']] ?? ['label' => $order['status'], 'color' => 'secondary'];
$payment_label = $paymentMap[$order['payment_method']] ?? $order['payment_method'];
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?php echo $this->baseUrl('orders'); ?>" class="btn btn-secondary btn-sm">
                <i class="bx bx-arrow-back"></i> Quay lại
            </a>
            <h1 class="h3 d-inline-block ms-3 mb-0"><strong>Chi tiết đơn hàng #<?php echo htmlspecialchars($order['code']); ?></strong></h1>
        </div>
        <div>
            <a href="<?php echo $this->baseUrl('orders/edit/' . $order['id']); ?>" class="btn btn-warning btn-sm">
                <i class="bx bx-edit"></i> Cập nhật trạng thái
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin đơn hàng</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Mã đơn:</strong> <?php echo htmlspecialchars($order['code']); ?></p>
                            <p class="mb-2"><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                            <p class="mb-2"><strong>Trạng thái:</strong> 
                                <span class="badge bg-<?php echo $status_info['color']; ?>"><?php echo $status_info['label']; ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Thanh toán:</strong> <?php echo htmlspecialchars($payment_label); ?></p>
                            <p class="mb-2"><strong>Phí ship:</strong> <?php echo number_format($order['shipping_fee'] ?? 0); ?> VNĐ</p>
                            <p class="mb-2"><strong>Tổng tiền:</strong> <span class="text-primary fw-bold"><?php echo number_format($order['total']); ?> VNĐ</span></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['note'])): ?>
                    <div class="mt-3">
                        <strong>Ghi chú:</strong>
                        <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($order['note'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sản phẩm</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hình ảnh</th>
                                <th>Tên sản phẩm</th>
                                <th>Giá</th>
                                <th>Số lượng</th>
                                <th>Tổng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($order['items'])): ?>
                                <?php 
                                // Detect base path for localhost vs production
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
                                    $base_path = '/VIBE_MARKET_BACKEND/VibeMarket-BE/';
                                } else {
                                    $base_path = '/';
                                }
                                
                                foreach ($order['items'] as $item): 
                                    $image = $item['image'] ?? '';
                                    
                                    // Parse image - could be JSON array or comma-separated string
                                    $firstImage = '';
                                    if (!empty($image)) {
                                        // Try to decode as JSON first
                                        $imageArray = json_decode($image, true);
                                        if (is_array($imageArray) && !empty($imageArray)) {
                                            $firstImage = $imageArray[0];
                                        } elseif (strpos($image, ',') !== false) {
                                            // Comma-separated string
                                            $imageArray = explode(',', $image);
                                            $firstImage = trim($imageArray[0]);
                                        } else {
                                            // Single image string
                                            $firstImage = $image;
                                        }
                                    }
                                    
                                    // Build image path
                                    if (empty($firstImage)) {
                                        $imagePath = $base_path . 'uploads/img/no-image.png';
                                    } elseif (filter_var($firstImage, FILTER_VALIDATE_URL)) {
                                        $imagePath = $firstImage;
                                    } else {
                                        // Check if path already contains uploads/products/
                                        if (strpos($firstImage, 'uploads/products/') !== false) {
                                            // Already has full path, just add base
                                            $imagePath = $base_path . ltrim($firstImage, '/');
                                        } else {
                                            // Add uploads/products/ directory
                                            $imagePath = $base_path . 'uploads/products/' . ltrim($firstImage, '/');
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <?php
                                        // Debug: Show actual file path
                                        $actualFilePath = __DIR__ . '/../../uploads/products/' . ltrim($firstImage, '/');
                                        $fileExists = file_exists($actualFilePath);
                                        ?>
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             width="50" 
                                             class="rounded"
                                             title="Path: <?php echo htmlspecialchars($imagePath); ?> | Exists: <?php echo $fileExists ? 'Yes' : 'No'; ?>"
                                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td><?php echo number_format($item['price']); ?> VNĐ</td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo number_format($item['price'] * $item['quantity']); ?> VNĐ</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Không có sản phẩm</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Tên:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="mb-2"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                    <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($order['email'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong>Địa chỉ:</strong></p>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                </div>
            </div>

            <?php if (!empty($order['shipping_tracking_code'])): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin vận chuyển</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><strong>Mã vận đơn:</strong> <?php echo htmlspecialchars($order['shipping_tracking_code']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
