<?php
/**
 * ============================================================
 * admin/team/delete.php
 * ------------------------------------------------------------
 * "Delete" a team — like Player/Coach Management, this is a
 * SOFT delete (status = 'inactive'), not a SQL DELETE. Teams are
 * referenced by matches.team_one with ON DELETE CASCADE, so a
 * real delete could silently wipe match history — soft delete
 * avoids that entirely, and keeps every module's data intact.
 * Admin-only, matching "Cannot delete other teams" (coaches
 * never reach this endpoint at all).
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

$teamId = (int) ($_POST['team_id'] ?? 0);
if ($teamId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid team.']);
    exit;
}

$stmt = $conn->prepare("UPDATE teams SET status = 'inactive' WHERE team_id = ?");
$stmt->bind_param('i', $teamId);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Deactivated team #{$teamId}");
}

echo json_encode(['success' => $success, 'message' => $success ? 'Team deactivated.' : 'Could not update team.']);
