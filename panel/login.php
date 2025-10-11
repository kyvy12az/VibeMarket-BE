<?php
session_start();
require_once '../config/database.php';
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/dashboard.php');
    exit;
}

$msg = $_GET['msg'] ?? '';
$error_message = '';

switch ($msg) {
    case 'no_code':
        $error_message = 'Lỗi xác thực GitHub: Thiếu mã xác thực';
        break;
    case 'no_access_token':
        $error_message = 'Lỗi xác thực GitHub: Không thể lấy access token';
        break;
    case 'invalid_user':
        $error_message = 'Lỗi xác thực GitHub: Thông tin người dùng không hợp lệ';
        break;
    case 'not_admin':
        $error_message = 'Bạn không có quyền truy cập admin panel';
        break;
    case 'logged_out':
        $error_message = 'Bạn đã đăng xuất thành công';
        break;
}
/** test local **/
// if ($isDev) {
//     $_SESSION['user_id'] = 1;
//     $_SESSION['username'] = 'kyvy12az';
//     $_SESSION['email'] = 'kyvy12az@example.com';
//     $_SESSION['name'] = 'Đấng tối cao';
//     $_SESSION['avatar'] = 'https://avatars.githubusercontent.com/u/1?v=4';
//     $_SESSION['github_id'] = 132124729;
//     $_SESSION['user_role'] = 'admin';
// }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Login | VibeMarket Panel</title>

    <link href="css/app.css?v=<?= time(); ?>" rel="stylesheet" />
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
            height: 100vh;
        }

        a {
            text-decoration: none !important;
        }

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

        p {
            text-align: center;
            color: #9ca3af;
            margin-bottom: 2rem;
        }

        .github-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-weight: 500;
            padding: 0.85rem 1.25rem;
            border-radius: 12px;
            background: #24292e;
            color: #fff;
            font-size: 1rem;
            transition: all 0.25s ease;
        }

        .github-btn:hover {
            background: #2f363d;
        }

        .alert {
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #fca5a5;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert i {
            font-size: 1.2rem;
        }

        .github-btn.loading {
            opacity: 0.8;
            cursor: not-allowed;
            background: #2f363d;
        }

        .spinner {
            animation: spin 1s linear infinite;
            width: 20px;
            height: 20px;
            margin-right: 8px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <main>
        <div class="card">
            <h1>VIBEMARKET PANEL</h1>
            <p>Đăng nhập để quản lý hệ thống</p>

            <?php if ($error_message): ?>
                <div class="alert">
                    <i class="bx bx-error-circle"></i>
                    <?= htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <a href="<?= $Github_LoginURL; ?>"
                id="github-btn"
                class="github-btn">
                <i class="bx bxl-github" style="font-size: 1.4rem"></i>
                <span class="btn-text">Đăng nhập với GitHub</span>
            </a>

            <script>
                const btn = document.getElementById("github-btn");
                const loginURL = "<?= $Github_LoginURL; ?>";

                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    btn.classList.add("loading");
                    btn.setAttribute("aria-disabled", "true");
                    btn.style.pointerEvents = "none";
                    btn.innerHTML = `
                    <svg class="spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" 
                        d="M4 12a8 8 0 018-8V0C5.373 
                            0 0 5.373 0 12h4zm2 5.291A7.962 
                            7.962 0 014 12H0c0 3.042 1.135 
                            5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span>Đang đăng nhập...</span>
                    `;
                    setTimeout(() => {
                        window.location.href = loginURL;
                    }, 300);
                });
            </script>

        </div>
    </main>
</body>

</html>