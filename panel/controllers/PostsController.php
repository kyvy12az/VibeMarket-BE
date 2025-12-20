<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Session.php';

class PostsController extends Controller
{
    private $postModel;

    public function __construct()
    {
        parent::__construct();
        $this->postModel = $this->model('PostModel');
    }

    public function index()
    {
        $this->requireAuth();

        $extra_css = '<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">';
        
        $extra_js = '
        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
        <script src="' . $this->baseUrl('js/posts.js') . '"></script>
        ';

        $this->view('posts/index', [
            'page_title' => 'Quản lý bài viết',
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
            'status' => $_POST['status_filter'] ?? ''
        ];

        $posts = $this->postModel->getPosts($start, $length, $search_value, $filters);
        $total_records = $this->postModel->getPostsCount($search_value, $filters);

        $statusMap = [
            'public' => ['label' => 'Công khai', 'color' => 'success'],
            'pending' => ['label' => 'Chờ duyệt', 'color' => 'warning'],
            'hidden' => ['label' => 'Đã ẩn', 'color' => 'secondary'],
            'deleted' => ['label' => 'Đã xóa', 'color' => 'danger']
        ];

        $data = [];
        foreach ($posts as $post) {
            $status_info = $statusMap[$post['status']] ?? ['label' => $post['status'], 'color' => 'secondary'];
            $status_badge = '<span class="badge bg-' . $status_info['color'] . '">' . $status_info['label'] . '</span>';

            $likes = $post['likes_count'] ?? 0;
            $comments = $post['comments_count'] ?? 0;
            
            $actions = '<div class="btn-group btn-group-sm">';
            
            if ($post['status'] === 'pending') {
                $actions .= '<button class="btn btn-outline-success btn-sm" onclick="approvePost(' . $post['id'] . ')" title="Duyệt bài">
                    <i class="bx bx-check"></i>
                </button>';
            }
            
            if ($post['status'] !== 'hidden' && $post['status'] !== 'deleted') {
                $actions .= '<button class="btn btn-outline-warning btn-sm" onclick="hidePost(' . $post['id'] . ')" title="Ẩn bài">
                    <i class="bx bx-hide"></i>
                </button>';
            } elseif ($post['status'] === 'hidden') {
                $actions .= '<button class="btn btn-outline-info btn-sm" onclick="showPost(' . $post['id'] . ')" title="Hiện bài">
                    <i class="bx bx-show-alt"></i>
                </button>';
            }
            
            $actions .= '<button class="btn btn-outline-primary btn-sm" onclick="viewPost(' . $post['id'] . ')" title="Xem chi tiết">
                <i class="bx bx-show"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="deletePost(' . $post['id'] . ')" title="Xóa bài">
                <i class="bx bx-trash"></i>
            </button>
            </div>';

            $data[] = [
                htmlspecialchars($post['id']),
                htmlspecialchars(mb_substr($post['content'], 0, 100)) . (mb_strlen($post['content']) > 100 ? '...' : ''),
                htmlspecialchars($post['user_name'] ?? 'N/A'),
                $status_badge,
                '<span title="Lượt thích"><i class="bx bx-heart"></i> ' . $likes . '</span> | <span title="Bình luận"><i class="bx bx-comment"></i> ' . $comments . '</span>',
                date('d/m/Y H:i', strtotime($post['created_at'])),
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

    public function detail($id = null)
    {
        $this->requireAuth();

        if (!$id) {
            header('Location: ' . $this->baseUrl('posts'));
            exit;
        }

        $post_id = intval($id);
        $post = $this->postModel->getPostById($post_id);

        if (!$post) {
            $_SESSION['error_message'] = 'Không tìm thấy bài viết';
            header('Location: ' . $this->baseUrl('posts'));
            exit;
        }

        $this->view('posts/detail', [
            'page_title' => 'Chi tiết bài viết #' . $post['id'],
            'post' => $post
        ]);
    }

    public function approve()
    {
        $this->requireAuth();

        $post_id = intval($_POST['post_id']);

        if ($this->postModel->updateStatus($post_id, 'public')) {
            $this->json(['success' => true, 'message' => 'Duyệt bài viết thành công']);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function hide()
    {
        $this->requireAuth();

        $post_id = intval($_POST['post_id']);
        $action = $_POST['action'] ?? 'hide';

        $new_status = $action === 'hide' ? 'hidden' : 'public';

        if ($this->postModel->updateStatus($post_id, $new_status)) {
            $message = $action === 'hide' ? 'Ẩn bài viết thành công' : 'Hiện bài viết thành công';
            $this->json(['success' => true, 'message' => $message]);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function delete()
    {
        $this->requireAuth();

        $post_id = intval($_POST['post_id']);

        if ($this->postModel->updateStatus($post_id, 'deleted')) {
            $this->json(['success' => true, 'message' => 'Xóa bài viết thành công']);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }
}
