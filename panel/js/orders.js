// Get base URL from current location
const BASE_URL = window.location.pathname.split('/panel')[0] + '/panel/';

$(document).ready(function() {
    const table = $('#orders-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: BASE_URL + 'orders/getData',
            type: 'POST',
            data: function(d) {
                d.action = 'get_orders_data';
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
                    text: 'Lỗi khi tải danh sách đơn hàng. Vui lòng kiểm tra console để xem chi tiết.'
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
            { data: 6 },
            { data: 7 }
        ],
        language: {
            processing: 'Đang xử lý...',
            search: 'Tìm kiếm:',
            lengthMenu: 'Hiển thị _MENU_ đơn hàng',
            info: 'Hiển thị _START_ đến _END_ trong _TOTAL_ đơn hàng',
            infoEmpty: 'Hiển thị 0 đến 0 trong 0 đơn hàng',
            infoFiltered: '(lọc từ _MAX_ đơn hàng)',
            loadingRecords: 'Đang tải...',
            zeroRecords: 'Không tìm thấy đơn hàng nào',
            emptyTable: 'Không có đơn hàng',
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

function viewOrder(id) {
    window.location.href = BASE_URL + 'orders/detail/' + id;
}

function editOrder(id) {
    window.location.href = BASE_URL + 'orders/edit/' + id;
}
