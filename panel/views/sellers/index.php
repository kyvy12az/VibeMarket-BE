<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Quản lý cửa hàng</strong></h1>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-store fs-1 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Tổng cửa hàng</h6>
                            <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-check-circle fs-1 text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Đã duyệt</h6>
                            <h3 class="mb-0"><?= number_format($stats['approved']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-time fs-1 text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Chờ duyệt</h6>
                            <h3 class="mb-0"><?= number_format($stats['pending']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-lock fs-1 text-dark"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Bị khóa</h6>
                            <h3 class="mb-0"><?= number_format($stats['blocked']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <select class="form-select" id="status-filter">
                        <option value="">Tất cả trạng thái</option>
                        <option value="approved">Đã duyệt</option>
                        <option value="pending">Chờ duyệt</option>
                        <option value="rejected">Từ chối</option>
                        <option value="blocked">Bị khóa</option>
                    </select>
                </div>
            </div>

            <table id="sellers-table" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cửa hàng</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Địa chỉ</th>
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
        initializeSellersTable();
    });
});

function initializeSellersTable() {
    $(document).ready(function() {
        const table = $('#sellers-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?php echo $this->baseUrl('sellers/getData'); ?>',
                type: 'POST',
                data: function(d) {
                    d.status_filter = $('#status-filter').val();
                }
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
                { data: 1, orderable: false, width: '18%' },
                { data: 2, width: '15%' },
                { data: 3, width: '12%' },
                { data: 4, width: '15%' },
                { data: 5, orderable: false, width: '10%' },
                { data: 6, width: '10%' },
                { data: 7, orderable: false, width: '10%' }
            ]
        });

        $('#status-filter').change(function() {
            table.ajax.reload();
        });

        window.viewSeller = viewSeller;
        window.editSeller = editSeller;
    });
}

function viewSeller(id) {
    window.location.href = '<?php echo $this->baseUrl('sellers/detail/'); ?>' + id;
}

function editSeller(id) {
    window.location.href = '<?php echo $this->baseUrl('sellers/edit/'); ?>' + id;
}


function updateStats(stats) {
    // Update statistics cards with animation
    const cards = [
        { selector: '.col-md-3:nth-child(1) h3', value: stats.total, label: 'Tổng cửa hàng' },
        { selector: '.col-md-3:nth-child(2) h3', value: stats.approved, label: 'Đã duyệt' },
        { selector: '.col-md-3:nth-child(3) h3', value: stats.pending, label: 'Chờ duyệt' },
        { selector: '.col-md-3:nth-child(4) h3', value: stats.blocked, label: 'Bị khóa' }
    ];
    
    cards.forEach(card => {
        const element = $(card.selector);
        element.fadeOut(300, function() {
            $(this).text(new Intl.NumberFormat('vi-VN').format(card.value)).fadeIn(300);
        });
    });
}

function updateSellerStatus(sellerId, status) {
    const statusLabels = {
        'approved': 'duyệt',
        'rejected': 'từ chối',
        'pending': 'đặt lại thành chờ duyệt',
        'blocked': 'khóa'
    };

    Swal.fire({
        title: 'Xác nhận?',
        text: `Bạn có chắc muốn ${statusLabels[status]} cửa hàng này?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'approved' ? '#198754' : (status === 'rejected' ? '#dc3545' : (status === 'blocked' ? '#212529' : '#ffc107')),
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Xác nhận',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?php echo $this->baseUrl('sellers/updateStatus'); ?>',
                type: 'POST',
                data: {
                    seller_id: sellerId,
                    status: status
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Thành công!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'Đóng'
                        });

                        // Reload the table
                        $('#sellers-table').DataTable().ajax.reload();

                        // Update statistics cards dynamically
                        if (response.stats) {
                            updateStats(response.stats);
                        }
                    } else {
                        Swal.fire({
                            title: 'Lỗi!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'Đóng'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: 'Lỗi!',
                        text: 'Không thể cập nhật trạng thái. Vui lòng thử lại.',
                        icon: 'error',
                        confirmButtonText: 'Đóng'
                    });
                }
            });
        }
    });
}

</script>
