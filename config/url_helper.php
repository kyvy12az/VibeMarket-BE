<?php
/**
 * Helper functions để xử lý URL động cho cả local và production
 */

if (!function_exists('getBackendBaseUrl')) {
    function getBackendBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        
        // Detect if running on localhost or production
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            // Local development
            return $protocol . '://' . $host . '/VIBE_MARKET_BACKEND/VibeMarket-BE';
        } else {
            // Production
            return $protocol . '://' . $host;
        }
    }
}

if (!function_exists('getFullImageUrl')) {
    function getFullImageUrl($relativePath) {
        if (!$relativePath) {
            return null;
        }
        
        // Check if already a full URL
        if (strpos($relativePath, 'http://') === 0 || strpos($relativePath, 'https://') === 0) {
            return $relativePath;
        }
        
        $baseUrl = getBackendBaseUrl();
        
        // Handle different path formats
        if (strpos($relativePath, 'uploads/') === 0) {
            return $baseUrl . '/' . $relativePath;
        } else {
            return $baseUrl . '/uploads/' . $relativePath;
        }
    }
}

if (!function_exists('getUploadDirectory')) {
    function getUploadDirectory($type = 'general') {
        // Base upload directory - always relative to the config file
        $base_dir = __DIR__ . '/../uploads/';
        
        $directories = [
            'avatar' => $base_dir . 'store_avatars/',
            'cover' => $base_dir . 'store_covers/',
            'product' => $base_dir . 'products/',
            'general' => $base_dir
        ];
        
        $dir = isset($directories[$type]) ? $directories[$type] : $directories['general'];
        
        // Create directory if not exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $dir;
    }
}

if (!function_exists('deleteOldFile')) {
    function deleteOldFile($filename, $type = 'general') {
        if (!$filename) return false;
        
        // Extract just filename if it's a URL or path
        $filename = basename($filename);
        
        $upload_dir = getUploadDirectory($type);
        $filepath = $upload_dir . $filename;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
}
?>