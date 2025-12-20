<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Chỉnh sửa người dùng</strong></h1>
        <a href="<?php echo $this->baseUrl('users'); ?>" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Quay lại
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin người dùng</h5>
                </div>
                <div class="card-body">
                    <form id="edit-user-form">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" autocomplete="tel">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vai trò <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" required>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="seller" <?php echo $user['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Địa chỉ</label>
                            <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-3">Đổi mật khẩu (để trống nếu không đổi)</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Mật khẩu mới</label>
                                <input type="password" class="form-control" name="password" id="password" minlength="6" autocomplete="new-password">
                                <small class="text-muted">Tối thiểu 6 ký tự</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Xác nhận mật khẩu</label>
                                <input type="password" class="form-control" name="password_confirm" id="password_confirm" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?php echo $this->baseUrl('users'); ?>" class="btn btn-secondary">
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
                    <h5 class="card-title mb-0">Avatar</h5>
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
                         class="rounded-circle mb-3" 
                         width="150" 
                         height="150" 
                         alt="Avatar"
                         onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                    
                    <div class="mb-2">
                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                    </div>
                    <div class="text-muted mb-3">
                        <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    
                    <?php
                    $role_colors = [
                        'admin' => 'danger',
                        'seller' => 'info',
                        'user' => 'secondary'
                    ];
                    $role_color = $role_colors[$user['role']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $role_color; ?> mb-2"><?php echo ucfirst($user['role']); ?></span>
                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo $user['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin khác</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">ID</small>
                        <div><?php echo $user['id']; ?></div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Ngày tạo</small>
                        <div><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></div>
                    </div>
                    <?php if (isset($user['updated_at']) && $user['updated_at']): ?>
                    <div class="mb-2">
                        <small class="text-muted">Cập nhật lần cuối</small>
                        <div><?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?></div>
                    </div>
                    <?php endif; ?>
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
        $('#edit-user-form').on('submit', function(e) {
        e.preventDefault();
        
        const password = $('#password').val();
        const password_confirm = $('#password_confirm').val();
        
        // Validate password confirmation if password is provided
        if (password && password !== password_confirm) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: 'Mật khẩu xác nhận không khớp!',
                confirmButtonText: 'Đóng'
            });
            return;
        }
        
        // Validate password length if provided
        if (password && password.length < 6) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: 'Mật khẩu phải có ít nhất 6 ký tự!',
                confirmButtonText: 'Đóng'
            });
            return;
        }
        
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
            url: '<?php echo $this->baseUrl('users/update'); ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công!',
                        text: response.message || 'Cập nhật người dùng thành công',
                        timer: 2000,
                        showConfirmButton: false,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = '<?php echo $this->baseUrl('users'); ?>';
                    });
                } else {
                    submitBtn.prop('disabled', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Cập nhật thất bại!',
                        text: response.message || 'Có lỗi xảy ra khi cập nhật người dùng',
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
    }); // Close $(document).ready
})(); // Close checkJQuery
</script>
