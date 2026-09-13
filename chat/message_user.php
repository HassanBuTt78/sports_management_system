<?php
/**
 * ============================================================
 * chat/message_user.php
 * ------------------------------------------------------------
 * Shared "Message this person" entry point — used by performance
 * pages' "Message Coach"/"Message Player" buttons (§21: reuse the
 * existing chat system, never build a second one). Validates via
 * the same isAllowedChatContact() rules as the rest of chat, then
 * finds/creates the private conversation and redirects into it.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$targetRole = cleanInput($_GET['role'] ?? '');
$targetId = (int) ($_GET['id'] ?? 0);

if (!$conn || !in_array($targetRole, ['admin', 'coach', 'player'], true) || $targetId <= 0) {
    redirectTo(BASE_URL . '/chat/index.php');
}
if (!isAllowedChatContact($conn, $myRole, $myId, $targetRole, $targetId)) {
    redirectTo(BASE_URL . '/chat/index.php');
}

$conversationId = getOrCreatePrivateConversation($conn, $myRole, $myId, $targetRole, $targetId);
redirectTo(BASE_URL . '/chat/index.php?id=' . $conversationId);
