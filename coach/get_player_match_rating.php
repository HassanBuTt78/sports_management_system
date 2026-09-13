<?php
/**
 * ============================================================
 * coach/get_player_match_rating.php
 * ------------------------------------------------------------
 * AJAX: returns any existing score/rating/comment for a
 * (player, match) pair so rate_player.php's form can switch
 * into "edit" mode instead of risking a duplicate (§4).
 * Ownership re-verified here too — a coach cannot probe another
 * sport's player/match combination even via direct AJAX calls.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || currentRole() !== 'coach' || !$conn) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$coachId = (int) $_SESSION['user_id'];
$playerId = (int) ($_GET['player_id'] ?? 0);
$matchId = (int) ($_GET['match_id'] ?? 0);

$stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
$stmt->bind_param('i', $coachId);
$stmt->execute();
$mySportId = (int) ($stmt->get_result()->fetch_assoc()['sport_id'] ?? 0);
$stmt->close();

$stmt = $conn->prepare('SELECT 1 FROM players WHERE player_id = ? AND sport_id = ? LIMIT 1');
$stmt->bind_param('ii', $playerId, $mySportId);
$stmt->execute();
$playerOk = (bool) $stmt->get_result()->fetch_row();
$stmt->close();

$stmt = $conn->prepare("SELECT 1 FROM matches WHERE match_id = ? AND sport_id = ? AND status = 'Completed' LIMIT 1");
$stmt->bind_param('ii', $matchId, $mySportId);
$stmt->execute();
$matchOk = (bool) $stmt->get_result()->fetch_row();
$stmt->close();

if (!$playerOk || !$matchOk) {
    echo json_encode(['success' => false, 'message' => 'Invalid player or match.']);
    exit;
}

$stmt = $conn->prepare('SELECT custom_score FROM player_scores WHERE player_id = ? AND match_id = ? LIMIT 1');
$stmt->bind_param('ii', $playerId, $matchId);
$stmt->execute();
$scoreRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('SELECT rating, review FROM player_ratings WHERE player_id = ? AND match_id = ? LIMIT 1');
$stmt->bind_param('ii', $playerId, $matchId);
$stmt->execute();
$ratingRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success' => true,
    'exists' => (bool) ($scoreRow || $ratingRow),
    'score' => $scoreRow['custom_score'] ?? null,
    'rating' => $ratingRow['rating'] ?? null,
    'comment' => $ratingRow['review'] ?? null,
]);
