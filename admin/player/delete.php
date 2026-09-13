<?php
/**
 * ============================================================
 * admin/player/delete.php
 * ------------------------------------------------------------
 * "Delete" a player — per the brief, this NEVER runs a SQL
 * DELETE. It sets status = 'inactive' (soft delete), so match
 * history, scores, and ratings tied to this player_id stay
 * intact. Confirmed client-side with SweetAlert2 before this
 * endpoint is ever called (see player.js).
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

$playerId = (int) ($_POST['player_id'] ?? 0);
if ($playerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid player.']);
    exit;
}

$stmt = $conn->prepare("UPDATE players SET status = 'inactive' WHERE player_id = ?");
$stmt->bind_param('i', $playerId);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Deactivated player #{$playerId}");
}

echo json_encode(['success' => $success, 'message' => $success ? 'Player deactivated.' : 'Could not update player.']);
