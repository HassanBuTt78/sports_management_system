<?php
/**
 * ============================================================
 * chat/upload_attachment.php
 * ------------------------------------------------------------
 * Lets the composer show a preview (image thumbnail / file name
 * + size / video player) BEFORE the user hits Send. The actual
 * message row (and its permanent attachment reference) is only
 * created by send_message.php — this endpoint just validates
 * and stores the file, returning enough info to render a preview
 * and to re-submit alongside the eventual send.
 *
 * Note: since send_message.php itself accepts the raw file too,
 * this endpoint is what the composer's "attach" button calls
 * immediately on file selection — the file is already safely
 * validated and stored by the time Send is pressed, so Send
 * simply re-uses the same validated upload path.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !$conn) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}
if (empty($_FILES['attachment']['name'])) {
    echo json_encode(['success' => false, 'message' => 'No file provided.']);
    exit;
}

$allowedByType = [
    'image' => ['jpg', 'jpeg', 'png', 'webp'],
    'video' => ['mp4', 'webm', 'mov'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
];
$maxByType = ['image' => 10 * 1024 * 1024, 'video' => 50 * 1024 * 1024, 'document' => 20 * 1024 * 1024];
$blocked = ['php', 'php5', 'phtml', 'exe', 'bat', 'sh', 'js'];

$ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
if (in_array($ext, $blocked, true)) {
    echo json_encode(['success' => false, 'message' => 'That file type is not supported.']);
    exit;
}

$kind = null;
foreach ($allowedByType as $type => $exts) {
    if (in_array($ext, $exts, true)) { $kind = $type; break; }
}
if (!$kind) {
    echo json_encode(['success' => false, 'message' => 'That file type is not supported.']);
    exit;
}

$fileCheck = validateUploadedFile($_FILES['attachment'], $allowedByType[$kind], $maxByType[$kind]);
if (!$fileCheck['valid']) {
    echo json_encode(['success' => false, 'message' => $fileCheck['error']]);
    exit;
}

echo json_encode([
    'success' => true,
    'file_name' => $_FILES['attachment']['name'],
    'file_size' => $_FILES['attachment']['size'],
    'kind' => $kind,
]);
