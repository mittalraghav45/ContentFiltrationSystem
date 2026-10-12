<?php
declare(strict_types=1);
require_once __DIR__ . '/s3-object.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST to upload an image.']);
}
$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
    respond(400, ['error' => 'Select one JPEG or PNG image per request.']);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    respond(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400,
        ['error' => 'Upload failed. Check the file and PHP upload limits.']);
}
if (!is_uploaded_file($file['tmp_name']) || $file['size'] < 1 || $file['size'] > 5 * 1024 * 1024) {
    respond(413, ['error' => 'Images must be between 1 byte and 5 MiB.']);
}
$type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($type, ['image/jpeg', 'image/png'], true) || @getimagesize($file['tmp_name']) === false) {
    respond(415, ['error' => 'Only valid JPEG and PNG images are supported.']);
}
try {
    $s3 = storageClient();
    $key = 'uploads/' . bin2hex(random_bytes(16)) . ($type === 'image/png' ? '.png' : '.jpg');
    $s3->putObject([
        'Bucket' => getenv('AWS_BUCKET'), 'Key' => $key,
        'SourceFile' => $file['tmp_name'], 'ContentType' => $type,
        'Tagging' => 'moderation=pending',
    ]);
    respond(202, ['key' => $key, 'status' => 'pending', 'message' => 'Uploaded; awaiting moderation.']);
} catch (Throwable $e) {
    error_log('Upload failed: ' . $e->getMessage());
    respond(503, ['error' => 'Storage unavailable. Check server configuration and logs.']);
}
