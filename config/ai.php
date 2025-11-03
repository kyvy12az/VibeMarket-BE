<?php
// Hugging Face Inference API configuration

// Prefer environment variable HF_TOKEN; fallback to provided key
$HF_TOKEN = getenv('HF_TOKEN') ?: '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';

// Default models
$HF_MODELS = [
    'caption' => getenv('HF_MODEL_CAPTION') ?: 'Salesforce/blip-image-captioning-large',
    // fashionclip provides image-text embeddings
    'embedding' => getenv('HF_MODEL_EMBEDDING') ?: 'fashionclip/fashion-clip'
];

// Endpoint base
$HF_API_BASE = 'https://api-inference.huggingface.co/models/';

function hf_headers() {
    global $HF_TOKEN;
    return [
        'Content-Type: application/octet-stream',
        'Authorization: Bearer ' . $HF_TOKEN,
        'x-wait-for-model: true'
    ];
}

function hf_is_configured() {
    global $HF_TOKEN;
    return !empty($HF_TOKEN);
}

// Basic helper to POST bytes to HF Inference API
function hf_post_bytes($model, $binary) {
    global $HF_API_BASE;
    $url = $HF_API_BASE . rawurlencode($model);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, hf_headers());
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $binary);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    return [$httpCode, $response, $curlErr];
}

// More robust caption generation with multi-model fallbacks and retries
function hf_generate_caption($binary, $preferredModel = null) {
    global $HF_MODELS;
    $candidates = array_values(array_unique(array_filter([
        $preferredModel,
        $HF_MODELS['caption'] ?? null,
        'Salesforce/blip-image-captioning-base',
        'nlpconnect/vit-gpt2-image-captioning',
    ])));

    foreach ($candidates as $model) {
        for ($i = 0; $i < 2; $i++) { // retry each model up to 2 times
            [$code, $resp] = hf_post_bytes($model, $binary);
            if ($code >= 200 && $code < 300) {
                $json = json_decode($resp, true);
                if (is_array($json) && isset($json[0]['generated_text'])) {
                    $txt = trim($json[0]['generated_text']);
                } elseif (isset($json['generated_text'])) {
                    $txt = trim($json['generated_text']);
                } else {
                    $txt = '';
                }
                if ($txt !== '') {
                    return [$txt, $model];
                }
            }
            // short backoff between retries to let model warm
            usleep(200000);
        }
    }
    return ['', end($candidates) ?: ''];
}

// Extract up to N dominant colors as hex using a simple quantization approach
function extract_dominant_colors($imagePath, $maxColors = 4) {
    if (!extension_loaded('gd')) {
        return [];
    }
    $img = @imagecreatefromstring(file_get_contents($imagePath));
    if (!$img) return [];

    // Downscale to reduce color space
    $width = imagesx($img);
    $height = imagesy($img);
    $targetW = 50; $targetH = max(1, intval($height * (50 / max(1, $width))));
    $thumb = imagecreatetruecolor($targetW, $targetH);
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $targetW, $targetH, $width, $height);

    $freq = [];
    for ($y = 0; $y < $targetH; $y++) {
        for ($x = 0; $x < $targetW; $x++) {
            $rgb = imagecolorat($thumb, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            // Quantize to reduce unique keys
            $rq = intval($r / 24) * 24;
            $gq = intval($g / 24) * 24;
            $bq = intval($b / 24) * 24;
            $key = sprintf('#%02X%02X%02X', $rq, $gq, $bq);
            $freq[$key] = ($freq[$key] ?? 0) + 1;
        }
    }
    imagedestroy($thumb);
    imagedestroy($img);

    arsort($freq);
    return array_slice(array_keys($freq), 0, $maxColors);
}

// Compute similarity between caption/labels and style vocabulary using simple heuristics
function infer_style_from_caption($caption) {
    $vocab = [
        'Minimalist' => ['minimal', 'clean', 'simple', 'neutral', 'basic'],
        'Modern' => ['modern', 'sleek', 'contemporary'],
        'Vintage' => ['vintage', 'retro', 'classic', 'old-fashioned'],
        'Streetwear' => ['street', 'urban', 'hoodie', 'sneakers', 'baggy'],
        'Formal' => ['suit', 'blazer', 'trousers', 'tie', 'dress shirt'],
        'Sporty' => ['sport', 'athletic', 'jersey', 'tracksuit', 'sneakers'],
        'Bohemian' => ['boho', 'floral', 'flowy', 'bohemian'],
        'Chic' => ['elegant', 'chic', 'stylish', 'fashionable']
    ];
    $captionL = mb_strtolower($caption);
    if (trim($captionL) === '') {
        return ['Uncertain', 40];
    }
    $best = ['style' => 'Uncertain', 'score' => 0];
    foreach ($vocab as $style => $keywords) {
        $score = 0;
        foreach ($keywords as $kw) {
            if (mb_strpos($captionL, $kw) !== false) $score += 1;
        }
        if ($score > $best['score']) {
            $best = ['style' => $style, 'score' => $score];
        }
    }
    $confidence = min(90, 50 + $best['score'] * 12);
    return [$best['style'], $confidence];
}

function suggest_products_by_style($style) {
    $map = [
        'Minimalist' => ['blazer', 'plain t-shirt', 'trousers', 'loafers'],
        'Modern' => ['bomber jacket', 'slim jeans', 'sneakers', 'watch'],
        'Vintage' => ['midi dress', 'leather bag', 'oxfords', 'beret'],
        'Streetwear' => ['hoodie', 'cargo pants', 'high-top sneakers', 'cap'],
        'Formal' => ['blazer', 'dress pants', 'oxford shoes', 'tie'],
        'Sporty' => ['track jacket', 'running shorts', 'trainers', 'cap'],
        'Bohemian' => ['floral dress', 'sandals', 'woven bag', 'bracelets'],
        'Chic' => ['silk blouse', 'pencil skirt', 'heels', 'earrings']
    ];
    if (isset($map[$style])) return $map[$style];
    return [];
}


