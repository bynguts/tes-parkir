<?php
/**
 * api/ocr_proxy.php
 * Secure proxy for OCR.space API to prevent exposing API Key on frontend.
 */
header('Content-Type: application/json');

// Using a placeholder or the common free key. 
// User should replace this with their own key from ocr.space
$ocr_api_key = 'K84564883888957'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['error' => 'No image file uploaded.']);
    exit;
}

$file = $_FILES['file'];

// Validate file type (basic)
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, and WEBP allowed.']);
    exit;
}

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    $post_fields = [
        'apikey'            => $ocr_api_key,
        'language'          => 'eng',
        'isOverlayRequired' => 'false',
        'scale'             => 'true',
        'OCREngine'         => '2', // Engine 2 is often better for numbers/plates
        'file'              => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception("CURL Error: " . $curl_error);
    }

    $result = json_decode($response, true);

    if (isset($result['ParsedResults'][0]['ParsedText'])) {
        $text = $result['ParsedResults'][0]['ParsedText'];
        
        // Basic cleaning for license plates (remove newlines, extra spaces)
        $clean_text = preg_replace('/\s+/', ' ', trim($text));
        
        echo json_encode([
            'success' => true,
            'plate'   => $clean_text,
            'raw'     => $result
        ]);
    } else {
        $msg = $result['ErrorMessage'][0] ?? 'Failed to parse image.';
        echo json_encode(['error' => $msg]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
