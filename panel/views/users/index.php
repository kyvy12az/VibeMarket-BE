<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Quản lý người dùng</strong></h1>
        <button class="btn btn-success" onclick="exportUsers()">
            <i class="bx bx-download me-1"></i> Export Users
        </button>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-user-circle fs-1 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Tổng người dùng</h6>
                            <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-check-circle fs-1 text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Đang hoạt động</h6>
                            <h3 class="mb-0"><?= number_format($stats['active']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-store fs-1 text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Người bán</h6>
                            <h3 class="mb-0"><?= number_format($stats['vendors']) ?></h3>
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
                    <select class="form-select" id="role-filter">
                        <option value="">Tất cả vai trò</option>
                        <option value="user">User</option>
                        <option value="seller">Seller</option>
                        <option value="admin">Admin</option>
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

            <table id="users-table" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Người dùng</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Đơn hàng</th>
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
    // Load DataTables scripts dynamically
    const loadScript = (src) => {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    };

    // Wait for jQuery to be available
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
        initializeUsersTable();
    });
});

function initializeUsersTable() {
    $(document).ready(function() {
    const table = $('#users-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?php echo $this->baseUrl('users/getData'); ?>',
            type: 'POST',
            data: function(d) {
                d.role_filter = $('#role-filter').val();
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
            { data: 2, width: '18%' },
            { data: 3, width: '8%' },
            { data: 4, orderable: false, width: '8%' },
            { data: 5, orderable: false, width: '10%' },
            { data: 6, width: '12%' },
            { data: 7, orderable: false, width: '12%' }
        ],
        drawCallback: function() {
            // Reinitialize status toggles after table redraw
            $('.status-toggle').off('change').on('change', function() {
                const userId = $(this).data('user-id');
                const status = $(this).is(':checked') ? 'active' : 'inactive';
                
                $.post('<?php echo $this->baseUrl('users/toggleStatus'); ?>', {
                    user_id: userId,
                    status: status
                }, function(response) {
                    if (!response.success) {
                        alert('Có lỗi xảy ra');
                    }
                });
            });
        }
    });

    $('#role-filter, #status-filter').change(function() {
        table.ajax.reload();
    });

    // Expose functions to global scope
    window.viewUser = viewUser;
    window.editUser = editUser;
    window.deleteUser = deleteUser;
    window.exportUsers = exportUsers;
    });
}

function viewUser(id) {
    window.location.href = '<?php echo $this->baseUrl('users/detail/'); ?>' + id;
}

function editUser(id) {
    window.location.href = '<?php echo $this->baseUrl('users/edit/'); ?>' + id;
}

function updateStats(stats) {
    // Update statistics cards with animation
    const cards = [
        { selector: '.col-md-4:nth-child(1) h3', value: stats.total, label: 'Tổng người dùng' },
        { selector: '.col-md-4:nth-child(2) h3', value: stats.active, label: 'Đang hoạt động' },
        { selector: '.col-md-4:nth-child(3) h3', value: stats.vendors, label: 'Người bán' }
    ];
    
    cards.forEach(card => {
        const element = $(card.selector);
        element.fadeOut(300, function() {
            $(this).text(new Intl.NumberFormat('vi-VN').format(card.value)).fadeIn(300);
        });
    });
}

function deleteUser(id) {
    Swal.fire({
        title: 'Xác nhận xóa?',
        text: "Bạn không thể hoàn tác hành động này!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('<?php echo $this->baseUrl('users/delete'); ?>', {
                user_id: id
            }, function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Đã xóa!',
                        text: response.message || 'Người dùng đã được xóa thành công.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Update statistics cards
                        if (response.stats) {
                            updateStats(response.stats);
                        }
                        // Reload table
                        $('#users-table').DataTable().ajax.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Lỗi!',
                        text: response.message || 'Có lỗi xảy ra khi xóa người dùng.',
                        icon: 'error',
                        confirmButtonText: 'Đóng'
                    });
                }
            }).fail(function() {
                Swal.fire({
                    title: 'Lỗi!',
                    text: 'Không thể kết nối đến server.',
                    icon: 'error',
                    confirmButtonText: 'Đóng'
                });
            });
        }
    });
}

function exportUsers() {
    window.open('/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/users-export.php', '_blank');
}
</script>
