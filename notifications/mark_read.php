<?php
/**
 * ============================================================
 * notifications/mark_read.php
 * ------------------------------------------------------------
 * Handles both "mark as read" and "delete" for a single
 * notification (action param) — ownership is verified against
 * the session's own role/id before touching anything, including
 * broadcast rows (receiver_id NULL) which only get marked read
 * for THIS user's view, never deleted (deleting a broadcast row
 * would remove it for every recipient).
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
$notificationId = (int) ($_POST['notification_id'] ?? 0);
$action = cleanInput($_POST['action'] ?? 'read');

if ($notificationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT notification_id, receiver_id FROM notifications
     WHERE notification_id = ? AND ((receiver_role = ? AND (receiver_id = ? OR receiver_id IS NULL)) OR receiver_role = 'all') LIMIT 1"
);
$stmt->bind_param('isi', $notificationId, $myRole, $myId);
$stmt->execute();
$notif = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$notif) {
    echo json_encode(['success' => false, 'message' => 'Notification not found.']);
    exit;
}

if ($action === 'delete') {
    // Only delete personally-addressed notifications — never a broadcast row shared with others.
    if ($notif['receiver_id'] === null) {
        echo json_encode(['success' => false, 'message' => 'This is a shared announcement and cannot be deleted individually.']);
        exit;
    }
    $stmt = $conn->prepare('DELETE FROM notifications WHERE notification_id = ?');
    $stmt->bind_param('i', $notificationId);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE notification_id = ?");
    $stmt->bind_param('i', $notificationId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]);
