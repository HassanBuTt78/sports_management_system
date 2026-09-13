<?php
/**
 * ============================================================
 * chat/download_attachment.php
 * ------------------------------------------------------------
 * The ONLY way chat attachments are ever served. uploads/chat/
 * is never linked to directly anywhere in this module — every
 * <img>/<video>/download link points here instead, so access
 * control is enforced on every single fetch, not just the first
 * page load.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

if (!isLoggedIn() || !$conn) {
    http_response_code(401);
    exit('Not authorized.');
}

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$messageId = (int) ($_GET['message_id'] ?? 0);

$stmt = $conn->prepare('SELECT conversation_id, attachment, attachment_type, is_deleted FROM messages WHERE message_id = ? LIMIT 1');
$stmt->bind_param('i', $messageId);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$msg || !$msg['attachment'] || $msg['is_deleted']) {
    http_response_code(404);
    exit('Attachment not found.');
}
if (!isConversationMember($conn, (int) $msg['conversation_id'], $myRole, $myId)) {
    http_response_code(403);
    exit('You are not authorized to access this file.');
}

$fullPath = __DIR__ . '/../uploads/' . $msg['attachment'];
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Attachment not found.');
}

$mimeByExt = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    'pdf' => 'application/pdf', 'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = $mimeByExt[$ext] ?? 'application/octet-stream';
$inline = in_array($msg['attachment_type'], ['image', 'video'], true);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
