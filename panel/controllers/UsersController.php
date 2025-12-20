<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Session.php';

class UsersController extends Controller
{
    private $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = $this->model('UserModel');
    }

    public function index()
    {
        $this->requireAuth();

        $stats = $this->userModel->getStats();

        $this->view('users/index', [
            'page_title' => 'Quản lý người dùng',
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
            'role' => $_POST['role_filter'] ?? '',
            'status' => $_POST['status_filter'] ?? ''
        ];

        $users = $this->userModel->getUsers($start, $length, $search_value, $filters);
        $total_records = $this->userModel->getUsersCount($search_value, $filters);

        $data = [];
        foreach ($users as $user) {
            // Fix avatar path - works for both local and host
            $avatar = $user['avatar'] ?? null;
            $uploads_base = $this->getUploadsBaseUrl();
            
            if (empty($avatar)) {
                // No avatar, use default
                $avatar = $this->baseUrl('img/avatars/default.jpg');
            } elseif (filter_var($avatar, FILTER_VALIDATE_URL)) {
                // Valid URL (from Google, Zalo, GitHub, etc.), use as is
                $avatar = $avatar;
            } else {
                // Local path in uploads folder
                if (strpos($avatar, 'uploads/') !== false) {
                    // Already has uploads path, prepend base URL
                    $avatar = $uploads_base . ltrim($avatar, '/');
                } else {
                    // Just filename, prepend uploads/avatars/
                    $avatar = $uploads_base . 'uploads/avatars/' . ltrim($avatar, '/');
                }
            }
            $default_avatar = $this->baseUrl('img/avatars/default.jpg');
            
            $status_toggle = '<div class="form-check form-switch">
                <input class="form-check-input status-toggle" type="checkbox"
                    data-user-id="' . $user['id'] . '"
                    ' . ($user['status'] === 'active' ? 'checked' : '') . '>
            </div>';

            $actions = '<div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-sm" onclick="viewUser(' . $user['id'] . ')">
                    <i class="bx bx-show"></i>
                </button>
                <button class="btn btn-outline-warning btn-sm" onclick="editUser(' . $user['id'] . ')">
                    <i class="bx bx-edit"></i>
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteUser(' . $user['id'] . ')">
                    <i class="bx bx-trash"></i>
                </button>
            </div>';

            $user_info = '<div class="d-flex align-items-center">
                <img src="' . htmlspecialchars($avatar) . '" class="rounded-circle me-2" width="40" height="40" alt="Avatar" 
                     onerror="this.src=\'' . $default_avatar . '\'">
                <div>
                    <div class="fw-bold">' . htmlspecialchars($user['name']) . '</div>
                    <small class="text-muted">' . htmlspecialchars($user['phone'] ?? '') . '</small>
                </div>
            </div>';

            // Role badge with different colors: admin=danger, seller=info, user=secondary
            $role_colors = [
                'admin' => 'danger',
                'seller' => 'info',
                'user' => 'secondary'
            ];
            $role_color = $role_colors[$user['role']] ?? 'secondary';
            $role_badge = '<span class="badge bg-' . $role_color . '">' . ucfirst($user['role']) . '</span>';

            // Get order count
            $order_count_sql = "SELECT COUNT(*) as count FROM orders WHERE customer_id = ?";
            $order_result = $this->userModel->fetchOne($order_count_sql, [$user['id']]);
            $order_count = $order_result ? $order_result['count'] : 0;
            $order_stats = '<span class="badge bg-primary">📦 ' . $order_count . ' đơn</span>';

            $data[] = [
                $user['id'],
                $user_info,
                htmlspecialchars($user['email']),
                $role_badge,
                $status_toggle,
                $order_stats,
                date('d/m/Y', strtotime($user['created_at'])),
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

    public function toggleStatus()
    {
        $this->requireAuth();

        $user_id = intval($_POST['user_id']);
        $new_status = $_POST['status'];

        if (!in_array($new_status, ['active', 'inactive'])) {
            $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ'], 400);
        }

        if ($this->userModel->updateStatus($user_id, $new_status)) {
            $this->json(['success' => true, 'new_status' => $new_status]);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function viewDetails()
    {
        $this->requireAuth();

        $user_id = intval($_POST['user_id']);
        $user = $this->userModel->getUserById($user_id);

        if ($user) {
            $this->json(['success' => true, 'user' => $user]);
        } else {
            $this->json(['success' => false, 'message' => 'Không tìm thấy người dùng'], 404);
        }
    }

    public function delete()
    {
        $this->requireAuth();

        $user_id = intval($_POST['user_id']);

        if ($this->userModel->delete($user_id)) {
            // Get updated stats
            $stats = $this->userModel->getStats();
            $this->json([
                'success' => true, 
                'message' => 'Đã xóa người dùng',
                'stats' => $stats
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function getStats()
    {
        $this->requireAuth();
        $stats = $this->userModel->getStats();
        $this->json(['success' => true, 'stats' => $stats]);
    }

    public function detail($id)
    {
        $this->requireAuth();

        $user_id = intval($id);
        $user = $this->userModel->getUserById($user_id);

        if (!$user) {
            $this->redirect('users?error=user_not_found');
            return;
        }

        // Get user details with statistics
        $details = $this->userModel->getUserDetails($user_id);

        $this->view('users/detail', [
            'page_title' => 'Chi tiết người dùng',
            'user' => $user,
            'stats' => $details['stats'],
            'recent_orders' => $details['recent_orders'],
            'monthly_activity' => $details['monthly_activity'],
            'posts' => $details['posts'],
            'network_stats' => $details['network_stats'],
            'followers_list' => $details['followers_list'],
            'following_list' => $details['following_list']
        ]);
    }

    public function edit($id)
    {
        $this->requireAuth();

        $user_id = intval($id);
        $user = $this->userModel->getUserById($user_id);

        if (!$user) {
            $this->redirect('users?error=user_not_found');
            return;
        }

        $this->view('users/edit', [
            'page_title' => 'Chỉnh sửa người dùng',
            'user' => $user
        ]);
    }

    public function update()
    {
        $this->requireAuth();

        $user_id = intval($_POST['user_id']);
        $data = [
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'role' => $_POST['role'],
            'status' => $_POST['status']
        ];

        // Validate required fields
        if (empty($data['name']) || empty($data['email'])) {
            $this->json(['success' => false, 'message' => 'Tên và email là bắt buộc'], 400);
            return;
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Email không hợp lệ'], 400);
            return;
        }

        // Check if email already exists (except current user)
        if ($this->userModel->emailExists($data['email'], $user_id)) {
            $this->json(['success' => false, 'message' => 'Email đã tồn tại'], 400);
            return;
        }

        // Update password if provided
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        if ($this->userModel->updateUser($user_id, $data)) {
            $this->json(['success' => true, 'message' => 'Cập nhật thành công']);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }
}
