<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Session.php';

class ProductsController extends Controller
{
    private $productModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = $this->model('ProductModel');
    }

    public function index()
    {
        $this->requireAuth();

        $categories = $this->productModel->getCategories();
        $vendors = $this->productModel->getVendors();

        $this->view('products/index', [
            'page_title' => 'Quản lý sản phẩm',
            'categories' => $categories,
            'vendors' => $vendors
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
            'category' => $_POST['category_filter'] ?? '',
            'status' => $_POST['status_filter'] ?? '',
            'vendor' => $_POST['vendor_filter'] ?? ''
        ];

        $products = $this->productModel->getProducts($start, $length, $search_value, $filters);
        $total_records = $this->productModel->getProductsCount($search_value, $filters);

        $data = [];
        foreach ($products as $product) {
            // Handle product image
            $uploads_base = $this->getUploadsBaseUrl();
            if (!empty($product['image'])) {
                $decoded = json_decode($product['image'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    $firstImage = $decoded[0];
                    if (strpos($firstImage, '/uploads/products/') === 0) {
                        $productImage = $uploads_base . $firstImage;
                    } else {
                        $productImage = $uploads_base . '/uploads/products/' . ltrim($firstImage, '/');
                    }
                } else {
                    $productImage = $this->baseUrl('img/default-product.jpg');
                }
            } else {
                $productImage = $this->baseUrl('img/default-product.jpg');
            }

            $product_info = '<div class="d-flex align-items-center">
                <img src="' . htmlspecialchars($productImage) . '" class="rounded me-2" width="50" height="50" alt="Product" style="object-fit: cover;">
                <div>
                    <div class="fw-bold">' . htmlspecialchars($product['name']) . '</div>
                    <small class="text-muted">' . htmlspecialchars(substr($product['description'] ?? '', 0, 50)) . '...</small>
                </div>
            </div>';

            // Handle seller avatar
            $avatar = $product['seller_avatar'] ?? null;
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

            $vendor_info = '<div class="d-flex align-items-center">
                <img src="' . htmlspecialchars($avatar_url) . '" class="rounded-circle me-2" width="32" height="32" alt="Seller" onerror="this.src=\'' . $this->baseUrl('img/avatars/default.jpg') . '\'">
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

            // Status badge with visibility
            $status_colors = [
                'active' => 'success',
                'inactive' => 'secondary',
                'deleted' => 'danger'
            ];
            $status_labels = [
                'active' => 'Đang bán',
                'inactive' => 'Ngừng bán',
                'deleted' => 'Đã xóa'
            ];
            $visibility_label = '';
            if ($product['visibility'] === 'blocked') {
                $visibility_label = '<br><span class="badge bg-danger">Bị khóa</span>';
            } elseif ($product['visibility'] === 'pending') {
                $visibility_label = '<br><span class="badge bg-warning">Chờ duyệt</span>';
            }
            $status_color = $status_colors[$product['status']] ?? 'secondary';
            $status_label = $status_labels[$product['status']] ?? $product['status'];
            $status_display = '<span class="badge bg-' . $status_color . '">' . $status_label . '</span>' . $visibility_label;

            $actions = '<div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-sm" onclick="viewProduct(' . $product['id'] . ')" title="Xem chi tiết">
                    <i class="bx bx-show"></i>
                </button>
                <button class="btn btn-outline-warning btn-sm" onclick="editProduct(' . $product['id'] . ')" title="Chỉnh sửa">
                    <i class="bx bx-edit"></i>
                </button>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bx bx-cog"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="updateProductVisibility(' . $product['id'] . ', \'approved\'); return false;">
                            <i class="bx bx-check text-success"></i> Duyệt sản phẩm
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="updateProductVisibility(' . $product['id'] . ', \'blocked\'); return false;">
                            <i class="bx bx-lock text-danger"></i> Khóa sản phẩm
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteProduct(' . $product['id'] . '); return false;">
                            <i class="bx bx-trash"></i> Xóa sản phẩm
                        </a></li>
                    </ul>
                </div>
            </div>';

            $data[] = [
                $product['id'],
                $product_info,
                $vendor_info,
                $category_badge,
                $price_display,
                $stock_badge,
                $product['sold'] ?? 0,
                $status_display,
                date('d/m/Y', strtotime($product['created_at'])),
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

        $product_id = intval($_POST['product_id']);
        $new_status = $_POST['status'];

        if (!in_array($new_status, ['active', 'inactive'])) {
            $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ'], 400);
        }

        if ($this->productModel->updateStatus($product_id, $new_status)) {
            $this->json(['success' => true, 'new_status' => $new_status]);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function viewDetails()
    {
        $this->requireAuth();

        $product_id = intval($_POST['product_id']);
        $product = $this->productModel->getProductById($product_id);

        if ($product) {
            $this->json(['success' => true, 'product' => $product]);
        } else {
            $this->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        }
    }

    public function delete()
    {
        $this->requireAuth();

        $product_id = intval($_POST['product_id']);

        if ($this->productModel->deleteProduct($product_id)) {
            $this->json(['success' => true, 'message' => 'Đã xóa sản phẩm']);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function updateVisibility()
    {
        $this->requireAuth();

        $product_id = intval($_POST['product_id']);
        $visibility = $_POST['visibility'];

        if (!in_array($visibility, ['approved', 'blocked', 'pending'])) {
            $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ'], 400);
            return;
        }

        if ($this->productModel->updateVisibility($product_id, $visibility)) {
            $this->json(['success' => true, 'message' => 'Cập nhật trạng thái thành công', 'visibility' => $visibility]);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function detail($id)
    {
        $this->requireAuth();

        $product_id = intval($id);
        $product = $this->productModel->getProductById($product_id);

        if (!$product) {
            $this->redirect('products?error=product_not_found');
            return;
        }

        $this->view('products/detail', [
            'page_title' => 'Chi tiết sản phẩm',
            'product' => $product
        ]);
    }

    public function edit($id)
    {
        $this->requireAuth();

        $product_id = intval($id);
        $product = $this->productModel->getProductById($product_id);

        if (!$product) {
            $this->redirect('products?error=product_not_found');
            return;
        }

        $categories = $this->productModel->getCategories();

        $this->view('products/edit', [
            'page_title' => 'Chỉnh sửa sản phẩm',
            'product' => $product,
            'categories' => $categories
        ]);
    }

    public function update()
    {
        $this->requireAuth();

        $product_id = intval($_POST['product_id']);
        $data = [
            'name' => trim($_POST['name']),
            'price' => intval($_POST['price']),
            'original_price' => intval($_POST['original_price'] ?? 0),
            'quantity' => intval($_POST['quantity'] ?? 0),
            'category' => trim($_POST['category'] ?? ''),
            'brand' => trim($_POST['brand'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];

        // Validate required fields
        if (empty($data['name']) || $data['price'] <= 0) {
            $this->json(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin'], 400);
            return;
        }

        if (!in_array($data['status'], ['active', 'inactive'])) {
            $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ'], 400);
            return;
        }

        if ($this->productModel->updateProduct($product_id, $data)) {
            $this->json(['success' => true, 'message' => 'Cập nhật sản phẩm thành công']);
        } else {
            $this->json(['success' => false, 'message' => 'Có lỗi xảy ra'], 500);
        }
    }
}
