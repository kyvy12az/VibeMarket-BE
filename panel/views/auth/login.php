<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Login | VibeMarket Panel</title>

    <link href="<?php echo $this->baseUrl('css/app.css?v=' . time()); ?>" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #101214, #1b1f23);
            color: #eaeaea;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }

        a { text-decoration: none !important; }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 4rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            max-width: 420px;
            width: 100%;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4ade80, #22d3ee);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 1rem;
        }

        p { text-align: center; color: #9ca3af; margin-bottom: 2rem; }

        .form-group { margin-bottom: 1.5rem; }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #d1d5db;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            color: #eaeaea;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4ade80;
            background: rgba(255, 255, 255, 0.08);
        }

        .btn-primary {
            display: block;
            width: 100%;
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #4ade80, #22d3ee);
            color: #000;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(74, 222, 128, 0.3);
        }

        .divider {
            text-align: center;
            color: #6b7280;
            margin: 1.5rem 0;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider::before { left: 0; }
        .divider::after { right: 0; }

        .github-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-weight: 500;
            padding: 0.85rem 1.25rem;
            width: 100%;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
            color: #eaeaea;
            transition: all 0.3s ease;
        }

        .github-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            color: #fff;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            border-color: rgba(74, 222, 128, 0.3);
            color: #4ade80;
        }

        .github-btn.loading {
            opacity: 0.8;
            cursor: not-allowed;
            pointer-events: none;
        }

        .spinner {
            animation: spin 1s linear infinite;
            width: 20px;
            height: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>Admin Panel</h1>
        <p>Đăng nhập để quản lý hệ thống</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert">
                <?= htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
            <div class="alert alert-success">
                Đăng xuất thành công!
            </div>
        <?php endif; ?>

        <!-- Local Login Form -->
        <form method="POST" action="<?php echo $this->baseUrl('auth/doLogin'); ?>">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-primary">
                <i class="bx bx-log-in"></i> Đăng nhập
            </button>
        </form>

        <div class="divider">hoặc</div>

        <a href="<?= $Github_LoginURL; ?>" id="github-btn" class="github-btn">
            <i class="bx bxl-github" style="font-size: 1.25rem;"></i>
            <span class="btn-text">Đăng nhập với GitHub</span>
        </a>
    </div>

    <script>
        const btn = document.getElementById("github-btn");
        const loginURL = "<?= $Github_LoginURL; ?>";

        // Debug
        console.log("GitHub Login URL:", loginURL);

        // Không dùng event listener, để link hoạt động tự nhiên
        // Hoặc nếu muốn có loading effect:
        btn.addEventListener("click", function(e) {
            console.log("GitHub button clicked!");
            console.log("Redirecting to:", loginURL);
            
            // Không preventDefault, để link hoạt động bình thường
            // Chỉ thêm loading effect
            btn.classList.add("loading");
            btn.innerHTML = `
            <svg class="spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Đang đăng nhập...</span>
            `;
            
            // Không dùng preventDefault - để browser tự redirect
        });
        
        // Additional debug
        console.log("=== ADDITIONAL DEBUG ===");
        console.log("Button href:", btn.href);
        console.log("Button click will redirect to:", btn.href || "NO HREF");
    </script>
</body>

</html>
