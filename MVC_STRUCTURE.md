# CẤU TRÚC MVC CHO VIBEMARKET BACKEND

## Tổng quan

Backend đã được tái cấu trúc theo mô hình **MVC (Model-View-Controller)**, trong đó:
- **Model**: Quản lý dữ liệu và logic nghiệp vụ
- **Controller**: Xử lý request và response
- **View**: Kết nối với Frontend (API JSON)

## Cấu trúc thư mục

```
VibeMarket-BE/
├── models/              # Models - Quản lý dữ liệu
│   ├── BaseModel.php    # Class cơ sở cho tất cả models
│   ├── User.php         # Model User
│   ├── Product.php      # Model Product
│   ├── Order.php        # Model Order
│   ├── OrderItem.php    # Model OrderItem
│   ├── Vendor.php       # Model Vendor/Seller
│   └── Review.php       # Model Review
│
├── controllers/         # Controllers - Xử lý logic
│   ├── BaseController.php    # Class cơ sở cho controllers
│   ├── AuthController.php    # Xác thực & đăng ký
│   ├── ProductController.php # Quản lý sản phẩm
│   ├── OrderController.php   # Quản lý đơn hàng
│   ├── VendorController.php  # Quản lý vendor
│   ├── ReviewController.php  # Quản lý đánh giá
│   └── UserController.php    # Quản lý user
│
├── api/                 # API endpoints (có thể giữ hoặc chuyển sang router)
├── config/              # Cấu hình
└── api_router_example.php  # Ví dụ router tập trung
```

## BaseModel - Class cơ sở

BaseModel cung cấp các phương thức CRUD cơ bản:

### Phương thức chính:

```php
// Lấy tất cả records
$products = $model->getAll($conditions, $orderBy, $limit, $offset);

// Tìm theo ID
$user = $model->findById($id);

// Tìm một record theo điều kiện
$user = $model->findOne(['email' => 'test@example.com']);

// Tạo mới
$id = $model->create($data);

// Cập nhật
$model->update($id, $data);

// Xóa
$model->delete($id);

// Đếm
$total = $model->count($conditions);

// Query tùy chỉnh
$result = $model->query($sql, $params, $types);
```

## BaseController - Class cơ sở

BaseController cung cấp các tiện ích:

### Phương thức chính:

```php
// Trả về JSON response
$this->json($data, $statusCode);

// Trả về success
$this->success($data, $message, $statusCode);

// Trả về error
$this->error($message, $statusCode, $errors);

// Lấy JSON input
$data = $this->getJsonInput();

// Validate dữ liệu
$errors = $this->validate($data, $rules);

// Xác thực JWT
$decoded = $this->authenticateJWT();
```

## Ví dụ sử dụng

### 1. Trong API endpoint cũ - Chuyển sang Controller

**Trước (api/auth/login.php):**
```php
<?php
require_once '../../config/database.php';
require_once '../../config/jwt.php';

$data = json_decode(file_get_contents("php://input"), true);
// ... logic xử lý login
echo json_encode($response);
```

**Sau (sử dụng AuthController):**
```php
<?php
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';

int_headers();

$controller = new AuthController($conn);
$controller->login();
```

### 2. Tạo API endpoint mới

```php
<?php
// api/product/list.php
require_once '../../config/database.php';
require_once '../../controllers/ProductController.php';

int_headers();

$controller = new ProductController($conn);
$controller->index();
```

### 3. Sử dụng Model trực tiếp

```php
<?php
require_once 'config/database.php';
require_once 'models/Product.php';

$productModel = new Product($conn);

// Lấy tất cả sản phẩm
$products = $productModel->getProducts($page, $limit);

// Lấy sản phẩm theo category
$products = $productModel->getByCategory('electronics', 1, 20);

// Tìm kiếm
$products = $productModel->search('laptop', 1, 20);

// Tạo sản phẩm mới
$productId = $productModel->create([
    'name' => 'Laptop Dell',
    'price' => 15000000,
    'seller_id' => 1
]);
```

## Controllers có sẵn

### 1. AuthController
- `login()` - Đăng nhập
- `register()` - Đăng ký
- `me()` - Lấy thông tin user hiện tại
- `logout()` - Đăng xuất

### 2. ProductController
- `index()` - Danh sách sản phẩm
- `show($id)` - Chi tiết sản phẩm
- `create()` - Tạo sản phẩm
- `update($id)` - Cập nhật sản phẩm
- `delete($id)` - Xóa sản phẩm
- `byCategory()` - Lấy theo category
- `flashSale()` - Sản phẩm flash sale
- `search()` - Tìm kiếm

### 3. OrderController
- `create()` - Tạo đơn hàng
- `userOrders()` - Đơn hàng của user
- `detail($orderId)` - Chi tiết đơn hàng
- `updateStatus()` - Cập nhật trạng thái

### 4. VendorController
- `register()` - Đăng ký seller
- `dashboardStats()` - Thống kê dashboard
- `revenueChart()` - Biểu đồ doanh thu
- `topProducts()` - Top sản phẩm
- `categorySales()` - Doanh thu theo category
- `getOrders()` - Đơn hàng của seller
- `productsList()` - Sản phẩm của seller

### 5. ReviewController
- `submit()` - Gửi đánh giá
- `getProductReviews()` - Lấy reviews của sản phẩm
- `getReviews()` - Lấy reviews của user
- `checkReviewEligibility()` - Kiểm tra quyền review

### 6. UserController
- `list()` - Danh sách users
- `show($id)` - Chi tiết user
- `update()` - Cập nhật thông tin
- `changePassword()` - Đổi mật khẩu

## Cách migration từ code cũ sang MVC

### Bước 1: Xác định logic nghiệp vụ
Tách logic từ file API cũ thành các phương thức trong Controller

### Bước 2: Di chuyển query vào Model
Chuyển các SQL query vào Model methods

### Bước 3: Update API endpoint
Thay thế code trong file API bằng việc gọi Controller

### Bước 4: Test
Test lại tất cả endpoints

## Ví dụ Migration

**File cũ: api/product/add.php**
```php
<?php
require_once '../../config/database.php';
$data = json_decode(file_get_contents("php://input"), true);

// Validate
if (!isset($data['name'], $data['price'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu dữ liệu']);
    exit;
}

// Insert
$sql = "INSERT INTO products (name, price) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $data['name'], $data['price']);
$stmt->execute();

echo json_encode(['success' => true, 'id' => $conn->insert_id]);
```

**File mới: api/product/add.php**
```php
<?php
require_once '../../config/database.php';
require_once '../../controllers/ProductController.php';

int_headers();

$controller = new ProductController($conn);
$controller->create();
```

**Logic trong ProductController:**
```php
public function create()
{
    $data = $this->getJsonInput();
    
    $errors = $this->validate($data, [
        'name' => 'required',
        'price' => 'required|numeric'
    ]);
    
    if (!empty($errors)) {
        $this->error('Dữ liệu không hợp lệ', 400, $errors);
    }
    
    $productId = $this->productModel->create($data);
    
    if (!$productId) {
        $this->error('Tạo sản phẩm thất bại', 500);
    }
    
    $this->success(['id' => $productId], 'Tạo sản phẩm thành công', 201);
}
```

## Lợi ích của MVC

1. **Code dễ maintain**: Logic tập trung, dễ tìm và sửa
2. **Tái sử dụng**: Models và Controllers có thể dùng lại
3. **Testing dễ dàng**: Có thể test từng layer riêng
4. **Mở rộng linh hoạt**: Thêm features mới không ảnh hưởng code cũ
5. **Team work tốt hơn**: Phân chia công việc rõ ràng

## Router tập trung (Tùy chọn)

Thay vì dùng nhiều file API riêng lẻ, có thể dùng 1 file router:

```php
// api_router.php
switch ($route) {
    case '/api/auth/login':
        $controller = new AuthController($conn);
        $controller->login();
        break;
    
    case '/api/products':
        $controller = new ProductController($conn);
        $controller->index();
        break;
    // ...
}
```

Xem chi tiết trong file `api_router_example.php`

## Validation Rules

Các rules có sẵn trong `BaseController->validate()`:
- `required` - Bắt buộc
- `email` - Email hợp lệ
- `min:n` - Độ dài tối thiểu
- `max:n` - Độ dài tối đa
- `numeric` - Phải là số

**Ví dụ:**
```php
$errors = $this->validate($data, [
    'email' => 'required|email',
    'password' => 'required|min:6',
    'age' => 'numeric'
]);
```

## Best Practices

1. **Luôn validate input** trong Controller
2. **Sử dụng transactions** cho operations phức tạp
3. **Xử lý errors properly** - catch exceptions và trả về response phù hợp
4. **Không hardcode** - Dùng constants và config
5. **Comment code** - Giải thích logic phức tạp
6. **Follow naming conventions** - Đặt tên rõ ràng, có ý nghĩa

## Hỗ trợ

Nếu có vấn đề hoặc cần thêm tính năng, hãy:
1. Kiểm tra các Model/Controller có sẵn
2. Tham khảo `api_router_example.php`
3. Mở rộng BaseModel/BaseController nếu cần

---

**Tài liệu này được tạo để hướng dẫn sử dụng cấu trúc MVC cho VibeMarket Backend**
