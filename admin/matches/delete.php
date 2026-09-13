<?php
/**
 * ============================================================
 * admin/matches/delete.php
 * ------------------------------------------------------------
 * Handles Cancel and Delete for matches (one endpoint, an
 * `action` param — same pattern as admin/events/delete.php).
 * Delete is a REAL delete but is blocked if the match already
 * has recorded player_scores, to avoid silently destroying
 * performance history — Cancel is offered instead in that case.
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

$matchId = (int) ($_POST['match_id'] ?? 0);
$action = cleanInput($_POST['action'] ?? '');
$adminId = (int) $_SESSION['user_id'];

if ($matchId <= 0 || !in_array($action, ['cancel', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

switch ($action) {
    case 'delete':
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM player_scores WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $scoreCount = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        if ($scoreCount > 0) {
            echo json_encode(['success' => false, 'message' => "This match has {$scoreCount} recorded score(s). Cancel it instead of deleting, to keep performance history."]);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM matches WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) logActivity($conn, 'admin', $adminId, "Deleted match #{$matchId}");
        break;

    case 'cancel':
        $stmt = $conn->prepare("UPDATE matches SET status = 'Cancelled' WHERE match_id = ?");
        $stmt->bind_param('i', $matchId);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) {
            logActivity($conn, 'admin', $adminId, "Cancelled match #{$matchId}");
            $stmt2 = $conn->prepare('SELECT coach_id, team_one, team_two FROM matches WHERE match_id = ? LIMIT 1');
            $stmt2->bind_param('i', $matchId);
            $stmt2->execute();
            $m = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            $notified = [];
            foreach (array_filter([$m['team_one'] ?? null, $m['team_two'] ?? null]) as $tid) {
                $stmt3 = $conn->prepare('SELECT coach_id FROM teams WHERE team_id = ? LIMIT 1');
                $stmt3->bind_param('i', $tid);
                $stmt3->execute();
                $cid = $stmt3->get_result()->fetch_assoc()['coach_id'] ?? null;
                $stmt3->close();
                if ($cid && !in_array((int) $cid, $notified, true)) {
                    notifyUser($conn, 'coach', (int) $cid, 'Match Cancelled', 'A match involving your team has been cancelled.');
                    $notified[] = (int) $cid;
                }
            }
        }
        break;
}

echo json_encode(['success' => $success ?? false, 'message' => ($success ?? false) ? 'Done.' : 'Could not complete the action.']);
