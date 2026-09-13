<?php
/**
 * ============================================================
 * admin/events/delete.php
 * ------------------------------------------------------------
 * Handles every admin quick-action button from the events list
 * via one endpoint + an `action` parameter: delete, cancel,
 * publish (open registration), approve (a coach-created event).
 *
 * "Delete" here is a REAL delete — unlike Player/Coach/Team
 * Management, Cancel already covers the "soft" need (status =
 * 'Cancelled'), so the brief's separate Delete feature is the
 * genuine removal option. To avoid silently destroying
 * registration history, delete is BLOCKED if the event already
 * has participants — Cancel is offered instead in that case.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || currentRole() !== 'admin') {
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

$eventId = (int) ($_POST['event_id'] ?? 0);
$action  = cleanInput($_POST['action'] ?? '');
$adminId = (int) $_SESSION['user_id'];

if ($eventId <= 0 || !in_array($action, ['delete', 'cancel', 'publish', 'approve'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare('SELECT title, coach_id, sport_id FROM events WHERE event_id = ? LIMIT 1');
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit;
}

switch ($action) {
    case 'delete':
        $participantCount = getEventParticipantCount($conn, $eventId);
        if ($participantCount > 0) {
            echo json_encode([
                'success' => false,
                'message' => "This event has {$participantCount} registered participant(s). Cancel it instead of deleting, to keep their registration history.",
            ]);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM events WHERE event_id = ?');
        $stmt->bind_param('i', $eventId);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) logActivity($conn, 'admin', $adminId, "Deleted event: {$event['title']} (#{$eventId})");
        break;

    case 'cancel':
        $stmt = $conn->prepare("UPDATE events SET status = 'Cancelled' WHERE event_id = ?");
        $stmt->bind_param('i', $eventId);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) {
            logActivity($conn, 'admin', $adminId, "Cancelled event: {$event['title']} (#{$eventId})");
            notifyUser($conn, 'player', null, 'Event Cancelled: ' . $event['title'], 'This event has been cancelled.');
            if ($event['coach_id']) {
                notifyUser($conn, 'coach', (int) $event['coach_id'], 'Event Cancelled', "\"{$event['title']}\" has been cancelled.");
            }
        }
        break;

    case 'publish':
        $stmt = $conn->prepare("UPDATE events SET status = 'Registration Open' WHERE event_id = ?");
        $stmt->bind_param('i', $eventId);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) {
            logActivity($conn, 'admin', $adminId, "Opened registration for event: {$event['title']} (#{$eventId})");
            notifyUser($conn, 'player', null, 'Registration Open: ' . $event['title'], 'Registration is now open for this event.');
        }
        break;

    case 'approve':
        $stmt = $conn->prepare('UPDATE events SET is_approved = 1 WHERE event_id = ?');
        $stmt->bind_param('i', $eventId);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) {
            logActivity($conn, 'admin', $adminId, "Approved event: {$event['title']} (#{$eventId})");
            if ($event['coach_id']) {
                notifyUser($conn, 'coach', (int) $event['coach_id'], 'Event Approved', "\"{$event['title']}\" has been approved and is now live.");
            }
        }
        break;
}

echo json_encode(['success' => $success ?? false, 'message' => ($success ?? false) ? 'Done.' : 'Could not complete the action.']);
