<?php
/**
 * ============================================================
 * chat/new_conversation.php
 * ------------------------------------------------------------
 * Validates the target against isAllowedChatContact() before
 * creating anything — the RBAC rules are the actual gate, not
 * just what the "New Message" picker happens to display.
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
$targetRole = cleanInput($_POST['target_role'] ?? '');
$targetId = (int) ($_POST['target_id'] ?? 0);

if (!in_array($targetRole, ['admin', 'coach', 'player'], true) || $targetId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (!isAllowedChatContact($conn, $myRole, $myId, $targetRole, $targetId)) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to message this person.']);
    exit;
}

$conversationId = getOrCreatePrivateConversation($conn, $myRole, $myId, $targetRole, $targetId);

echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
