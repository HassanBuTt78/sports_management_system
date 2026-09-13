<?php
/**
 * ============================================================
 * performance/api/performance_data.php
 * ------------------------------------------------------------
 * Returns the same breakdown player.php renders server-side, as
 * JSON — available for any future dynamic refresh without a
 * full page reload. Same RBAC scoping as trend_data.php.
 * ============================================================ */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/performance_engine.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !$conn) {
    echo json_encode(['success' => false]);
    exit;
}

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$playerId = (int) ($_GET['player_id'] ?? 0);

if ($myRole === 'player' && $playerId !== $myId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to access this player\'s performance.']);
    exit;
}
if ($myRole === 'coach') {
    $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
    $stmt->bind_param('i', $myId);
    $stmt->execute();
    $mySportId = (int) ($stmt->get_result()->fetch_assoc()['sport_id'] ?? 0);
    $stmt->close();
    $stmt = $conn->prepare('SELECT sport_id FROM players WHERE player_id = ? LIMIT 1');
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $targetSportId = (int) ($stmt->get_result()->fetch_assoc()['sport_id'] ?? -1);
    $stmt->close();
    if ($mySportId !== $targetSportId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized to access this player\'s performance.']);
        exit;
    }
}

$result = calculatePlayerPerformance($conn, $playerId);
echo json_encode(['success' => true] + $result);
