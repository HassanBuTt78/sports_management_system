<?php
/**
 * ============================================================
 * chat/edit_message.php
 * ------------------------------------------------------------
 * Ownership is enforced in the UPDATE's WHERE clause itself —
 * editing someone else's message_id silently matches zero rows.
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
$newText = trim((string) ($_POST['message'] ?? ''));

if ($messageId <= 0 || $newText === '') {
    echo json_encode(['success' => false, 'message' => 'Message could not be sent.']);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE messages SET message = ?, is_edited = 1
     WHERE message_id = ? AND sender_role = ? AND sender_id = ? AND message_type = 'text' AND is_deleted = 0"
);
$stmt->bind_param('sisi', $newText, $messageId, $myRole, $myId);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode($changed
    ? ['success' => true]
    : ['success' => false, 'message' => 'You are not authorized to edit this message.']);
