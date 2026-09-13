<?php
/**
 * ============================================================
 * chat/send_message.php
 * ------------------------------------------------------------
 * Sends a message into an existing conversation. Membership is
 * re-checked here (not just trusted from the page) — posting to
 * a conversation_id the sender doesn't belong to is rejected
 * regardless of what the client sends.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database not connected.']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh and try again.']);
    exit;
}

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$text = trim((string) ($_POST['message'] ?? ''));
$replyTo = ($_POST['reply_to_message_id'] ?? '') !== '' ? (int) $_POST['reply_to_message_id'] : null;

if ($conversationId <= 0 || !isConversationMember($conn, $conversationId, $myRole, $myId)) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to access this conversation.']);
    exit;
}
if (isSenderRateLimited($conn, $myRole, $myId)) {
    echo json_encode(['success' => false, 'message' => "You're sending messages too quickly. Please slow down."]);
    exit;
}

$attachmentPath = null;
$attachmentType = 'none';
$messageType = 'text';

if (!empty($_FILES['attachment']['name'])) {
    $allowedByType = [
        'image' => ['jpg', 'jpeg', 'png', 'webp'],
        'video' => ['mp4', 'webm', 'mov'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
    ];
    $maxByType = ['image' => 10 * 1024 * 1024, 'video' => 50 * 1024 * 1024, 'document' => 20 * 1024 * 1024];
    $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));

    // Explicit blocklist, even though the whitelist above already excludes these — defense in depth.
    $blocked = ['php', 'php5', 'phtml', 'exe', 'bat', 'sh', 'js'];
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

    $result = storeUploadedFile($_FILES['attachment'], 'chat/' . $kind . 's');
    if (!$result['success'] || !$result['path']) {
        echo json_encode(['success' => false, 'message' => 'File could not be saved.']);
        exit;
    }
    $attachmentPath = $result['path'];
    $attachmentType = $kind;
    $messageType = $kind;
}

if ($text === '' && !$attachmentPath) {
    echo json_encode(['success' => false, 'message' => 'Message could not be sent.']);
    exit;
}

// receiver_role/receiver_id stay populated for private chats only (kept for backward
// compatibility with the Module 2 messages table's original design); group/event/match
// messages rely purely on conversation_id + conversation_members for delivery.
$receiverRole = null;
$receiverId = null;
$stmt = $conn->prepare("SELECT conversation_type FROM conversations WHERE conversation_id = ? LIMIT 1");
$stmt->bind_param('i', $conversationId);
$stmt->execute();
$convType = $stmt->get_result()->fetch_assoc()['conversation_type'] ?? 'private';
$stmt->close();

if ($convType === 'private') {
    $stmt = $conn->prepare('SELECT user_role, user_id FROM conversation_members WHERE conversation_id = ? AND NOT (user_role = ? AND user_id = ?) LIMIT 1');
    $stmt->bind_param('isi', $conversationId, $myRole, $myId);
    $stmt->execute();
    $other = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($other) { $receiverRole = $other['user_role']; $receiverId = (int) $other['user_id']; }
}

$textVal = $text !== '' ? $text : null;

$stmt = $conn->prepare(
    'INSERT INTO messages (conversation_id, sender_role, sender_id, receiver_role, receiver_id, message, message_type, reply_to_message_id, attachment, attachment_type)
     VALUES (?,?,?,?,?,?,?,?,?,?)'
);
$stmt->bind_param(
    'isisississ',
    $conversationId, $myRole, $myId, $receiverRole, $receiverId, $textVal, $messageType, $replyTo, $attachmentPath, $attachmentType
);

if ($stmt->execute()) {
    $newMessageId = $stmt->insert_id;
    $stmt->close();

    $conn->query("UPDATE conversations SET updated_at = NOW() WHERE conversation_id = {$conversationId}");

    // Notify every other member (broadcast-safe: fetch and loop, conversations are typically small).
    $stmt2 = $conn->prepare('SELECT user_role, user_id FROM conversation_members WHERE conversation_id = ? AND NOT (user_role = ? AND user_id = ?)');
    $stmt2->bind_param('isi', $conversationId, $myRole, $myId);
    $stmt2->execute();
    $recipients = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    $senderName = resolveUserName($conn, $myRole, $myId);
    $notifTitle = $convType === 'group' ? 'New Group Message' : 'New Message';
    foreach ($recipients as $r) {
        notifyUser($conn, $r['user_role'], (int) $r['user_id'], $notifTitle, "{$senderName} sent you a message.");
    }

    echo json_encode(['success' => true, 'message_id' => $newMessageId]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Message could not be sent.']);
}
