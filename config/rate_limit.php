<?php
function checkRateLimit($limit = 5, $window = 10)
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $storagePath = $_SERVER['DOCUMENT_ROOT'] . '/storage/tmp';
    if (!file_exists($storagePath)) {
        mkdir($storagePath, 0777, true);
    }
    $file = $storagePath . "/rate_limit_" . md5($ip);

    $now = time();

    $data = ["count" => 0, "expires" => $now + $window];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);

        if ($data["expires"] < $now) {
            $data = ["count" => 0, "expires" => $now + $window];
        }
    }

    $data["count"]++;
    file_put_contents($file, json_encode($data));

    if ($data["count"] > $limit) {
        $retry = $data["expires"] - $now;
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "message" => "Quá nhiều yêu cầu, thử lại sau: " . $retry . " giây",
            "retry_after" => $retry
        ]);
        exit;
    }
}
