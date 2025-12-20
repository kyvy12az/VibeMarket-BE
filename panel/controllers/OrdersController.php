<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Session.php';

class OrdersController extends Controller
{
    private $orderModel;

    public function __construct()
    {
        parent::__construct();
        $this->orderModel = $this->model('OrderModel');
    }

    public function index()
    {
        $this->requireAuth();

        $extra_css = '<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">';
        
        $extra_js = '
        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
        <script src="' . $this->baseUrl('js/orders.js') . '"></script>
        ';

        $this->view('orders/index', [
            'page_title' => 'Quản lý đơn hàng',
            'extra_css' => $extra_css,
            'extra_js' => $extra_js
        ]);
    }

    public function getData()
    {
        $this->requireAuth();

        $draw = intval($_POST['draw']);
        $start = intval($_POST['start']);
        $length = intval($_POST['length']);
        $search_value = $_POST['search']['value'] ?? '';

        $filters = [
            'status' => $_POST['status_filter'] ?? '',
            'payment_method' => $_POST['payment_filter'] ?? ''
        ];

        $orders = $this->orderModel->getOrders($start, $length, $search_value, $filters);
        $total_records = $this->orderModel->getOrdersCount($search_value, $filters);

        $statusMap = [
            'pending' => ['label' => 'Chờ xử lý', 'color' => 'warning'],
            'processing' => ['label' => 'Đang xử lý', 'color' => 'info'],
            'shipped' => ['label' => 'Đã gửi', 'color' => 'primary'],
            'shipping' => ['label' => 'Đang giao', 'color' => 'info'],
            'delivered' => ['label' => 'Đã giao', 'color' => 'success'],
            'cancelled' => ['label' => 'Đã hủy', 'color' => 'danger'],
            'returned' => ['label' => 'Đã trả', 'color' => 'secondary']
        ];

        $data = [];
        foreach ($orders as $order) {
            $status_info = $statusMap[$order['status']] ?? ['label' => $order['status'], 'color' => 'secondary'];
            $status_badge = '<span class="badge bg-' . $status_info['color'] . '">' . $status_info['label'] . '</span>';

            $actions = '<div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-sm" onclick="viewOrder(' . $order['id'] . ')" title="Xem chi tiết">
                    <i class="bx bx-show"></i>
                </button>
                <button class="btn btn-outline-warning btn-sm" onclick="editOrder(' . $order['id'] . ')" title="Cập nhật trạng thái">
                    <i class="bx bx-edit"></i>
                </button>
            </div>';

            $paymentMap = [
                'cod' => 'Tiền mặt',
                'vnpay' => 'VNPay',
                'momo' => 'MoMo',
                'zalopay' => 'ZaloPay',
                'banking' => 'Chuyển khoản'
            ];
            $payment_label = $paymentMap[$order['payment_method']] ?? $order['payment_method'];

            $data[] = [
                htmlspecialchars($order['code']),
                htmlspecialchars($order['customer_name']),
                htmlspecialchars($order['email'] ?? 'N/A'),
                number_format($order['total']) . ' VNĐ',
                htmlspecialchars($payment_label),
                $status_badge,
                date('d/m/Y H:i', strtotime($order['created_at'])),
                $actions
            ];
        }

        $this->json([
            'draw' => $draw,
            'recordsTotal' => $total_records,
            'recordsFiltered' => $total_records,
            'data' => $data
        ]);
    }

    public function viewDetails()
    {
        $this->requireAuth();

        $order_id = intval($_POST['order_id']);
        $order = $this->orderModel->getOrderById($order_id);

        if ($order) {
            $order['items'] = $this->orderModel->getOrderItems($order_id);
            $this->json(['success' => true, 'order' => $order]);
        } else {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }
    }

    public function detail($id = null)
    {
        $this->requireAuth();

        if (!$id) {
            header('Location: ' . $this->baseUrl('orders'));
            exit;
        }

        $order_id = intval($id);
        $order = $this->orderModel->getOrderById($order_id);

        if (!$order) {
            $_SESSION['error_message'] = 'Không tìm thấy đơn hàng';
            header('Location: ' . $this->baseUrl('orders'));
            exit;
        }

        $order['items'] = $this->orderModel->getOrderItems($order_id);
        
        $this->view('orders/detail', [
            'page_title' => 'Chi tiết đơn hàng #' . $order['code'],
            'order' => $order
        ]);
    }

    public function edit($id = null)
    {
        $this->requireAuth();

        if (!$id) {
            header('Location: ' . $this->baseUrl('orders'));
            exit;
        }

        $order_id = intval($id);
        $order = $this->orderModel->getOrderById($order_id);

        if (!$order) {
            $_SESSION['error_message'] = 'Không tìm thấy đơn hàng';
            header('Location: ' . $this->baseUrl('orders'));
            exit;
        }

        $extra_js = '
        <script>
        const BASE_URL = window.location.pathname.split(\'/panel\')[0] + \'/panel/\';
        
        $(document).ready(function() {
            $("#updateStatusForm").on("submit", function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                Swal.fire({
                    title: "Xác nhận",
                    text: "Bạn có chắc chắn muốn cập nhật trạng thái đơn hàng?",
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Cập nhật",
                    cancelButtonText: "Hủy",
                    confirmButtonColor: "#3B7DDD",
                    cancelButtonColor: "#6c757d"
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: BASE_URL + "orders/updateStatus",
                            type: "POST",
                            data: formData,
                            dataType: "json",
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: "success",
                                        title: "Thành công!",
                                        text: response.message,
                                        confirmButtonColor: "#3B7DDD"
                                    }).then(() => {
                                        window.location.href = BASE_URL + "orders/detail/' . $order_id . '";
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Lỗi!",
                                        text: response.message,
                                        confirmButtonColor: "#3B7DDD"
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: "error",
                                    title: "Lỗi!",
                                    text: "Không thể cập nhật trạng thái. Vui lòng thử lại sau.",
                                    confirmButtonColor: "#3B7DDD"
                                });
                            }
                        });
                    }
                });
            });
        });
        </script>
        ';

        $this->view('orders/edit', [
            'page_title' => 'Cập nhật đơn hàng #' . $order['code'],
            'order' => $order,
            'extra_js' => $extra_js
        ]);
    }

    public function updateStatus()
    {
        $this->requireAuth();

        $order_id = intval($_POST['order_id']);
        $new_status = $_POST['status'];

        $allowed_statuses = ['pending', 'processing', 'shipped', 'shipping', 'delivered', 'cancelled', 'returned'];
        if (!in_array($new_status, $allowed_statuses)) {
            $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ'], 400);
            return;
        }

        if ($this->orderModel->updateStatus($order_id, $new_status)) {
            $this->json(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }
}
