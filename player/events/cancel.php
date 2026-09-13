<?php
/**
 * ============================================================
 * player/events/cancel.php
 * ------------------------------------------------------------
 * "Cancel participation before deadline." Ownership (player_id
 * = session) is baked into the DELETE's WHERE clause itself —
 * a player can only ever cancel their own registration, never
 * anyone else's, regardless of what participant ID is posted.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || currentRole() !== 'player') {
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

$playerId = (int) $_SESSION['user_id'];
$eventId = (int) ($_POST['event_id'] ?? 0);

if ($eventId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event.']);
    exit;
}

$stmt = $conn->prepare('SELECT title, status, registration_deadline FROM events WHERE event_id = ? LIMIT 1');
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit;
}
if ($event['registration_deadline'] && strtotime($event['registration_deadline']) < time()) {
    echo json_encode(['success' => false, 'message' => 'The registration deadline has passed — you can no longer cancel.']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM event_participants WHERE event_id = ? AND player_id = ?');
$stmt->bind_param('ii', $eventId, $playerId);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    logActivity($conn, 'player', $playerId, "Cancelled participation in event #{$eventId}");
}

echo json_encode(['success' => $success, 'message' => $success ? 'Your registration has been cancelled.' : 'Could not cancel.']);
