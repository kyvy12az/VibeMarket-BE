<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Quản lý sản phẩm</strong></h1>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <select class="form-select" id="category-filter">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category']); ?>">
                                <?= htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="status-filter">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <table id="products-table" class="table table-striped">
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
                        <th>Hành động</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script>
// Wait for jQuery and DataTables to load
window.addEventListener('DOMContentLoaded', function() {
    const loadScript = (src) => {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    };

    const waitForJQuery = () => {
        return new Promise((resolve) => {
            const check = () => {
                if (typeof jQuery !== 'undefined') {
                    resolve();
                } else {
                    setTimeout(check, 50);
                }
            };
            check();
        });
    };

    waitForJQuery().then(() => {
        return loadScript('https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js');
    }).then(() => {
        return loadScript('https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js');
    }).then(() => {
        initializeProductsTable();
    });
});

function initializeProductsTable() {
    $(document).ready(function() {
        const table = $('#products-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?php echo $this->baseUrl('products/getData'); ?>',
                type: 'POST',
                data: function(d) {
                    d.action = 'get_products_data';
                    d.category_filter = $('#category-filter').val();
                    d.status_filter = $('#status-filter').val();
                }
            },
            drawCallback: function() {
                // Re-initialize Bootstrap dropdowns after DataTables draws
                setTimeout(function() {
                    if (typeof bootstrap !== 'undefined') {
                        const dropdownElementList = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                        dropdownElementList.forEach(function(dropdownToggleEl) {
                            if (!bootstrap.Dropdown.getInstance(dropdownToggleEl)) {
                                new bootstrap.Dropdown(dropdownToggleEl);
                            }
                        });
                    }
                }, 100);
            },
            language: {
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
                { data: 0, width: '5%' },
                { data: 1, orderable: false, width: '20%' },
                { data: 2, orderable: false, width: '15%' },
                { data: 3, orderable: false, width: '10%' },
                { data: 4, width: '10%' },
                { data: 5, orderable: false, width: '8%' },
                { data: 6, width: '8%' },
                { data: 7, orderable: false, width: '10%' },
                { data: 8, width: '10%' },
                { data: 9, orderable: false, width: '12%' }
            ]
        });

        $('#category-filter, #status-filter').change(function() {
            table.ajax.reload();
        });

        window.viewProduct = viewProduct;
        window.editProduct = editProduct;
        window.deleteProduct = deleteProduct;
        window.updateProductVisibility = updateProductVisibility;
    });
}

function viewProduct(id) {
    window.location.href = '<?php echo $this->baseUrl('products/detail/'); ?>' + id;
}

function editProduct(id) {
    window.location.href = '<?php echo $this->baseUrl('products/edit/'); ?>' + id;
}

function deleteProduct(id) {
    Swal.fire({
        title: 'Xác nhận xóa?',
        text: 'Bạn có chắc muốn xóa sản phẩm này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?php echo $this->baseUrl('products/delete'); ?>',
                type: 'POST',
                data: {
                    product_id: id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Đã xóa!',
                            text: response.message || 'Xóa sản phẩm thành công',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            $('#products-table').DataTable().ajax.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Lỗi!',
                            text: response.message || 'Có lỗi xảy ra',
                            icon: 'error',
                            confirmButtonText: 'Đóng'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Lỗi!',
                        text: 'Không thể kết nối đến server',
                        icon: 'error',
                        confirmButtonText: 'Đóng'
                    });
                }
            });
        }
    });
}

function updateProductVisibility(productId, visibility) {
    const labels = {
        'approved': 'duyệt',
        'blocked': 'khóa'
    };
    
    Swal.fire({
        title: 'Xác nhận?',
        text: `Bạn có chắc muốn ${labels[visibility]} sản phẩm này?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: visibility === 'approved' ? '#198754' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Xác nhận',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?php echo $this->baseUrl('products/updateVisibility'); ?>',
                type: 'POST',
                data: {
                    product_id: productId,
                    visibility: visibility
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Thành công!',
                            text: response.message || 'Cập nhật trạng thái thành công',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            $('#products-table').DataTable().ajax.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Lỗi!',
                            text: response.message || 'Có lỗi xảy ra',
                            icon: 'error',
                            confirmButtonText: 'Đóng'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Lỗi!',
                        text: 'Không thể kết nối đến server',
                        icon: 'error',
                        confirmButtonText: 'Đóng'
                    });
                }
            });
        }
    });
}
</script>
