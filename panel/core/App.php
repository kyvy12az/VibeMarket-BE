<?php

class App
{
    protected $controller = 'DashboardController';
    protected $method = 'index';
    protected $params = [];

    public function __construct()
    {
        $url = $this->parseUrl();

        // Check controller
        if (isset($url[0])) {
            $controllerName = ucfirst($url[0]) . 'Controller';
            if (file_exists(__DIR__ . '/../controllers/' . $controllerName . '.php')) {
                $this->controller = $controllerName;
                unset($url[0]);
            }
        }

        require_once __DIR__ . '/../controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        // Check method
        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            } else {
                // If method doesn't exist and we're not on index, show 404
                if ($url[1] !== 'index') {
                    // Method not found, redirect to 404
                    header('HTTP/1.0 404 Not Found');
                    echo "404 - Page Not Found<br>";
                    echo "Controller: " . get_class($this->controller) . "<br>";
                    echo "Method: " . htmlspecialchars($url[1]) . " not found<br>";
                    echo "Available methods: " . implode(', ', get_class_methods($this->controller));
                    exit;
                }
            }
        }

        // Get params
        $this->params = $url ? array_values($url) : [];

        // Call controller method
        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    protected function parseUrl()
    {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }
}
