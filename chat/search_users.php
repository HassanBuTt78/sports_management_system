<?php
/**
 * ============================================================
 * chat/search_users.php
 * ------------------------------------------------------------
 * Search "Conversations, Users, Messages" per the brief — the
 * user list here is always filtered through getChatContacts(),
 * so search can never surface someone the user isn't allowed
 * to message in the first place.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !$conn) {
    echo json_encode(['success' => false, 'results' => []]);
    exit;
}

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$query = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
$scope = cleanInput($_GET['scope'] ?? 'contacts'); // 'contacts' | 'all' (conversations + messages)

$contacts = getChatContacts($conn, $myRole, $myId);
if ($query !== '') {
    $contacts = array_values(array_filter($contacts, function ($c) use ($query) {
        return str_contains(mb_strtolower($c['name']), $query);
    }));
}
foreach ($contacts as &$c) {
    $c['image_url'] = $c['image'] ? UPLOADS_URL . '/' . $c['image'] : ASSETS_URL . '/images/default-avatar.svg';
}
unset($c);

$results = ['contacts' => $contacts, 'conversations' => [], 'messages' => []];

if ($scope === 'all' && $query !== '') {
    // Conversations by display title (groups) — private conversations are matched via contact name above.
    $stmt = $conn->prepare(
        "SELECT c.conversation_id, c.title FROM conversations c
         JOIN conversation_members cm ON cm.conversation_id = c.conversation_id AND cm.user_role = ? AND cm.user_id = ?
         WHERE c.conversation_type = 'group' AND LOWER(c.title) LIKE CONCAT('%', ?, '%')"
    );
    $stmt->bind_param('sis', $myRole, $myId, $query);
    $stmt->execute();
    $results['conversations'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Message text search, scoped to conversations this user belongs to.
    $stmt = $conn->prepare(
        "SELECT m.message_id, m.conversation_id, m.message, m.sent_at FROM messages m
         JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_role = ? AND cm.user_id = ?
         WHERE m.is_deleted = 0 AND m.message IS NOT NULL AND LOWER(m.message) LIKE CONCAT('%', ?, '%')
         ORDER BY m.sent_at DESC LIMIT 20"
    );
    $stmt->bind_param('sis', $myRole, $myId, $query);
    $stmt->execute();
    $results['messages'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode(['success' => true, 'results' => $results]);
