<?php
/**
 * ============================================================
 * chat/mark_read.php
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
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

if ($conversationId <= 0 || !isConversationMember($conn, $conversationId, $myRole, $myId)) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to access this conversation.']);
    exit;
}

$stmt = $conn->prepare('UPDATE conversation_members SET last_read_at = NOW() WHERE conversation_id = ? AND user_role = ? AND user_id = ?');
$stmt->bind_param('isi', $conversationId, $myRole, $myId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE messages SET seen_at = NOW(), seen_status = 'seen' WHERE conversation_id = ? AND NOT (sender_role = ? AND sender_id = ?) AND seen_at IS NULL");
$stmt->bind_param('isi', $conversationId, $myRole, $myId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
