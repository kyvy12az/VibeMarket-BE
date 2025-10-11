<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_products_data':
            $draw = intval($_POST['draw']);
            $start = intval($_POST['start']);
            $length = intval($_POST['length']);
            $search_value = $_POST['search']['value'] ?? '';

            $category_filter = $_POST['category_filter'] ?? '';
            $status_filter = $_POST['status_filter'] ?? '';
            $vendor_filter = $_POST['vendor_filter'] ?? '';

            $where_conditions = [];
            $params = [];
            $param_types = '';

            if (!empty($search_value)) {
                $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.seller_name LIKE ?)";
                $search_param = "%{$search_value}%";
                $params = array_merge($params, [$search_param, $search_param, $search_param]);
                $param_types .= 'sss';
            }

            if (!empty($category_filter)) {
                $where_conditions[] = "p.category = ?";
                $params[] = $category_filter;
                $param_types .= 's';
            }

            if (!empty($status_filter)) {
                $where_conditions[] = "p.status = ?";
                $params[] = $status_filter;
                $param_types .= 's';
            }

            if (!empty($vendor_filter)) {
                $where_conditions[] = "p.seller_id = ?";
                $params[] = intval($vendor_filter);
                $param_types .= 'i';
            }

            $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

            $count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";
            if (!empty($params)) {
                $count_stmt = $conn->prepare($count_sql);
                $count_stmt->bind_param($param_types, ...$params);
                $count_stmt->execute();
                $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
                $count_stmt->close();
            } else {
                $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
            }

            $sql = "SELECT p.*, s.store_name, s.avatar AS seller_avatar
                    FROM products p
                    LEFT JOIN seller s ON p.seller_id = s.seller_id
                    $where_clause
                    ORDER BY p.created_at DESC
                    LIMIT ?, ?";
            $params[] = $start;
            $params[] = $length;
            $param_types .= 'ii';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $data = [];
            foreach ($products as $product) {
                // Ảnh sản phẩm
                $images = json_decode($product['image'] ?? '[]', true);
                $imageList = [];
                if (!empty($product['image'])) {
                    $decoded = json_decode($product['image'], true);
                    if (is_array($decoded) && !empty($decoded)) {
                        // Lấy ảnh đầu tiên và chuyển về dạng hợp lệ
                        $productImage = str_replace('\\/', '/', $decoded[0]);
                    } else {
                        $productImage = str_replace('\\/', '/', $product['image']);
                    }
                } else {
                    $productImage = 'img/default-product.jpg';
                }
                // $productImage = !empty($imageList) ? $imageList[0] : 'img/default-product.jpg';

                $product_info = '<div class="d-flex align-items-center">
                    <img src="' . htmlspecialchars($productImage) . '" class="rounded me-2" width="50" height="50" alt="Product" style="object-fit: cover;">
                    <div>
                        <div class="fw-bold">' . htmlspecialchars($product['name']) . '</div>
                        <small class="text-muted">' . htmlspecialchars(substr($product['description'] ?? '', 0, 50)) . '...</small>
                    </div>
                </div>';

                // Xử lý seller avatar
                $sellerAvatar = !empty($product['seller_avatar']) ? $product['seller_avatar'] : 'img/avatars/default.jpg';

                // Hiển thị thông tin seller
                $vendor_info = '<div class="d-flex align-items-center">
                    <img src="' . htmlspecialchars($sellerAvatar) . '" class="rounded-circle me-2" width="32" height="32" alt="Seller">
                    <div>
                        <div class="fw-bold">' . htmlspecialchars($product['store_name'] ?? 'N/A') . '</div>
                    </div>
                </div>';

                $category_badge = $product['category'] ?
                    '<span class="badge bg-secondary">' . htmlspecialchars($product['category']) . '</span>' :
                    '<span class="text-muted">-</span>';

                $price_display = '<div><strong>' . number_format($product['price']) . 'đ</strong>';
                if ($product['original_price'] && $product['original_price'] > $product['price']) {
                    $price_display .= '<br><small class="text-muted text-decoration-line-through">' .
                        number_format($product['original_price']) . 'đ</small>';
                }
                $price_display .= '</div>';

                $stock_class = 'success';
                if ($product['quantity'] == 0)
                    $stock_class = 'danger';
                elseif ($product['quantity'] <= ($product['low_stock'] ?? 10))
                    $stock_class = 'warning';

                $stock_badge = '<span class="badge bg-' . $stock_class . '">' . ($product['quantity'] ?? 0) . '</span>';

                $status_toggle = '<div class="form-check form-switch">
                    <input class="form-check-input status-toggle" type="checkbox" data-product-id="' . $product['id'] . '" ' .
                    ($product['status'] === 'active' ? 'checked' : '') . '>
                </div>';

                $actions = '<div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-sm" onclick="viewProduct(' . $product['id'] . ')" title="Xem chi tiết">
                        <i class="bx bx-show"></i>
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="editProduct(' . $product['id'] . ')" title="Chỉnh sửa">
                        <i class="bx bx-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteProduct(' . $product['id'] . ')" title="Xóa">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>';

                $data[] = [
                    $product['id'],
                    $product_info,
                    $vendor_info,
                    $category_badge,
                    $price_display,
                    $stock_badge,
                    $product['sold'] ?? 0,
                    $status_toggle,
                    date('d/m/Y', strtotime($product['created_at'])),
                    $actions
                ];
            }

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $total_records,
                'recordsFiltered' => $total_records,
                'data' => $data
            ]);
            exit;

        case 'toggle_status':
            $product_id = intval($_POST['product_id']);
            $new_status = $_POST['status'];
            // Chỉ cho phép chuyển giữa active/inactive
            if (!in_array($new_status, ['active', 'inactive'])) {
                echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $product_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'new_status' => $new_status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
            }
            exit;

        case 'delete_product':
            $product_id = intval($_POST['product_id']);

            $product = $conn->query("SELECT image FROM products WHERE id = $product_id")->fetch_assoc();
            if ($product && $product['image']) {
                $images = json_decode($product['image'], true);
                if (is_array($images)) {
                    foreach ($images as $img) {
                        if ($img && file_exists('../' . $img)) {
                            unlink('../' . $img);
                        }
                    }
                }
            }

            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
            }
            exit;
    }
}

// Thống kê sản phẩm
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$stats['active'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'")->fetch_assoc()['count'];
$stats['out_of_stock'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity = 0")->fetch_assoc()['count'];
$stats['low_stock'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity > 0 AND quantity <= 10")->fetch_assoc()['count'];

// Lấy danh sách seller cho filter
$sellers = $conn->query("SELECT seller_id, store_name FROM seller ORDER BY store_name")->fetch_all(MYSQLI_ASSOC);

// Lấy danh mục sản phẩm từ bảng hoặc mảng tĩnh
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}
?>

<?php include "includes/header.php"; ?>
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
                        <h1 class="h3 mb-0"><strong>Quản lý sản phẩm</strong></h1>
                        <button class="btn btn-primary" onclick="exportProducts()">
                            <i class="bx bx-download me-1"></i> Xuất Excel
                        </button>
                    </div>

                    <div class="row mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Tổng số</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-primary">
                                                <i class="bx bx-package"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['total']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Đang bán</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-success">
                                                <i class="bx bx-check-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['active']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Hết hàng</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-danger">
                                                <i class="bx bx-x-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['out_of_stock']); ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Sắp hết</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat text-warning">
                                                <i class="bx bx-error"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?php echo number_format($stats['low_stock']); ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tìm kiếm</label>
                                    <input type="text" id="searchInput" class="form-control"
                                        placeholder="Tìm kiếm sản phẩm...">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Danh mục</label>
                                    <select id="categoryFilter" class="form-select">
                                        <option value="">Tất cả danh mục</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>">
                                                <?php echo htmlspecialchars($category); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Cửa hàng</label>
                                    <select id="vendorFilter" class="form-select">
                                        <option value="">Tất cả cửa hàng</option>
                                        <?php foreach ($sellers as $seller): ?>
                                            <option value="<?php echo $seller['seller_id']; ?>">
                                                <?php echo htmlspecialchars($seller['store_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Trạng thái</label>
                                    <select id="statusFilter" class="form-select">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" id="resetFilters" class="btn btn-outline-secondary">
                                        <i class="bx bx-refresh me-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Danh sách sản phẩm</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="productsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Sản phẩm</th>
                                            <th>Cửa hàng</th>
                                            <th>Danh mục</th>
                                            <th>Giá</th>
                                            <th>Tồn kho</th>
                                            <th>Đã bán</th>
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
        let productsTable;

        $(document).ready(function () {
            productsTable = $('#productsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/products.php',
                    type: 'POST',
                    data: function (d) {
                        d.action = 'get_products_data';
                        d.category_filter = $('#categoryFilter').val();
                        d.vendor_filter = $('#vendorFilter').val();
                        d.status_filter = $('#statusFilter').val();
                        return d;
                    }
                },
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json",
                    processing: "Đang tải dữ liệu...",
                    emptyTable: "Không có dữ liệu",
                    info: "Hiển thị _START_ đến _END_ của _TOTAL_ bản ghi",
                    infoEmpty: "Hiển thị 0 đến 0 của 0 bản ghi",
                    infoFiltered: "(lọc từ _MAX_ bản ghi)",
                    lengthMenu: "Hiển thị _MENU_ bản ghi",
                    search: "Tìm kiếm:",
                    paginate: {
                        first: "Đầu",
                        last: "Cuối",
                        next: "Tiếp",
                        previous: "Trước"
                    }
                },
                columns: [
                    { data: 0, name: 'id', width: '5%' },
                    { data: 1, name: 'product_info', orderable: false, width: '25%' },
                    { data: 2, name: 'vendor_info', orderable: false, width: '12%' },
                    { data: 3, name: 'category', orderable: false, width: '8%' },
                    { data: 4, name: 'price', width: '10%' },
                    { data: 5, name: 'stock', width: '8%' },
                    { data: 6, name: 'sold', width: '6%' },
                    { data: 7, name: 'status', orderable: false, width: '8%' },
                    { data: 8, name: 'created_at', width: '8%' },
                    { data: 9, name: 'actions', orderable: false, searchable: false, width: '10%' }
                ],
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                order: [[0, 'desc']],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="bx bx-download"></i> Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 8] }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="bx bx-file-blank"></i> PDF',
                        className: 'btn btn-danger btn-sm',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 8] }
                    },
                    {
                        extend: 'print',
                        text: '<i class="bx bx-printer"></i> In',
                        className: 'btn btn-info btn-sm',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 8] }
                    }
                ],
                drawCallback: function () {
                    bindEventHandlers();
                }
            });

            $('#searchInput').on('keyup', function () {
                productsTable.search(this.value).draw();
            });

            $('#categoryFilter, #vendorFilter, #statusFilter').on('change', function () {
                productsTable.ajax.reload();
            });

            $('#resetFilters').on('click', function () {
                $('#searchInput').val('');
                $('#categoryFilter').val('');
                $('#vendorFilter').val('');
                $('#statusFilter').val('');
                productsTable.search('').ajax.reload();
            });
        });

        function bindEventHandlers() {
            $('.status-toggle').off('change').on('change', function () {
                const productId = $(this).data('product-id');
                const status = this.checked ? 'active' : 'inactive';
                const toggleElement = this;

                fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=toggle_status&product_id=${productId}&status=${status}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành công!',
                                text: 'Đã cập nhật trạng thái sản phẩm',
                                timer: 2000,
                                showConfirmButton: false,
                            });
                        } else {
                            toggleElement.checked = !toggleElement.checked;
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi!',
                                text: data.message || 'Có lỗi xảy ra',
                            });
                        }
                    })
                    .catch(error => {
                        toggleElement.checked = !toggleElement.checked;
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: 'Có lỗi xảy ra khi gửi yêu cầu',
                        });
                    });
            });
        }

        function deleteProduct(productId) {
            Swal.fire({
                title: 'Bạn có chắc chắn?',
                text: "Hành động này không thể hoàn tác!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Có, xóa!',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/products.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_product&product_id=${productId}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã xóa!',
                                    text: 'Sản phẩm đã được xóa.',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                                productsTable.ajax.reload();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Lỗi!',
                                    text: data.message || 'Có lỗi xảy ra',
                                    toast: true,
                                    position: 'top-end'
                                });
                            }
                        });
                }
            });
        }

        function viewProduct(productId) {
            window.open(`/panel/product-detail.php?id=${productId}`, '_blank');
        }

        function editProduct(productId) {
            window.location.href = `/panel/product-edit.php?id=${productId}`;
        }

        function addProduct() {
            window.location.href = `/panel/product-add.php`;
        }
    </script>
</body>

</html>