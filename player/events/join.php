<?php
/**
 * ============================================================
 * player/events/join.php
 * ------------------------------------------------------------
 * "Player clicks Join Event" flow from the brief, exactly as
 * specified: check same sport, registration open, seats
 * available, not already registered (all via
 * checkJoinEligibility()) -> save registration -> update
 * participant count (computed live, nothing to "update") ->
 * notify coach and admin.
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
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);

if ($eventId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND (is_approved = 1 OR created_by_role = 'admin') LIMIT 1");
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit;
}

$eligibility = checkJoinEligibility($conn, $event, $playerId, $mySportId);
if (!$eligibility['eligible']) {
    echo json_encode(['success' => false, 'message' => $eligibility['reason']]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO event_participants (event_id, player_id, participation_status) VALUES (?, ?, 'Pending')");
$stmt->bind_param('ii', $eventId, $playerId);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    logActivity($conn, 'player', $playerId, "Joined event: {$event['title']} (#{$eventId})");

    // Notify coach + admin, per the brief.
    if ($event['coach_id']) {
        notifyUser($conn, 'coach', (int) $event['coach_id'], 'New Registration', "A player registered for \"{$event['title']}\".");
    }
    notifyUser($conn, 'admin', null, 'New Event Registration', "A player registered for \"{$event['title']}\".");
}

echo json_encode([
    'success' => $success,
    'message' => $success ? 'You have joined this event! Your registration is pending approval.' : 'Could not join the event. Please try again.',
]);
