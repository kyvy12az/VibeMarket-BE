<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php');
    exit;
}

$stats = [
    'total_posts' => 150,
    'questions' => 45,
    'reviews' => 80,
    'reported' => 5
];

include "includes/header.php";
?>

<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main">
            <?php include 'includes/navbar.php'; ?>

            <main class="content">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0"><strong>Quản lý bài viết</strong></h1>
                        <button class="btn btn-primary" onclick="createPost()">
                            <i class="bx bx-plus me-1"></i> Xuất file
                        </button>
                    </div>

                    <div class="row mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Tổng bài viết</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-primary">
                                                <i class="bx bx-news"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['total_posts']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Câu hỏi</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-success">
                                                <i class="bx bx-question-mark"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['questions']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Reviews</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-info">
                                                <i class="bx bx-star"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['reviews']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Báo cáo</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-danger">
                                                <i class="bx bx-error-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['reported']); ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tìm kiếm</label>
                                    <input type="text" id="searchInput" class="form-control" placeholder="Tìm tiêu đề, nội dung...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Loại bài viết</label>
                                    <select id="typeFilter" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="review">Review</option>
                                        <option value="question">Câu hỏi</option>
                                        <option value="sharing">Chia sẻ</option>
                                        <option value="livestream">Livestream</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select id="statusFilter" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="active">Hiển thị</option>
                                        <option value="hidden">Đã ẩn</option>
                                        <option value="reported">Bị báo cáo</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" id="resetFilters" class="btn btn-outline-secondary w-100">
                                        <i class="bx bx-refresh me-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Danh sách bài viết cộng đồng</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover w-100" id="postsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nội dung bài viết</th>
                                            <th>Tác giả</th>
                                            <th>Loại</th>
                                            <th>Tương tác</th>
                                            <th>Trạng thái</th>
                                            <th>Ngày tạo</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let postsTable;

        $(document).ready(function () {
            // Khởi tạo DataTable
            postsTable = $('#postsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    // Bạn cần tạo file community_data.php hoặc trỏ về controller xử lý lấy dữ liệu
                    url: '/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/community_data.php', 
                    type: 'POST',
                    data: function (d) {
                        d.action = 'get_posts_data';
                        d.type_filter = $('#typeFilter').val();
                        d.status_filter = $('#statusFilter').val();
                        return d;
                    }
                },
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json"
                },
                columns: [
                    { data: 0, name: 'id', width: '5%' },
                    { data: 1, name: 'content', width: '30%' }, // Cột này chứa Ảnh + Tiêu đề
                    { data: 2, name: 'author', width: '15%' },
                    { data: 3, name: 'type', width: '10%' },
                    { data: 4, name: 'interactions', orderable: false, width: '10%' }, // Like/Comment
                    { data: 5, name: 'status', width: '10%' },
                    { data: 6, name: 'created_at', width: '10%' },
                    { data: 7, name: 'actions', orderable: false, searchable: false, width: '10%' }
                ],
                order: [[0, 'desc']], // Sắp xếp ID giảm dần
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="bx bx-download"></i> Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                    },
                    {
                        extend: 'print',
                        text: '<i class="bx bx-printer"></i> In',
                        className: 'btn btn-info btn-sm',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                    }
                ],
                drawCallback: function () {
                    bindEventHandlers();
                }
            });

            // Sự kiện tìm kiếm & Filter
            $('#searchInput').on('keyup', function () {
                postsTable.search(this.value).draw();
            });

            $('#typeFilter, #statusFilter').on('change', function () {
                postsTable.ajax.reload();
            });

            $('#resetFilters').on('click', function () {
                $('#searchInput').val('');
                $('#typeFilter').val('');
                $('#statusFilter').val('');
                postsTable.search('').ajax.reload();
            });
        });

        // Hàm bind sự kiện cho các nút toggle/delete sau khi render bảng
        function bindEventHandlers() {
            $('.status-toggle').off('change').on('change', function () {
                const postId = $(this).data('post-id');
                const status = this.checked ? 'active' : 'hidden';
                const toggleElement = this;

                // Gọi API cập nhật trạng thái
                fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/community_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=toggle_status&post_id=${postId}&status=${status}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        Toast.fire({
                            icon: 'success',
                            title: 'Cập nhật trạng thái thành công'
                        });
                    } else {
                        toggleElement.checked = !toggleElement.checked; // Revert
                        Swal.fire('Lỗi!', 'Không thể cập nhật trạng thái.', 'error');
                    }
                })
                .catch(() => {
                    toggleElement.checked = !toggleElement.checked;
                    Swal.fire('Lỗi mạng!', 'Vui lòng thử lại sau.', 'error');
                });
            });
        }

        // Hàm xóa bài viết
        function deletePost(postId) {
            Swal.fire({
                title: 'Xóa bài viết này?',
                text: "Hành động này không thể hoàn tác!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xóa ngay',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/community_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_post&post_id=${postId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Đã xóa!',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000
                            });
                            postsTable.ajax.reload();
                        } else {
                            Swal.fire('Lỗi!', 'Không thể xóa bài viết.', 'error');
                        }
                    });
                }
            });
        }

        function createPost() {
        }
    </script>
</body>
</html>