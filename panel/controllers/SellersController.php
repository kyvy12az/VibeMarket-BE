<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Session.php';

class SellersController extends Controller
{
    private $sellerModel;

    public function __construct()
    {
        parent::__construct();
        $this->sellerModel = $this->model('SellerModel');
    }

    public function index()
    {
        $this->requireAuth();

        $stats = $this->sellerModel->getStats();

        $this->view('sellers/index', [
            'page_title' => 'Quản lý cửa hàng',
            'stats' => $stats
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
            'status' => $_POST['status_filter'] ?? ''
        ];

        $sellers = $this->sellerModel->getSellers($start, $length, $search_value, $filters);
        $total_records = $this->sellerModel->getSellersCount($search_value, $filters);

        $data = [];
        foreach ($sellers as $seller) {
            // Fix avatar path
            $avatar = $seller['avatar'] ?? null;
            $uploads_base = $this->getUploadsBaseUrl();
            
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
            $default_avatar = $this->baseUrl('img/avatars/default.jpg');

            $seller_info = '<div class="d-flex align-items-center">
                <img src="' . htmlspecialchars($avatar_url) . '" class="rounded-circle me-2" width="40" height="40" alt="Avatar"
                     onerror="this.src=\'' . $default_avatar . '\'">
                <div>
                    <div class="fw-bold">' . htmlspecialchars($seller['store_name']) . '</div>
                    <small class="text-muted">' . htmlspecialchars($seller['owner_name'] ?? '') . '</small>
                </div>
            </div>';

            // Status badge instead of toggle
            $status_colors = [
                'approved' => 'success',
                'pending' => 'warning',
                'rejected' => 'danger',
                'blocked' => 'dark'
            ];
            $status_labels = [
                'approved' => 'Đã duyệt',
                'pending' => 'Chờ duyệt',
                'rejected' => 'Từ chối',
                'blocked' => 'Bị khóa'
            ];
            $status_color = $status_colors[$seller['status']] ?? 'secondary';
            $status_label = $status_labels[$seller['status']] ?? $seller['status'];
            
            $status_badge = '<span class="badge bg-' . $status_color . '">' . $status_label . '</span>';

            $actions = '<div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-sm" onclick="viewSeller(' . $seller['seller_id'] . ')">
                    <i class="bx bx-show"></i>
                </button>
                <button class="btn btn-outline-warning btn-sm" onclick="editSeller(' . $seller['seller_id'] . ')">
                    <i class="bx bx-edit"></i>
                </button>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bx bx-cog"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="updateSellerStatus(' . $seller['seller_id'] . ', \'approved\'); return false;">
                            <i class="bx bx-check text-success"></i> Duyệt
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="updateSellerStatus(' . $seller['seller_id'] . ', \'rejected\'); return false;">
                            <i class="bx bx-x text-danger"></i> Từ chối
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="updateSellerStatus(' . $seller['seller_id'] . ', \'pending\'); return false;">
                            <i class="bx bx-time text-warning"></i> Chờ duyệt
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="updateSellerStatus(' . $seller['seller_id'] . ', \'blocked\'); return false;">
                            <i class="bx bx-lock text-dark"></i> Khóa cửa hàng
                        </a></li>
                    </ul>
                </div>
            </div>';

            $data[] = [
                $seller['seller_id'],
                $seller_info,
                htmlspecialchars($seller['email'] ?? ''),
                htmlspecialchars($seller['phone'] ?? ''),
                htmlspecialchars($seller['business_address'] ?? ''),
                $status_badge,
                date('d/m/Y', strtotime($seller['created_at'])),
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

    public function updateStatus()
    {
        $this->requireAuth();

        $seller_id = intval($_POST['seller_id']);
        $new_status = $_POST['status'];

        if (!in_array($new_status, ['approved', 'pending', 'rejected', 'blocked'])) {
            $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ'], 400);
        }

        if ($this->sellerModel->updateStatus($seller_id, $new_status)) {
            // If the status is approved, update the user's role to 'seller'
            if ($new_status === 'approved') {
                $user_id = $this->sellerModel->getUserIdBySellerId($seller_id);
                if ($user_id) {
                    $this->sellerModel->updateUserRole($user_id, 'seller');
                }
            }

            // If the status is rejected, update the user's role to 'user'
            if ($new_status === 'rejected') {
                $user_id = $this->sellerModel->getUserIdBySellerId($seller_id);
                if ($user_id) {
                    $this->sellerModel->updateUserRole($user_id, 'user');
                }
            }

            $this->json(['success' => true, 'message' => 'Cập nhật trạng thái thành công', 'new_status' => $new_status]);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function viewDetails()
    {
        $this->requireAuth();

        $seller_id = intval($_POST['seller_id']);
        $seller = $this->sellerModel->getSellerById($seller_id);

        if ($seller) {
            $this->json(['success' => true, 'seller' => $seller]);
        } else {
            $this->json(['success' => false, 'message' => 'Không tìm thấy cửa hàng'], 404);
        }
    }

    public function getStats()
    {
        $this->requireAuth();
        $stats = $this->sellerModel->getStats();
        $this->json(['success' => true, 'stats' => $stats]);
    }

    public function detail($id)
    {
        $this->requireAuth();

        $seller_id = intval($id);
        $seller = $this->sellerModel->getSellerById($seller_id);

        if (!$seller) {
            $this->redirect('sellers?error=seller_not_found');
            return;
        }

        $stats = $this->sellerModel->getSellerStats($seller_id);
        $revenue_by_month = $this->sellerModel->getRevenueByMonth($seller_id, 6);
        $top_products = $this->sellerModel->getTopProducts($seller_id, 5);

        $this->view('sellers/detail', [
            'page_title' => 'Chi tiết cửa hàng',
            'seller' => $seller,
            'stats' => $stats,
            'revenue_by_month' => $revenue_by_month,
            'top_products' => $top_products
        ]);
    }

    public function edit($id)
    {
        $this->requireAuth();

        $seller_id = intval($id);
        $seller = $this->sellerModel->getSellerById($seller_id);

        if (!$seller) {
            $this->redirect('sellers?error=seller_not_found');
            return;
        }

        $total_revenue = $this->sellerModel->getTotalRevenue($seller_id);

        $this->view('sellers/edit', [
            'page_title' => 'Chỉnh sửa cửa hàng',
            'seller' => $seller,
            'total_revenue' => $total_revenue
        ]);
    }

    public function update()
    {
        $this->requireAuth();

        $seller_id = intval($_POST['seller_id']);
        $data = [
            'store_name' => trim($_POST['store_name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'business_address' => trim($_POST['business_address'] ?? ''),
            'business_type' => trim($_POST['business_type'] ?? ''),
            'tax_id' => trim($_POST['tax_id'] ?? ''),
            'establish_year' => intval($_POST['establish_year'] ?? 0),
            'description' => trim($_POST['description'] ?? '')
        ];

        // Validate required fields
        if (empty($data['store_name'])) {
            $this->json(['success' => false, 'message' => 'Tên cửa hàng là bắt buộc'], 400);
            return;
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Email không hợp lệ'], 400);
            return;
        }

        if ($this->sellerModel->updateSeller($seller_id, $data)) {
            $this->json(['success' => true, 'message' => 'Cập nhật thông tin thành công']);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }
}
