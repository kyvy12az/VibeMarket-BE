<?php
// Simple GHN fee proxy
// Expects JSON POST: { to_province, to_ward, shipping_method, weight, subtotal }
// Configure these constants with your GHN credentials
$GHN_TOKEN = getenv('VITE_GHN_TOKEN') ?: 'fae1a080-dfc8-11f0-a3d6-dac90fb956b5';
$GHN_SHOP_ID = getenv('VITE_GHN_SHOPID') ?: '2510802';
$GHN_API_BASE = getenv('VITE_GHN_API_BASE') ?: 'https://dev-online-gateway.ghn.vn/shiip/public-api';

// --- CORS: allow calls from local dev server ---
// Adjust the origin check for production as needed
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // return 200 for preflight
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'OK']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$to_province = $input['to_province'] ?? null;
$to_ward = $input['to_ward'] ?? null;
$weight = intval($input['weight'] ?? 500);
$shipping_method = $input['shipping_method'] ?? 'standard';

// You should map province/ward strings to GHN district/ward IDs on the server side.
// For a quick demo, attempt to use GHN's fee endpoint requiring: service_id, from_district_id, to_district_id, to_ward_code, weight
// You MUST replace FROM_DISTRICT_ID and SERVICE_ID with values from your GHN shop account.
$FROM_DISTRICT_ID = getenv('GHN_FROM_DISTRICT') ?: null; // set this in server env
$SERVICE_ID = getenv('GHN_SERVICE_ID') ?: null; // set this in server env

if (!$FROM_DISTRICT_ID || !$SERVICE_ID) {
    echo json_encode(['success' => false, 'message' => 'Server not configured with FROM_DISTRICT_ID or SERVICE_ID']);
    exit;
}

// For production, implement a proper mapping from $to_province/$to_ward to GHN district IDs.
// Here we expect the frontend to send GHN district id and ward code; if not provided, we fail.
$to_district_id = $input['to_district_id'] ?? null;
$to_ward_code = $input['to_ward_code'] ?? null;

if (!$to_district_id || !$to_ward_code) {
    echo json_encode(['success' => false, 'message' => 'Missing to_district_id or to_ward_code. Implement mapping on server.']);
    exit;
}

$payload = [
    'service_id' => intval($SERVICE_ID),
    'insurance_value' => intval($input['subtotal'] ?? 0),
    'from_district_id' => intval($FROM_DISTRICT_ID),
    'to_district_id' => intval($to_district_id),
    'to_ward_code' => $to_ward_code,
    'height' => 10,
    'length' => 20,
    'weight' => intval($weight),
    'width' => 15,
    'coupon' => null
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $GHN_API_BASE . '/v2/shipping-order/fee');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Token: ' . $GHN_TOKEN,
    'ShopId: ' . $GHN_SHOP_ID
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'message' => "Curl error: $err"]);
    exit;
}

$resp = json_decode($response, true);
if (!$resp) {
    echo json_encode(['success' => false, 'message' => 'Invalid GHN response', 'raw' => $response]);
    exit;
}

if (isset($resp['code']) && $resp['code'] === 200 && isset($resp['data']['total'])) {
    $fee = $resp['data']['total'];
    echo json_encode(['success' => true, 'fee' => $fee, 'raw' => $resp]);
    exit;
}

// Fallback
echo json_encode(['success' => false, 'message' => 'GHN did not return fee', 'raw' => $resp]);
