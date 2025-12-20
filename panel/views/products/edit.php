<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Chỉnh sửa sản phẩm</strong></h1>
        <a href="<?php echo $this->baseUrl('products'); ?>" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Quay lại
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin sản phẩm</h5>
                </div>
                <div class="card-body">
                    <form id="edit-product-form">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                        <div class="mb-3">
                            <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Giá bán <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" value="<?php echo $product['price']; ?>" required min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Giá gốc</label>
                                <input type="number" class="form-control" name="original_price" value="<?php echo $product['original_price'] ?? ''; ?>" min="0">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tồn kho</label>
                                <input type="number" class="form-control" name="quantity" value="<?php echo $product['quantity'] ?? 0; ?>" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Danh mục</label>
                                <select class="form-select" name="category">
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo ($product['category'] === $cat['category']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Thương hiệu</label>
                            <input type="text" class="form-control" name="brand" value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="5"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo ($product['status'] === 'active') ? 'selected' : ''; ?>>Đang bán</option>
                                <option value="inactive" <?php echo ($product['status'] === 'inactive') ? 'selected' : ''; ?>>Ngừng bán</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?php echo $this->baseUrl('products'); ?>" class="btn btn-secondary">
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
            <!-- Product Preview -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Xem trước</h5>
                </div>
                <div class="card-body">
                    <?php
                    $uploads_base = $this->getUploadsBaseUrl();
                    $images = json_decode($product['image'] ?? '[]', true);
                    if (!empty($images) && is_array($images)) {
                        $firstImage = $images[0];
                        if (strpos($firstImage, '/uploads/products/') === 0) {
                            $img_url = $uploads_base . $firstImage;
                        } else {
                            $img_url = $uploads_base . '/uploads/products/' . ltrim($firstImage, '/');
                        }
                        echo '<img src="' . htmlspecialchars($img_url) . '" class="img-fluid rounded mb-3" alt="Product" onerror="this.src=\'' . $this->baseUrl('img/default-product.jpg') . '\'">';
                    } else {
                        echo '<img src="' . $this->baseUrl('img/default-product.jpg') . '" class="img-fluid rounded mb-3" alt="Default">';
                    }
                    ?>
                    
                    <div class="mb-2">
                        <small class="text-muted">ID</small>
                        <div><?php echo $product['id']; ?></div>
                    </div>

                    <div class="mb-2">
                        <small class="text-muted">SKU</small>
                        <div><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="mb-2">
                        <small class="text-muted">Đã bán</small>
                        <div><?php echo number_format($product['sold'] ?? 0); ?></div>
                    </div>

                    <div class="mb-2">
                        <small class="text-muted">Đánh giá</small>
                        <div>
                            <i class="bx bxs-star text-warning"></i>
                            <?php echo number_format($product['rating'] ?? 0, 1); ?>/5.0
                        </div>
                    </div>

                    <div class="mb-2">
                        <small class="text-muted">Ngày tạo</small>
                        <div><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Seller Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cửa hàng</h5>
                </div>
                <div class="card-body">
                    <?php
                    $avatar = $product['seller_avatar'] ?? null;
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
                    <div class="text-center">
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                             class="rounded-circle mb-2" 
                             width="64" 
                             height="64" 
                             alt="Seller"
                             onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                        <h6><?php echo htmlspecialchars($product['store_name'] ?? 'N/A'); ?></h6>
                        <?php if ($product['seller_id']): ?>
                            <a href="<?php echo $this->baseUrl('sellers/detail/' . $product['seller_id']); ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-store me-1"></i> Xem cửa hàng
                            </a>
                        <?php endif; ?>
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
        $('#edit-product-form').on('submit', function(e) {
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
                url: '<?php echo $this->baseUrl('products/update'); ?>',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: response.message || 'Cập nhật sản phẩm thành công',
                            timer: 2000,
                            showConfirmButton: false,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = '<?php echo $this->baseUrl('products'); ?>';
                        });
                    } else {
                        submitBtn.prop('disabled', false);
                        Swal.fire({
                            icon: 'error',
                            title: 'Cập nhật thất bại!',
                            text: response.message || 'Có lỗi xảy ra khi cập nhật sản phẩm',
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
