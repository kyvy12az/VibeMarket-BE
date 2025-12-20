<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Session.php';

class AuthController extends Controller
{
    public function login()
    {
        $this->requireGuest();
        
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
            case 'invalid_credentials':
                $error_message = 'Email hoặc mật khẩu không đúng';
                break;
        }

        // Declare as global BEFORE requiring the config file
        global $Github_ClientID, $Github_RedirectURI, $Github_Scope;
        
        require_once __DIR__ . '/../../config/database.php';
        
        // Build GitHub OAuth URL from config variables
        $Github_LoginURL = "https://github.com/login/oauth/authorize?client_id={$Github_ClientID}&redirect_uri=" . urlencode($Github_RedirectURI) . "&scope={$Github_Scope}";
        
        $this->viewWithoutLayout('auth/login', [
            'error_message' => $error_message,
            'Github_LoginURL' => $Github_LoginURL
        ]);
    }

    public function doLogin()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('auth/login');
        }

        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $this->redirect('auth/login?msg=invalid_credentials');
        }

        // Query user from database
        $sql = "SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1";
        $user = $this->db->prepare($sql);
        $user->bind_param('s', $email);
        $user->execute();
        $result = $user->get_result()->fetch_assoc();

        if (!$result) {
            $this->redirect('auth/login?msg=invalid_credentials');
        }

        // Verify password (assuming password is hashed with password_hash)
        if (isset($result['password']) && password_verify($password, $result['password'])) {
            // Set session
            Session::start();
            Session::set('user_id', $result['id']);
            Session::set('username', $result['username'] ?? $result['name']);
            Session::set('email', $result['email']);
            Session::set('name', $result['name']);
            Session::set('avatar', $result['avatar']);
            Session::set('user_role', $result['role']);

            $this->redirect('dashboard');
        } else {
            $this->redirect('auth/login?msg=invalid_credentials');
        }
    }

    public function logout()
    {
        Session::destroy();
        $this->redirect('auth/login?msg=logged_out');
    }
}
