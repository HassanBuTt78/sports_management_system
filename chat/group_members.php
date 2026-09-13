<?php
/**
 * ============================================================
 * chat/group_members.php
 * ------------------------------------------------------------
 * AJAX add/remove, gated on the caller's own is_admin flag in
 * conversation_members — checked server-side regardless of
 * which buttons the page happened to render.
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
$action = cleanInput($_POST['action'] ?? '');

$stmt = $conn->prepare('SELECT is_admin FROM conversation_members WHERE conversation_id = ? AND user_role = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('isi', $conversationId, $myRole, $myId);
$stmt->execute();
$isGroupAdmin = (bool) ($stmt->get_result()->fetch_assoc()['is_admin'] ?? 0);
$stmt->close();

if (!$isGroupAdmin) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to manage this group.']);
    exit;
}

if ($action === 'add') {
    $memberKeys = $_POST['members'] ?? [];
    $contacts = getChatContacts($conn, $myRole, $myId);
    $added = 0;
    foreach ($memberKeys as $key) {
        [$role, $id] = array_pad(explode(':', (string) $key, 2), 2, null);
        if (!$role || !$id) continue;
        foreach ($contacts as $c) {
            if ($c['role'] === $role && $c['id'] === (int) $id) {
                addConversationMemberIfMissing($conn, $conversationId, $role, (int) $id, false);
                notifyUser($conn, $role, (int) $id, 'Added to Group', 'You have been added to a group chat.');
                $added++;
                break;
            }
        }
    }
    echo json_encode(['success' => true, 'added' => $added]);
} elseif ($action === 'remove') {
    $targetRole = cleanInput($_POST['target_role'] ?? '');
    $targetId = (int) ($_POST['target_id'] ?? 0);
    if ($targetId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    $stmt = $conn->prepare('DELETE FROM conversation_members WHERE conversation_id = ? AND user_role = ? AND user_id = ?');
    $stmt->bind_param('isi', $conversationId, $targetRole, $targetId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
