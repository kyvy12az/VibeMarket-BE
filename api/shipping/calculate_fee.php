<?php
require_once '../../config/database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Debug logging
error_log("Shipping Fee Request - Raw Input: " . $rawInput);
error_log("Shipping Fee Request - Decoded Data: " . print_r($data, true));

$seller_address = isset($data['seller_address']) ? trim($data['seller_address']) : '';
$customer_address = isset($data['customer_address']) ? trim($data['customer_address']) : '';
$shipping_method = isset($data['shipping_method']) ? $data['shipping_method'] : 'standard'; // 'standard' or 'express'

error_log("Seller Address: " . $seller_address);
error_log("Customer Address: " . $customer_address);

if (!$seller_address || !$customer_address) {
    echo json_encode(['success' => false, 'message' => 'Thiếu địa chỉ giao hàng', 'debug' => ['seller' => $seller_address, 'customer' => $customer_address]]);
    exit;
}

// Get Geoapify API Key from config
global $Geoapify_ApiKey;
$geoapify_api_key = $Geoapify_ApiKey;

// Geocode addresses to get coordinates
function geocodeAddress($address, $api_key) {
    $url = "https://api.geoapify.com/v1/geocode/search?text=" . urlencode($address) . "&apiKey=" . $api_key;
    $response = @file_get_contents($url);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (isset($data['features'][0]['geometry']['coordinates'])) {
        $coords = $data['features'][0]['geometry']['coordinates'];
        return ['lon' => $coords[0], 'lat' => $coords[1]];
    }
    return null;
}

error_log("Geocoding seller address...");
$seller_coords = geocodeAddress($seller_address, $geoapify_api_key);

error_log("Geocoding customer address...");
$customer_coords = geocodeAddress($customer_address, $geoapify_api_key);

if (!$seller_coords || !$customer_coords) {
    // Fallback: Tính phí cố định nếu không geocode được
    $base_fee = $shipping_method === 'express' ? 50000 : 25000;
    error_log("Geocoding failed - seller: " . json_encode($seller_coords) . ", customer: " . json_encode($customer_coords));
    
    echo json_encode([
        'success' => true,
        'shipping_fee' => $base_fee,
        'distance_km' => 0,
        'duration' => 'N/A',
        'method' => $shipping_method,
        'note' => 'Không thể tìm thấy địa chỉ, áp dụng phí cố định',
        'debug' => [
            'geocoding_error' => 'Failed to geocode one or both addresses'
        ]
    ]);
    exit;
}

// Call Geoapify Routing API
$route_url = sprintf(
    "https://api.geoapify.com/v1/routing?waypoints=%f,%f|%f,%f&mode=drive&apiKey=%s",
    $seller_coords['lat'], $seller_coords['lon'],
    $customer_coords['lat'], $customer_coords['lon'],
    $geoapify_api_key
);

$route_response = @file_get_contents($route_url);
$route_data = json_decode($route_response, true);

error_log("Geoapify Routing Response: " . print_r($route_data, true));

if (!$route_data || !isset($route_data['features'][0]['properties'])) {
    // Fallback nếu không tính được route
    $base_fee = $shipping_method === 'express' ? 50000 : 25000;
    error_log("Routing failed - Response: " . $route_response);
    
    echo json_encode([
        'success' => true,
        'shipping_fee' => $base_fee,
        'distance_km' => 0,
        'duration' => 'N/A',
        'method' => $shipping_method,
        'note' => 'Không tìm thấy tuyến đường, áp dụng phí cố định'
    ]);
    exit;
}

// Parse distance and duration from Geoapify
$properties = $route_data['features'][0]['properties'];
$distance_meters = $properties['distance'];
$distance_km = round($distance_meters / 1000, 1);
$duration_seconds = $properties['time'];
$duration_minutes = round($duration_seconds / 60);
$duration_text = $duration_minutes . ' phút';

// Calculate shipping fee based on distance
// Logic tính phí:
// - 0-5km: 15,000đ (standard) / 30,000đ (express)
// - 5-10km: 25,000đ (standard) / 45,000đ (express)
// - 10-20km: 35,000đ (standard) / 60,000đ (express)
// - 20-50km: 50,000đ (standard) / 85,000đ (express)
// - >50km: 70,000đ (standard) / 120,000đ (express)

$shipping_fee = 0;

if ($shipping_method === 'express') {
    if ($distance_km <= 5) {
        $shipping_fee = 30000;
    } elseif ($distance_km <= 10) {
        $shipping_fee = 45000;
    } elseif ($distance_km <= 20) {
        $shipping_fee = 60000;
    } elseif ($distance_km <= 50) {
        $shipping_fee = 85000;
    } else {
        $shipping_fee = 120000;
    }
} else {
    // Standard shipping
    if ($distance_km <= 5) {
        $shipping_fee = 15000;
    } elseif ($distance_km <= 10) {
        $shipping_fee = 25000;
    } elseif ($distance_km <= 20) {
        $shipping_fee = 35000;
    } elseif ($distance_km <= 50) {
        $shipping_fee = 50000;
    } else {
        $shipping_fee = 70000;
    }
}

echo json_encode([
    'success' => true,
    'shipping_fee' => $shipping_fee,
    'distance_km' => $distance_km,
    'distance_text' => $distance_km . ' km',
    'duration' => $duration_text,
    'method' => $shipping_method,
    'seller_address' => $seller_address,
    'customer_address' => $customer_address
]);
