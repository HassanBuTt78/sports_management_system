<?php
/**
 * ============================================================
 * chat/match_chat.php
 * ------------------------------------------------------------
 * Same pattern as event_chat.php, for matches.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');

$matchId = (int) ($_GET['match_id'] ?? 0);
if (!$conn || $matchId <= 0) {
    redirectTo(BASE_URL . '/chat/index.php');
}

$conversationId = getOrCreateMatchConversation($conn, $matchId);
redirectTo(BASE_URL . '/chat/index.php?id=' . $conversationId);
