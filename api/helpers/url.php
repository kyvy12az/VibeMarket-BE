<?php
function getBaseUrl() {
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] == 443);

    $protocol = $isHttps ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'];

    // 👉 Lấy thư mục gốc của project (tự động)
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']); 
    // ví dụ:
    // local:  /VIBE_MARKET_BACKEND/VibeMarket-BE/api/product
    // host:   /api/product

    // Cắt bỏ /api và phần phía sau
    $basePath = preg_replace('#/api(/.*)?$#', '', $scriptDir);

    return $protocol . '://' . $host . $basePath;
}
