<?php
/**
 * Environment configuration
 * Tùy chỉnh theo môi trường deploy
 */

// Tự động detect environment
function getEnvironment() {
    $host = $_SERVER['HTTP_HOST'];
    
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        return 'development';
    }
    
    return 'production';
}

// Configuration theo environment
$config = [
    'development' => [
        'base_path' => '/VIBE_MARKET_BACKEND/VibeMarket-BE',
        'upload_path' => '/uploads',
        'debug' => true
    ],
    'production' => [
        'base_path' => '/komer.id.vn/public', // Root hoặc custom path trên server
        'upload_path' => '/uploads',
        'debug' => false
    ]
];

$env = getEnvironment();
define('APP_ENV', $env);
define('BASE_PATH', $config[$env]['base_path']);
define('UPLOAD_PATH', $config[$env]['upload_path']);
define('DEBUG_MODE', $config[$env]['debug']);
?>