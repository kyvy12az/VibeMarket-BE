<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Chi tiết sản phẩm</strong></h1>
        <div>
            <a href="<?php echo $this->baseUrl('products/edit/' . $product['id']); ?>" class="btn btn-warning me-2">
                <i class="bx bx-edit me-1"></i> Chỉnh sửa
            </a>
            <a href="<?php echo $this->baseUrl('products'); ?>" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i> Quay lại
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Product Images -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Hình ảnh sản phẩm</h5>
                </div>
                <div class="card-body p-0">
                    <?php
                    $uploads_base = $this->getUploadsBaseUrl();
                    $images = json_decode($product['image'] ?? '[]', true);
                    if (!empty($images) && is_array($images)) {
                        ?>
                        <style>
                            .product-carousel {
                                position: relative;
                                background-color: #f8f9fa;
                            }
                            .product-carousel .carousel-control-prev,
                            .product-carousel .carousel-control-next {
                                width: 50px;
                                background-color: rgba(0, 0, 0, 0.3);
                            }
                            .product-carousel .carousel-control-prev:hover,
                            .product-carousel .carousel-control-next:hover {
                                background-color: rgba(0, 0, 0, 0.5);
                            }
                            .product-carousel .carousel-indicators {
                                bottom: 10px;
                            }
                            .product-carousel .carousel-indicators button {
                                width: 10px;
                                height: 10px;
                                border-radius: 50%;
                                background-color: rgba(0, 0, 0, 0.5);
                            }
                            .product-carousel .carousel-indicators button.active {
                                background-color: #0d6efd;
                            }
                        </style>
                        <div id="productImagesCarousel" class="carousel slide product-carousel" data-bs-ride="carousel">
                            <!-- Indicators -->
                            <div class="carousel-indicators">
                                <?php foreach ($images as $index => $img): ?>
                                    <button type="button" 
                                            data-bs-target="#productImagesCarousel" 
                                            data-bs-slide-to="<?php echo $index; ?>" 
                                            <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?>
                                            aria-label="Slide <?php echo $index + 1; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Slides -->
                            <div class="carousel-inner">
                                <?php foreach ($images as $index => $img): 
                                    if (strpos($img, '/uploads/products/') === 0) {
                                        $img_url = $uploads_base . $img;
                                    } else {
                                        $img_url = $uploads_base . '/uploads/products/' . ltrim($img, '/');
                                    }
                                ?>
                                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars($img_url); ?>" 
                                             class="d-block w-100" 
                                             alt="Product Image <?php echo $index + 1; ?>"
                                             style="height: 400px; object-fit: contain; background-color: #f8f9fa;"
                                             onerror="this.src='<?php echo $this->baseUrl('img/default-product.jpg'); ?>'">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Controls -->
                            <button class="carousel-control-prev" type="button" data-bs-target="#productImagesCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#productImagesCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        </div>
                        <?php
                    } else {
                        echo '<img src="' . $this->baseUrl('img/default-product.jpg') . '" class="img-fluid" alt="Default" style="height: 400px; width: 100%; object-fit: contain; background-color: #f8f9fa;">';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Product Info -->
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin sản phẩm</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="200">ID</th>
                            <td><?php echo $product['id']; ?></td>
                        </tr>
                        <tr>
                            <th>Tên sản phẩm</th>
                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>SKU</th>
                            <td><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Mô tả</th>
                            <td><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th>Danh mục</th>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></span></td>
                        </tr>
                        <tr>
                            <th>Thương hiệu</th>
                            <td><?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Giá bán</th>
                            <td>
                                <strong class="text-primary"><?php echo number_format($product['price']); ?>đ</strong>
                                <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                                    <small class="text-muted text-decoration-line-through ms-2"><?php echo number_format($product['original_price']); ?>đ</small>
                                    <span class="badge bg-danger ms-2">-<?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>%</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Giá gốc</th>
                            <td><?php echo number_format($product['original_price'] ?? 0); ?>đ</td>
                        </tr>
                        <tr>
                            <th>Tồn kho</th>
                            <td>
                                <?php
                                $stock_class = 'success';
                                if ($product['quantity'] == 0) $stock_class = 'danger';
                                elseif ($product['quantity'] <= ($product['low_stock'] ?? 10)) $stock_class = 'warning';
                                ?>
                                <span class="badge bg-<?php echo $stock_class; ?>"><?php echo $product['quantity'] ?? 0; ?></span>
                                <?php if ($product['low_stock']): ?>
                                    <small class="text-muted ms-2">(Cảnh báo khi < <?php echo $product['low_stock']; ?>)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Đã bán</th>
                            <td><?php echo number_format($product['sold'] ?? 0); ?></td>
                        </tr>
                        <tr>
                            <th>Đánh giá</th>
                            <td>
                                <i class="bx bxs-star text-warning"></i>
                                <?php echo number_format($product['rating'] ?? 0, 1); ?>/5.0
                            </td>
                        </tr>
                        <tr>
                            <th>Trạng thái</th>
                            <td>
                                <?php
                                $status_colors = ['active' => 'success', 'inactive' => 'secondary', 'deleted' => 'danger'];
                                $status_labels = ['active' => 'Đang bán', 'inactive' => 'Ngừng bán', 'deleted' => 'Đã xóa'];
                                $status_color = $status_colors[$product['status']] ?? 'secondary';
                                $status_label = $status_labels[$product['status']] ?? $product['status'];
                                ?>
                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_label; ?></span>
                                <?php if ($product['visibility'] === 'blocked'): ?>
                                    <span class="badge bg-danger ms-2">Bị khóa</span>
                                <?php elseif ($product['visibility'] === 'pending'): ?>
                                    <span class="badge bg-warning ms-2">Chờ duyệt</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Ngày tạo</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($product['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Seller Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin cửa hàng</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
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
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                             class="rounded-circle me-3" 
                             width="64" 
                             height="64" 
                             alt="Seller"
                             onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'">
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($product['store_name'] ?? 'N/A'); ?></h5>
                            <?php if ($product['seller_id']): ?>
                                <a href="<?php echo $this->baseUrl('sellers/detail/' . $product['seller_id']); ?>" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bx bx-store me-1"></i> Xem cửa hàng
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
