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

$status_info = $statusMap[$order['status']] ?? ['label' => $order['status'], 'color' => 'secondary'];
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?php echo $this->baseUrl('orders/detail/' . $order['id']); ?>" class="btn btn-secondary btn-sm">
                <i class="bx bx-arrow-back"></i> Quay lại
            </a>
            <h1 class="h3 d-inline-block ms-3 mb-0"><strong>Cập nhật đơn hàng #<?php echo htmlspecialchars($order['code']); ?></strong></h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin đơn hàng</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Mã đơn:</strong> <?php echo htmlspecialchars($order['code']); ?></p>
                    <p class="mb-2"><strong>Khách hàng:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="mb-2"><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                    <p class="mb-2"><strong>Trạng thái hiện tại:</strong> 
                        <span class="badge bg-<?php echo $status_info['color']; ?>"><?php echo $status_info['label']; ?></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cập nhật trạng thái</h5>
                </div>
                <div class="card-body">
                    <form id="updateStatusForm">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Trạng thái mới</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">-- Chọn trạng thái --</option>
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Đang xử lý</option>
                                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Đã gửi</option>
                                <option value="shipping" <?php echo $order['status'] === 'shipping' ? 'selected' : ''; ?>>Đang giao</option>
                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Đã giao</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                <option value="returned" <?php echo $order['status'] === 'returned' ? 'selected' : ''; ?>>Đã trả</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label">Ghi chú (tùy chọn)</label>
                            <textarea class="form-control" id="note" name="note" rows="3" placeholder="Ghi chú về việc cập nhật trạng thái..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Cập nhật
                            </button>
                            <a href="<?php echo $this->baseUrl('orders/detail/' . $order['id']); ?>" class="btn btn-secondary">
                                <i class="bx bx-x"></i> Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
