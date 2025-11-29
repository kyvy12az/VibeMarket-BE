# 🚀 Quick Start Guide - VibeMarket MVC

## 📥 Cài đặt

### Bước 1: Cấu hình Database
```sql
-- Import database schema
mysql -u root -p vibemarket_db < database.sql
```

### Bước 2: Cấu hình Environment
```bash
# Copy .env.example thành .env
cp .env.example .env

# Cập nhật thông tin database trong .env
DB_HOST=localhost
DB_NAME=vibemarket_db
DB_USER=root
DB_PASS=
```

### Bước 3: Install Dependencies
```bash
composer install
```

### Bước 4: Cấu hình Apache/XAMPP
Đảm bảo mod_rewrite được enable:
```apache
# httpd.conf
LoadModule rewrite_module modules/mod_rewrite.so

# Cho phép .htaccess override
<Directory "C:/xampp/htdocs">
    AllowOverride All
</Directory>
```

Restart Apache sau khi cấu hình.

---

## 🧪 Testing API

### Test với cURL

#### 1. Đăng ký tài khoản
```bash
curl -X POST http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Nguyen Van A",
    "email": "test@example.com",
    "password": "123456",
    "phone": "0901234567"
  }'
```

#### 2. Đăng nhập
```bash
curl -X POST http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "123456"
  }'
```

Response:
```json
{
  "success": true,
  "message": "Đăng nhập thành công",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": 1,
      "name": "Nguyen Van A",
      "email": "test@example.com",
      "role": "user"
    }
  }
}
```

#### 3. Lấy danh sách sản phẩm
```bash
curl -X GET "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE/api/product/list?page=1&limit=10"
```

#### 4. Lấy chi tiết sản phẩm
```bash
curl -X GET "http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE/api/product/detail?id=1"
```

#### 5. Tạo đơn hàng (cần authentication)
```bash
curl -X POST http://localhost/VIBE_MARKET_BACKEND/VibeMarket-BE/api/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "items": [
      {
        "product_id": 1,
        "quantity": 2,
        "price": 500000
      }
    ],
    "total_amount": 1000000,
    "shipping_address": "123 Main St, Hanoi"
  }'
```

---

## 📝 Ví dụ thực tế

### Ví dụ 1: Tạo Product Controller Method mới

```php
// controllers/ProductController.php

/**
 * Tìm kiếm sản phẩm theo từ khóa
 */
public function search()
{
    $keyword = $_GET['keyword'] ?? '';
    
    if (empty($keyword)) {
        $this->error('Từ khóa tìm kiếm không được để trống', 400);
    }
    
    $productModel = new Product($this->conn);
    $products = $productModel->search($keyword);
    
    $this->success([
        'keyword' => $keyword,
        'results' => $products,
        'count' => count($products)
    ], 'Tìm kiếm thành công');
}
```

Thêm route:
```php
// routes/web.php
$router->get('/api/product/search', 'ProductController@search', 'product.search');
```

### Ví dụ 2: Thêm method vào Model

```php
// models/Product.php

/**
 * Tìm kiếm sản phẩm theo keyword
 */
public function search($keyword)
{
    $keyword = $this->conn->real_escape_string($keyword);
    
    $sql = "
        SELECT p.*, v.name as vendor_name
        FROM {$this->table} p
        LEFT JOIN vendors v ON p.vendor_id = v.id
        WHERE p.name LIKE '%{$keyword}%'
           OR p.description LIKE '%{$keyword}%'
        ORDER BY p.created_at DESC
        LIMIT 50
    ";
    
    $result = $this->conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}
```

### Ví dụ 3: Protected Route (Authentication Required)

```php
// controllers/OrderController.php

public function userOrders()
{
    // Authenticate user
    $user = $this->authenticateJWT();
    
    if (!$user) {
        JsonView::unauthorized('Vui lòng đăng nhập');
    }
    
    // Get user's orders
    $orderModel = new Order($this->conn);
    $orders = $orderModel->getUserOrders($user['id']);
    
    $this->success($orders, 'Lấy danh sách đơn hàng thành công');
}
```

### Ví dụ 4: Validation với Custom Rules

```php
public function create()
{
    $data = $this->getJsonInput();
    
    // Validate
    $errors = $this->validate($data, [
        'name' => 'required|min:3|max:100',
        'price' => 'required|numeric',
        'stock' => 'required|numeric',
        'category_id' => 'required',
        'vendor_id' => 'required'
    ]);
    
    if (!empty($errors)) {
        JsonView::validationError($errors, 'Dữ liệu không hợp lệ');
    }
    
    // Custom validation
    if ($data['price'] < 0) {
        JsonView::validationError([
            'price' => 'Giá sản phẩm phải lớn hơn 0'
        ]);
    }
    
    // Create product
    $productModel = new Product($this->conn);
    $product = $productModel->create($data);
    
    JsonView::created($product, 'Tạo sản phẩm thành công');
}
```

---

## 🎯 Available Routes

### Authentication Routes
- `POST /api/auth/login` - Đăng nhập
- `POST /api/auth/register` - Đăng ký
- `GET /api/auth/me` - Lấy thông tin user hiện tại
- `POST /api/auth/logout` - Đăng xuất

### Product Routes
- `GET /api/product/list` - Danh sách sản phẩm
- `GET /api/product/detail?id={id}` - Chi tiết sản phẩm
- `POST /api/product/add` - Tạo sản phẩm mới (Auth required)
- `PUT /api/product/update/{id}` - Cập nhật sản phẩm
- `DELETE /api/product/delete/{id}` - Xóa sản phẩm
- `GET /api/product/search?keyword={keyword}` - Tìm kiếm
- `GET /api/product/by_category?category_id={id}` - Theo danh mục
- `GET /api/product/flash_sale` - Sản phẩm flash sale

### Order Routes
- `POST /api/order/create` - Tạo đơn hàng (Auth required)
- `GET /api/order/user_orders` - Đơn hàng của user (Auth required)
- `GET /api/order/order_detail?id={id}` - Chi tiết đơn hàng
- `PUT /api/order/update_status` - Cập nhật trạng thái

### Vendor Routes
- `POST /api/vendor/register` - Đăng ký làm vendor
- `GET /api/vendor/dashboard_stats` - Thống kê dashboard
- `GET /api/vendor/products_list` - Danh sách sản phẩm vendor
- `GET /api/vendor/get_orders` - Đơn hàng của vendor
- `GET /api/vendor/analytics_dashboard` - Analytics

### Review Routes
- `POST /api/review/submit_review` - Gửi đánh giá
- `GET /api/review/get_product_reviews?product_id={id}` - Đánh giá sản phẩm
- `GET /api/review/check_review_eligibility` - Kiểm tra quyền review

### User Routes
- `GET /api/user/list` - Danh sách users (Admin)
- `GET /api/user/profile` - Thông tin profile (Auth required)
- `PUT /api/user/update` - Cập nhật profile

---

## 🔑 Authentication

Tất cả protected routes yêu cầu JWT token trong header:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Lấy token:**
1. Đăng nhập qua `/api/auth/login`
2. Lấy `token` từ response
3. Gửi token trong header của các requests tiếp theo

**Example:**
```javascript
// JavaScript/Axios
axios.get('/api/order/user_orders', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

---

## 📊 Response Formats

### Success (200)
```json
{
  "success": true,
  "message": "Success message",
  "data": { ... }
}
```

### Created (201)
```json
{
  "success": true,
  "message": "Resource created successfully",
  "data": { ... }
}
```

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "error": {
    "type": "validation",
    "fields": {
      "email": "Email is required",
      "password": "Password must be at least 6 characters"
    }
  }
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Unauthorized",
  "error": {
    "type": "authentication",
    "reason": "Token is missing or invalid"
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Resource not found",
  "error": {
    "type": "not_found"
  }
}
```

---

## 🛠️ Debugging

### Enable error display (Development only)
```php
// index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Check logs
```bash
# Apache error log
tail -f C:/xampp/apache/logs/error.log

# PHP error log
tail -f C:/xampp/php/logs/php_error_log
```

### Common issues

**Issue: 404 Not Found trên tất cả routes**
- Kiểm tra mod_rewrite đã enable chưa
- Kiểm tra .htaccess file tồn tại
- Restart Apache

**Issue: "Route not found"**
- Kiểm tra route đã được define trong routes/web.php chưa
- Kiểm tra HTTP method (GET, POST, PUT, DELETE)
- Kiểm tra URL path chính xác

**Issue: "Unauthorized"**
- Kiểm tra JWT token có được gửi trong header không
- Kiểm tra format: `Authorization: Bearer TOKEN`
- Kiểm tra token chưa hết hạn

---

## 💡 Tips

1. **Sử dụng Postman** để test APIs dễ dàng hơn
2. **Enable CORS** nếu frontend chạy trên domain/port khác
3. **Validate input** trước khi xử lý
4. **Handle exceptions** với try-catch
5. **Log errors** để debug
6. **Use transactions** cho operations phức tạp
7. **Cache** data khi cần thiết

---

## 📚 Next Steps

1. ✅ Đọc [MVC_ARCHITECTURE.md](./MVC_ARCHITECTURE.md) để hiểu kiến trúc
2. ✅ Test các API endpoints với Postman
3. ✅ Tạo Controllers/Models mới theo pattern
4. ✅ Implement authentication cho protected routes
5. ✅ Viết tests cho Controllers và Models
6. ✅ Optimize database queries
7. ✅ Add caching layer

---

## 🎉 Happy Coding!

Nếu có vấn đề, check documentation hoặc xem code examples trong `controllers/` và `models/`.
