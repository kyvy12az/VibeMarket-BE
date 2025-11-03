<?php
require_once '../../config/database.php';
require_once '../../config/ai.php';

int_headers();

if (!hf_is_configured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Hugging Face token is not configured. Set HF_TOKEN env var.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read input (single or batch)
$tmpPath = null;
$batch = [];
try {
    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $path) {
            if (!empty($path) && file_exists($path)) $batch[] = $path;
        }
    } elseif (!empty($_FILES['image']['tmp_name'])) {
        $tmpPath = $_FILES['image']['tmp_name'];
    } else {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (isset($data['image_base64'])) {
            $b64 = $data['image_base64'];
            if (strpos($b64, 'base64,') !== false) {
                $b64 = explode('base64,', $b64, 2)[1];
            }
            $bytes = base64_decode($b64);
            if ($bytes === false) throw new Exception('Invalid base64');
            $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('aistylist_' . bin2hex(random_bytes(6)) . '.img');
            file_put_contents($tmpPath, $bytes);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input: ' . $e->getMessage()]);
    exit;
}

if (empty($batch) && (!$tmpPath || !file_exists($tmpPath))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No image provided']);
    exit;
}

// Optional model overrides via query params
$captionModel = $_GET['caption_model'] ?? $HF_MODELS['caption'];
$embedModel = $_GET['embed_model'] ?? $HF_MODELS['embedding'];

// Batch handler (returns {success, results: [...]})
if (!empty($batch)) {
    $results = [];
    foreach ($batch as $p) {
        // caption with robust fallback
        [$caption, $usedCaptionModel] = hf_generate_caption(file_get_contents($p), $captionModel);

        $colors = extract_dominant_colors($p, 4);
        [$style, $confidence] = infer_style_from_caption($caption ?: '');
        $suggestions = suggest_products_by_style($style);

        // DB candidates
        $productSuggestions = [];
        try {
            $terms = array_map(function($s){ return mb_strtolower(trim($s)); }, $suggestions);
            $extra = [];
            foreach (preg_split('/\s+/', mb_strtolower($caption)) as $tok) {
                if (mb_strlen($tok) >= 4 && count($extra) < 8) $extra[] = preg_replace('/[^a-z0-9à-ỹ]/u','',$tok);
            }
            $sql = "SELECT * FROM products 
                    WHERE (release_date IS NULL OR release_date <= NOW())
                      AND status = 'active'
                    ORDER BY id DESC
                    LIMIT 300";
            $res = $conn->query($sql);
            $candidates = [];
            while ($row = $res->fetch_assoc()) {
                $nameL = mb_strtolower($row['name'] ?? '');
                $catL = mb_strtolower($row['category'] ?? '');
                $tagsL = mb_strtolower($row['tags'] ?? '');
                $keysL = mb_strtolower($row['keywords'] ?? '');
                $brandL = mb_strtolower($row['brand'] ?? '');
                $matL = mb_strtolower($row['material'] ?? '');
                $colorsL = mb_strtolower($row['colors'] ?? '');
                $score = 0; $matched = null;
                foreach ($terms as $t) {
                    if ($t && (
                        mb_strpos($nameL, $t) !== false ||
                        mb_strpos($catL, $t) !== false ||
                        mb_strpos($tagsL, $t) !== false ||
                        mb_strpos($keysL, $t) !== false ||
                        mb_strpos($brandL, $t) !== false ||
                        mb_strpos($matL, $t) !== false ||
                        mb_strpos($colorsL, $t) !== false
                    )) {
                        $score += 30;
                        if ($matched === null) $matched = $t;
                    }
                }
                foreach ($extra as $t) {
                    if ($t && (
                        mb_strpos($nameL, $t) !== false ||
                        mb_strpos($catL, $t) !== false ||
                        mb_strpos($tagsL, $t) !== false
                    )) $score += 6;
                }
                $score += max(0, (float)($row['rating'] ?? 0)) * 2;
                $score += max(0, (int)($row['sold'] ?? 0)) > 100 ? 5 : 0;
                if ($score <= 0) continue;
                $images = json_decode($row['image'] ?? '[]', true) ?: [];
                $candidates[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'price' => (int)$row['price'],
                    'image' => isset($images[0]) ? $images[0] : null,
                    'matchScore' => max(70, min(97, 55 + $score)),
                    'reason' => $matched ? ('Khớp từ khóa: ' . $matched) : ('Phù hợp phong cách ' . $style),
                    'category' => $row['category'],
                    'rating' => (float)($row['rating'] ?? 0),
                    'shop' => $row['seller_name'] ?? ''
                ];
            }
            usort($candidates, function($a,$b){ return $b['matchScore'] <=> $a['matchScore']; });
            $productSuggestions = array_slice($candidates, 0, 8);
        } catch (Throwable $e) {}

        // embedding
        $embedding = null;
        [$embCode, $embResp, $embErr] = hf_post_bytes($embedModel, file_get_contents($p));
        if ($embCode >= 200 && $embCode < 300) {
            $embJson = json_decode($embResp, true);
            if ($embJson !== null) $embedding = $embJson;
        }

        $results[] = [
            'caption' => $caption,
            'detectedStyle' => $style,
            'confidence' => $confidence,
            'colorPalette' => $colors,
            'mood' => ($style === 'Minimalist' ? 'Professional & Clean' : ($style === 'Streetwear' ? 'Urban & Bold' : ($style === 'Uncertain' ? 'Needs Review' : 'Stylish & Confident'))),
            'suggestions' => $suggestions,
            'models' => [ 'caption' => $usedCaptionModel ?: $captionModel, 'embedding' => $embedModel ],
            'embedding' => $embedding,
            'products' => $productSuggestions
        ];
    }
    // cleanup tmp files created from JSON batch
    if (isset($data) && isset($data['images_base64'])) {
        foreach ($batch as $p) { if (is_file($p)) @unlink($p); }
    }
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

// 1) Caption the image (robust basis for style inference)
[$caption, $usedCaptionModelSingle] = hf_generate_caption(file_get_contents($tmpPath), $captionModel);

// 2) Extract dominant colors (client UI uses this heavily)
$colors = extract_dominant_colors($tmpPath, 4);

// 3) Infer style heuristically from caption
[$style, $confidence] = infer_style_from_caption($caption ?: '');

// 4) Provide product suggestions from style
$suggestions = suggest_products_by_style($style);

// 4b) Retrieve product suggestions from DB by simple keyword scoring (no mocks)
$productSuggestions = [];
try {
    $terms = array_map(function($s){ return mb_strtolower(trim($s)); }, $suggestions);
    // extract extra terms from caption (longer tokens)
    $extra = [];
    foreach (preg_split('/\s+/', mb_strtolower($caption)) as $tok) {
        if (mb_strlen($tok) >= 4 && count($extra) < 8) $extra[] = preg_replace('/[^a-z0-9à-ỹ]/u','',$tok);
    }
    $allTerms = array_values(array_unique(array_filter(array_merge($terms, $extra))));

    $sql = "SELECT * FROM products 
            WHERE (release_date IS NULL OR release_date <= NOW())
              AND status = 'active'
            ORDER BY id DESC
            LIMIT 300";
    $res = $conn->query($sql);
    $candidates = [];
    while ($row = $res->fetch_assoc()) {
        $nameL = mb_strtolower($row['name'] ?? '');
        $catL = mb_strtolower($row['category'] ?? '');
        $tagsL = mb_strtolower($row['tags'] ?? '');
        $keysL = mb_strtolower($row['keywords'] ?? '');
        $brandL = mb_strtolower($row['brand'] ?? '');
        $matL = mb_strtolower($row['material'] ?? '');
        $colorsL = mb_strtolower($row['colors'] ?? '');
        $score = 0; $matched = null;
        foreach ($terms as $t) {
            if ($t && (
                mb_strpos($nameL, $t) !== false ||
                mb_strpos($catL, $t) !== false ||
                mb_strpos($tagsL, $t) !== false ||
                mb_strpos($keysL, $t) !== false ||
                mb_strpos($brandL, $t) !== false ||
                mb_strpos($matL, $t) !== false ||
                mb_strpos($colorsL, $t) !== false
            )) {
                $score += 30; // strong match
                if ($matched === null) $matched = $t;
            }
        }
        foreach ($extra as $t) {
            if ($t && (
                mb_strpos($nameL, $t) !== false ||
                mb_strpos($catL, $t) !== false ||
                mb_strpos($tagsL, $t) !== false
            )) $score += 6;
        }
        $score += max(0, (float)($row['rating'] ?? 0)) * 2; // rating boost
        $score += max(0, (int)($row['sold'] ?? 0)) > 100 ? 5 : 0; // popularity nudge
        if ($score <= 0) continue;
        $images = json_decode($row['image'] ?? '[]', true) ?: [];
        $candidates[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (int)$row['price'],
            'image' => isset($images[0]) ? $images[0] : null,
            'matchScore' => max(70, min(97, 55 + $score)),
            'reason' => $matched ? ('Khớp từ khóa: ' . $matched) : ('Phù hợp phong cách ' . $style),
            'category' => $row['category'],
            'rating' => (float)($row['rating'] ?? 0),
            'shop' => $row['seller_name'] ?? ''
        ];
    }
    usort($candidates, function($a,$b){ return $b['matchScore'] <=> $a['matchScore']; });
    $productSuggestions = array_slice($candidates, 0, 8);
} catch (Throwable $e) {
    // ignore DB suggestion errors; keep analysis without products
}

// 5) Optionally compute embedding (not strictly required for current UI, but returned for future use)
// Some HF models expect bytes for feature-extraction and return vector(s)
$embedding = null;
[$embCode, $embResp, $embErr] = hf_post_bytes($embedModel, file_get_contents($tmpPath));
if ($embCode >= 200 && $embCode < 300) {
    $embJson = json_decode($embResp, true);
    if ($embJson !== null) $embedding = $embJson;
}

// Build response
$result = [
    'success' => true,
    'analysis' => [
        'caption' => $caption,
        'detectedStyle' => $style,
        'confidence' => $confidence,
        'colorPalette' => $colors,
        'mood' => ($style === 'Minimalist' ? 'Professional & Clean' : ($style === 'Streetwear' ? 'Urban & Bold' : ($style === 'Uncertain' ? 'Needs Review' : 'Stylish & Confident'))),
        'suggestions' => $suggestions,
        'models' => [
            'caption' => $usedCaptionModelSingle ?: $captionModel,
            'embedding' => $embedModel
        ],
        'embedding' => $embedding,
        'products' => $productSuggestions
    ]
];

echo json_encode($result);

// Cleanup temp file created from base64
if (isset($data) && isset($data['image_base64']) && file_exists($tmpPath)) {
    @unlink($tmpPath);
}


