<?php
/**
 * ============================================================
 * chat/delete_message.php
 * ------------------------------------------------------------
 * Soft delete (is_deleted = 1, message/attachment cleared from
 * display) rather than a hard DELETE — keeps the thread's flow
 * intact for the other participant(s), same "This message was
 * deleted" convention every mainstream messaging app uses.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !$conn) {
    echo json_encode(['success' => false]);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$messageId = (int) ($_POST['message_id'] ?? 0);

if ($messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare('UPDATE messages SET is_deleted = 1 WHERE message_id = ? AND sender_role = ? AND sender_id = ?');
$stmt->bind_param('isi', $messageId, $myRole, $myId);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode($changed
    ? ['success' => true]
    : ['success' => false, 'message' => 'You are not authorized to delete this message.']);
