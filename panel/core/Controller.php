<?php

class Controller
{
    protected $db;

    public function __construct()
    {
        $this->db = $this->getConnection();
    }

    protected function getConnection()
    {
        global $conn;
        require_once __DIR__ . '/../../config/database.php';
        return $conn;
    }

    public function model($model)
    {
        require_once __DIR__ . '/../models/' . $model . '.php';
        return new $model();
    }

    public function view($view, $data = [])
    {
        extract($data);
        
        // Check if it's an AJAX request
        if ($this->isAjax()) {
            require_once __DIR__ . '/../views/' . $view . '.php';
        } else {
            // Load full layout
            require_once __DIR__ . '/../views/layouts/app.php';
        }
    }

    public function viewWithoutLayout($view, $data = [])
    {
        extract($data);
        require_once __DIR__ . '/../views/' . $view . '.php';
    }

    public function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function redirect($url)
    {
        header('Location: ' . $this->baseUrl($url));
        exit;
    }

    public function baseUrl($path = '')
    {
        $base = '/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/';
        return $base . ltrim($path, '/');
    }

    public function getUploadsBaseUrl()
    {
        // Get the base path dynamically for uploads folder
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // Get the base path from current script
        $script = $_SERVER['SCRIPT_NAME'];
        // Remove /panel/index.php to get base path
        $basePath = str_replace('/panel/index.php', '', $script);
        
        return $protocol . $host . $basePath . '/';
    }

    protected function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function requireAuth()
    {
        Session::start();
        if (!Session::has('user_role') || Session::get('user_role') !== 'admin') {
            $this->redirect('auth/login');
        }
    }

    protected function requireGuest()
    {
        Session::start();
        if (Session::has('user_role') && Session::get('user_role') === 'admin') {
            $this->redirect('dashboard');
        }
    }
}
