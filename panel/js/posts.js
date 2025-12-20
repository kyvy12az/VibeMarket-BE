// Get base URL from current location
const BASE_URL = window.location.pathname.split('/panel')[0] + '/panel/';

$(document).ready(function() {
    const table = $('#posts-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: BASE_URL + 'posts/getData',
            type: 'POST',
            data: function(d) {
                d.status_filter = $('#status-filter').val();
            },
            error: function(xhr, error, code) {
                console.error('DataTables AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    code: code
                });
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi!',
                    text: 'Lỗi khi tải danh sách bài viết. Vui lòng kiểm tra console để xem chi tiết.'
                });
            }
        },
        columns: [
            { data: 0 },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6 }
        ],
        language: {
            processing: 'Đang xử lý...',
            search: 'Tìm kiếm:',
            lengthMenu: 'Hiển thị _MENU_ bài viết',
            info: 'Hiển thị _START_ đến _END_ trong _TOTAL_ bài viết',
            infoEmpty: 'Hiển thị 0 đến 0 trong 0 bài viết',
            infoFiltered: '(lọc từ _MAX_ bài viết)',
            loadingRecords: 'Đang tải...',
            zeroRecords: 'Không tìm thấy bài viết nào',
            emptyTable: 'Không có bài viết',
            paginate: {
                first: 'Đầu',
                previous: 'Trước',
                next: 'Sau',
                last: 'Cuối'
            }
        }
    });

    $('#status-filter').change(function() {
        table.ajax.reload();
    });
});

function viewPost(id) {
    window.location.href = BASE_URL + 'posts/detail/' + id;
}

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
                            $('#posts-table').DataTable().ajax.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi!',
                        text: 'Không thể duyệt bài viết. Vui lòng thử lại sau.',
                        confirmButtonColor: '#3B7DDD'
                    });
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
                            $('#posts-table').DataTable().ajax.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi!',
                        text: 'Không thể ẩn bài viết. Vui lòng thử lại sau.',
                        confirmButtonColor: '#3B7DDD'
                    });
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
                            $('#posts-table').DataTable().ajax.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi!',
                        text: 'Không thể hiện bài viết. Vui lòng thử lại sau.',
                        confirmButtonColor: '#3B7DDD'
                    });
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
                            $('#posts-table').DataTable().ajax.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message,
                            confirmButtonColor: '#3B7DDD'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi!',
                        text: 'Không thể xóa bài viết. Vui lòng thử lại sau.',
                        confirmButtonColor: '#3B7DDD'
                    });
                }
            });
        }
    });
}
