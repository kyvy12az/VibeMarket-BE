# User Profile API Documentation

## Endpoints

### 1. GET Profile Info
**URL:** `/api/user/profile.php`  
**Method:** `GET`  
**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Nguyễn Kỳ Vỹ",
    "email": "kyvydev@gmail.com",
    "phone": "0901234567",
    "address": "Cổ Thành, Triệu Phong, Quảng Trị",
    "avatar": "/uploads/avatars/avatar_1_1234567890.jpg",
    "bio": "Yêu thích mua sắm...",
    "joinDate": "March, 2023",
    "points": 1250,
    "level": "Gold Member",
    "stats": {
      "totalOrders": 45,
      "totalSpent": 12500000,
      "reviews": 23,
      "followers": 156,
      "following": 89
    }
  },
  "spendingData": [
    {
      "month": "T1",
      "orders": 3,
      "amount": 1200000
    }
  ],
  "recentActivity": [
    {
      "type": "review",
      "content": "Đánh giá 5 sao cho Kem dưỡng da",
      "time": "2 giờ trước"
    }
  ]
}
```

### 2. Update Profile
**URL:** `/api/user/profile.php`  
**Method:** `PUT`  
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "name": "Nguyễn Kỳ Vỹ",
  "phone": "0901234567",
  "address": "Cổ Thành, Triệu Phong, Quảng Trị",
  "bio": "Yêu thích mua sắm và chia sẻ..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Cập nhật thông tin thành công"
}
```

### 3. Update Avatar
**URL:** `/api/user/update_avatar.php`  
**Method:** `POST`  
**Headers:**
```
Authorization: Bearer {token}
```

**Body:** `multipart/form-data`
- `avatar`: File (max 5MB, jpg/png/gif/webp)

**Response:**
```json
{
  "success": true,
  "message": "Cập nhật avatar thành công",
  "avatar": "/uploads/avatars/avatar_1_1234567890.jpg"
}
```

## Database Setup

### 1. Import SQL Migration
```bash
mysql -u root -p vibemarket_db < migrations/create_user_profile_tables.sql
```

### 2. Tables Created
- `user_points`: Lưu điểm và cấp độ của user
- `user_followers`: Lưu quan hệ theo dõi giữa users
- Thêm cột `bio` vào bảng `users`

### 3. Create Uploads Directory
```bash
mkdir -p uploads/avatars
chmod 755 uploads/avatars
```

## Frontend Integration

### 1. Fetch Profile Data
```typescript
const token = localStorage.getItem('vibeventure_token');
const response = await fetch(`${BACKEND_URL}/api/user/profile.php`, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();
```

### 2. Update Profile
```typescript
const response = await fetch(`${BACKEND_URL}/api/user/profile.php`, {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({ name, phone, bio, address })
});
```

### 3. Upload Avatar
```typescript
const formData = new FormData();
formData.append('avatar', file);

const response = await fetch(`${BACKEND_URL}/api/user/update_avatar.php`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});
```

## Features

✅ **Profile Information**
- Get user profile with stats
- Update profile (name, phone, address, bio)
- Upload/Update avatar

✅ **Statistics**
- Total orders and spending
- Reviews count
- Followers/Following count
- Points and membership level

✅ **Activity Tracking**
- Recent reviews
- Recent orders
- Spending data (6 months)

✅ **Security**
- JWT authentication
- File type validation
- File size limit (5MB)
- SQL injection protection

## Testing

### Test Profile API
```bash
# Get profile
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:80/api/user/profile.php

# Update profile
curl -X PUT \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"New Name","phone":"0123456789"}' \
  http://localhost:80/api/user/profile.php

# Upload avatar
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "avatar=@avatar.jpg" \
  http://localhost:80/api/user/update_avatar.php
```

## Troubleshooting

### Avatar Upload Issues
1. Check directory permissions: `chmod 755 uploads/avatars`
2. Check PHP upload settings in `php.ini`:
   - `upload_max_filesize = 10M`
   - `post_max_size = 10M`
3. Check Apache/Nginx write permissions

### Token Issues
- Make sure token is sent in `Authorization: Bearer {token}` format
- Check token expiration (JWT_SECRET in config/jwt.php)
- Verify token is stored in localStorage as `vibeventure_token`

### Database Issues
- Make sure all tables exist (run migration SQL)
- Check foreign key constraints
- Verify user_id exists in users table
