<?php
/**
 * ============================================================
 * chat/event_chat.php
 * ------------------------------------------------------------
 * Thin entry point: finds/creates the event's discussion
 * conversation (syncing approved-participant membership as it
 * goes — see getOrCreateEventConversation()) and redirects into
 * the normal chat window. Access is still gated the usual way:
 * if this user isn't a member after provisioning, chat/index.php
 * simply won't show them the conversation.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');

$eventId = (int) ($_GET['event_id'] ?? 0);
if (!$conn || $eventId <= 0) {
    redirectTo(BASE_URL . '/chat/index.php');
}

$conversationId = getOrCreateEventConversation($conn, $eventId);
redirectTo(BASE_URL . '/chat/index.php?id=' . $conversationId);
