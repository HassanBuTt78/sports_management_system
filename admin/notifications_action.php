<?php
/**
 * ============================================================
 * admin/notifications_action.php
 * ------------------------------------------------------------
 * AJAX endpoint used by the notification bell dropdown
 * (includes/admin_topbar.php + dashboard.js). Admin-only,
 * prepared statements only, JSON in/out.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

// requireRole() normally redirects; for an AJAX endpoint we want a
// JSON 401 instead of an HTML redirect the fetch() call can't follow.
if (!isLoggedIn() || currentRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database not connected.']);
    exit;
}

$notificationId = (int) ($_POST['notification_id'] ?? 0);
$action = cleanInput($_POST['action'] ?? '');

if ($notificationId <= 0 || !in_array($action, ['read', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($action === 'read') {
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE notification_id = ?");
} else {
    $stmt = $conn->prepare('DELETE FROM notifications WHERE notification_id = ?');
}
$stmt->bind_param('i', $notificationId);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
