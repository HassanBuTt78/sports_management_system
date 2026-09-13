<?php
/**
 * ============================================================
 * notifications/mark_all_read.php
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

$stmt = $conn->prepare(
    "UPDATE notifications SET status = 'read'
     WHERE (receiver_role = ? AND (receiver_id = ? OR receiver_id IS NULL)) OR receiver_role = 'all'"
);
$stmt->bind_param('si', $myRole, $myId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
