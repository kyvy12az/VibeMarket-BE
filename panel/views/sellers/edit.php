<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Chỉnh sửa cửa hàng</strong></h1>
        <a href="<?php echo $this->baseUrl('sellers'); ?>" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Quay lại
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin cửa hàng</h5>
                </div>
                <div class="card-body">
                    <form id="edit-seller-form">
                        <input type="hidden" name="seller_id" value="<?php echo $seller['seller_id']; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tên cửa hàng <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="store_name" value="<?php echo htmlspecialchars($seller['store_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($seller['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($seller['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Loại hình kinh doanh</label>
                                <input type="text" class="form-control" name="business_type" value="<?php echo htmlspecialchars($seller['business_type'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Mã số thuế</label>
                                <input type="text" class="form-control" name="tax_id" value="<?php echo htmlspecialchars($seller['tax_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Năm thành lập</label>
                                <input type="number" class="form-control" name="establish_year" value="<?php echo htmlspecialchars($seller['establish_year'] ?? ''); ?>" min="1900" max="<?php echo date('Y'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Địa chỉ kinh doanh</label>
                            <textarea class="form-control" name="business_address" rows="2"><?php echo htmlspecialchars($seller['business_address'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($seller['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?php echo $this->baseUrl('sellers'); ?>" class="btn btn-secondary">
                                <i class="bx bx-x me-1"></i> Hủy
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save me-1"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin khác</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
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
                             class="rounded-circle mb-3 d-block mx-auto" 
                             width="120" 
                             height="120" 
                             alt="Avatar"
                             style="object-fit: cover;"
                             onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                    </div>

                    <div class="mb-2">
                        <small class="text-muted">ID</small>
                        <div><?php echo $seller['seller_id']; ?></div>
                    </div>
                    
                    <div class="mb-2">
                        <small class="text-muted">Trạng thái</small>
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

                    <div class="mb-2">
                        <small class="text-muted">Ngày tạo</small>
                        <div><?php echo date('d/m/Y H:i', strtotime($seller['created_at'])); ?></div>
                    </div>

                    <div class="mb-2">
                        <small class="text-muted">Doanh thu</small>
                        <div><?php echo number_format($total_revenue ?? 0); ?>đ</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be available
(function checkJQuery() {
    if (typeof jQuery === 'undefined') {
        setTimeout(checkJQuery, 50);
        return;
    }
    
    $(document).ready(function() {
        $('#edit-seller-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            const submitBtn = $(this).find('button[type="submit"]');
            
            // Show loading state
            Swal.fire({
                title: 'Đang xử lý...',
                text: 'Vui lòng đợi',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Disable submit button
            submitBtn.prop('disabled', true);
            
            $.ajax({
                url: '<?php echo $this->baseUrl('sellers/update'); ?>',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: response.message || 'Cập nhật cửa hàng thành công',
                            timer: 2000,
                            showConfirmButton: false,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = '<?php echo $this->baseUrl('sellers'); ?>';
                        });
                    } else {
                        submitBtn.prop('disabled', false);
                        Swal.fire({
                            icon: 'error',
                            title: 'Cập nhật thất bại!',
                            text: response.message || 'Có lỗi xảy ra khi cập nhật cửa hàng',
                            confirmButtonText: 'Đóng',
                            confirmButtonColor: '#d33'
                        });
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false);
                    let errorMsg = 'Có lỗi xảy ra khi kết nối đến server';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMsg = 'Không tìm thấy endpoint';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Lỗi server, vui lòng thử lại sau';
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi!',
                        text: errorMsg,
                        confirmButtonText: 'Đóng',
                        confirmButtonColor: '#d33',
                        footer: xhr.status ? '<small>Mã lỗi: ' + xhr.status + '</small>' : ''
                    });
                }
            });
        });
    });
})();
</script>
