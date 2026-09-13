<?php
/**
 * ============================================================
 * chat/load_messages.php
 * ------------------------------------------------------------
 * Polled every ~4s by chat.js while a conversation is open.
 * Also marks delivered_at for anything freshly fetched by the
 * recipient, and returns just the new messages (not the whole
 * thread) to keep polling cheap.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !$conn) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);

if ($conversationId <= 0 || !isConversationMember($conn, $conversationId, $myRole, $myId)) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to access this conversation.', 'messages' => []]);
    exit;
}

touchPresence($conn, $myRole, $myId);

$stmt = $conn->prepare(
    "SELECT m.*, r.message AS reply_message FROM messages m
     LEFT JOIN messages r ON m.reply_to_message_id = r.message_id
     WHERE m.conversation_id = ? AND m.message_id > ? ORDER BY m.sent_at ASC"
);
$stmt->bind_param('ii', $conversationId, $afterId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mark anything from someone else as delivered now that this member has fetched it.
$incomingIds = [];
foreach ($rows as $r) {
    if (!($r['sender_role'] === $myRole && (int) $r['sender_id'] === $myId) && !$r['delivered_at']) {
        $incomingIds[] = (int) $r['message_id'];
    }
}
if (!empty($incomingIds)) {
    $placeholders = implode(',', array_fill(0, count($incomingIds), '?'));
    $types = str_repeat('i', count($incomingIds));
    $stmt = $conn->prepare("UPDATE messages SET delivered_at = NOW() WHERE message_id IN ($placeholders) AND delivered_at IS NULL");
    $stmt->bind_param($types, ...$incomingIds);
    $stmt->execute();
    $stmt->close();
}

$out = array_map(function ($m) use ($myRole, $myId) {
    return [
        'message_id' => (int) $m['message_id'],
        'is_mine' => $m['sender_role'] === $myRole && (int) $m['sender_id'] === $myId,
        'message' => $m['message'],
        'message_type' => $m['message_type'],
        'attachment' => $m['attachment'] ? true : false,
        'attachment_type' => $m['attachment_type'],
        'reply_message' => $m['reply_message'],
        'sent_at' => date('g:i A', strtotime($m['sent_at'])),
        'is_edited' => (bool) $m['is_edited'],
        'is_deleted' => (bool) $m['is_deleted'],
        'download_url' => BASE_URL . '/chat/download_attachment.php?message_id=' . $m['message_id'],
    ];
}, $rows);

echo json_encode(['success' => true, 'messages' => $out]);
