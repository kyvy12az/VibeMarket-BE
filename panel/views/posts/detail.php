<?php
$statusMap = [
    'public' => ['label' => 'Công khai', 'color' => 'success'],
    'pending' => ['label' => 'Chờ duyệt', 'color' => 'warning'],
    'hidden' => ['label' => 'Đã ẩn', 'color' => 'secondary'],
    'deleted' => ['label' => 'Đã xóa', 'color' => 'danger']
];

$status_info = $statusMap[$post['status']] ?? ['label' => $post['status'], 'color' => 'secondary'];

// Detect base path
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $base_path = '/VIBE_MARKET_BACKEND/VibeMarket-BE/';
} else {
    $base_path = '/';
}
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?php echo $this->baseUrl('posts'); ?>" class="btn btn-secondary btn-sm">
                <i class="bx bx-arrow-back"></i> Quay lại
            </a>
            <h1 class="h3 d-inline-block ms-3 mb-0"><strong>Chi tiết bài viết #<?php echo $post['id']; ?></strong></h1>
        </div>
        <div>
            <?php if ($post['status'] === 'pending'): ?>
                <button onclick="approvePost(<?php echo $post['id']; ?>)" class="btn btn-success btn-sm">
                    <i class="bx bx-check"></i> Duyệt bài
                </button>
            <?php endif; ?>
            
            <?php if ($post['status'] !== 'hidden' && $post['status'] !== 'deleted'): ?>
                <button onclick="hidePost(<?php echo $post['id']; ?>)" class="btn btn-warning btn-sm">
                    <i class="bx bx-hide"></i> Ẩn bài
                </button>
            <?php elseif ($post['status'] === 'hidden'): ?>
                <button onclick="showPost(<?php echo $post['id']; ?>)" class="btn btn-info btn-sm">
                    <i class="bx bx-show-alt"></i> Hiện bài
                </button>
            <?php endif; ?>
            
            <button onclick="deletePost(<?php echo $post['id']; ?>)" class="btn btn-danger btn-sm">
                <i class="bx bx-trash"></i> Xóa bài
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Nội dung bài viết</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p><strong>Trạng thái:</strong> 
                            <span class="badge bg-<?php echo $status_info['color']; ?>"><?php echo $status_info['label']; ?></span>
                        </p>
                        <p><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?></p>
                        <p><strong>Tương tác:</strong> 
                            <i class="bx bx-heart"></i> <?php echo $post['likes_count'] ?? 0; ?> lượt thích | 
                            <i class="bx bx-comment"></i> <?php echo $post['comments_count'] ?? 0; ?> bình luận
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    </div>

                    <?php if (!empty($post['image'])): ?>
                    <div class="mt-3">
                        <strong>Hình ảnh:</strong>
                        <div class="mt-2">
                            <?php
                            $images = json_decode($post['image'], true);
                            if (is_array($images)) {
                                foreach ($images as $image) {
                                    if (strpos($image, 'uploads/posts/') !== false) {
                                        $imagePath = $base_path . ltrim($image, '/');
                                    } else {
                                        $imagePath = $base_path . 'uploads/posts/' . ltrim($image, '/');
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                         alt="Post image" 
                                         class="img-thumbnail me-2 mb-2" 
                                         style="max-width: 200px;"
                                         onerror="this.style.display='none'">
                                    <?php
                                }
                            } elseif (!empty($post['image'])) {
                                $image = $post['image'];
                                if (strpos($image, 'uploads/posts/') !== false) {
                                    $imagePath = $base_path . ltrim($image, '/');
                                } else {
                                    $imagePath = $base_path . 'uploads/posts/' . ltrim($image, '/');
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="Post image" 
                                     class="img-thumbnail" 
                                     style="max-width: 200px;"
                                     onerror="this.style.display='none'">
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin người đăng</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($post['user_avatar'])): ?>
                    <div class="text-center mb-3">
                        <img src="<?php echo htmlspecialchars($base_path . 'uploads/avatars/' . ltrim($post['user_avatar'], '/')); ?>" 
                             alt="Avatar" 
                             class="rounded-circle" 
                             width="80"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Ccircle fill=%22%23ddd%22 cx=%2250%22 cy=%2250%22 r=%2250%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2240%22%3E?%3C/text%3E%3C/svg%3E'">
                    </div>
                    <?php endif; ?>
                    <p class="mb-2 text-center"><strong><?php echo htmlspecialchars($post['user_name'] ?? 'N/A'); ?></strong></p>
                    <p class="mb-2"><strong>User ID:</strong> <?php echo $post['user_id']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = window.location.pathname.split('/panel')[0] + '/panel/';

function approvePost(id) {
    Swal.fire({
        title: 'Xác nhận',
        text: 'Bạn có chắc chắn muốn duyệt bài viết này?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Duyệt',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: BASE_URL + 'posts/approve',
                type: 'POST',
                data: { post_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                }
            });
        }
    });
}

function hidePost(id) {
    Swal.fire({
        title: 'Xác nhận',
        text: 'Bạn có chắc chắn muốn ẩn bài viết này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ẩn',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: BASE_URL + 'posts/hide',
                type: 'POST',
                data: { post_id: id, action: 'hide' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                }
            });
        }
    });
}

function showPost(id) {
    Swal.fire({
        title: 'Xác nhận',
        text: 'Bạn có chắc chắn muốn hiện bài viết này?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Hiện',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: BASE_URL + 'posts/hide',
                type: 'POST',
                data: { post_id: id, action: 'show' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                }
            });
        }
    });
}

function deletePost(id) {
    Swal.fire({
        title: 'Xác nhận xóa',
        text: 'Bạn có chắc chắn muốn xóa bài viết này? Hành động này không thể hoàn tác!',
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: BASE_URL + 'posts/delete',
                type: 'POST',
                data: { post_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        }).then(() => {
                            window.location.href = BASE_URL + 'posts';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                }
            });
        }
    });
}
</script>
