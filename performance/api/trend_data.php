<?php
/**
 * ============================================================
 * performance/api/trend_data.php
 * ------------------------------------------------------------
 * Powers player.php's 7/10/All Time trend range buttons.
 * ============================================================ */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !$conn) {
    echo json_encode(['success' => false, 'labels' => [], 'scores' => []]);
    exit;
}

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$playerId = (int) ($_GET['player_id'] ?? 0);
$range = cleanInput($_GET['range'] ?? '7');

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

$stmt = $conn->prepare(
    "SELECT h.performance_score, m.match_title, m.match_date FROM player_performance_history h
     JOIN matches m ON h.match_id = m.match_id WHERE h.player_id = ? ORDER BY m.match_date ASC"
);
$stmt->bind_param('i', $playerId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($range !== 'all' && ctype_digit($range)) {
    $rows = array_slice($rows, -1 * (int) $range);
}

echo json_encode([
    'success' => true,
    'labels' => array_map(fn($r) => $r['match_title'] ?: date('M j', strtotime($r['match_date'])), $rows),
    'scores' => array_map(fn($r) => $r['performance_score'] !== null ? round((float) $r['performance_score'], 1) : null, $rows),
]);
