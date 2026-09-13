<?php
/**
 * ============================================================
 * admin/player/player_details.php
 * ------------------------------------------------------------
 * Lightweight JSON summary for the "quick view" SweetAlert2
 * popup triggered from the index.php DataTable — a fast preview
 * without leaving the list. The FULL profile (match history,
 * events, performance) lives at view.php, per the brief's
 * Feature 8 "Player Profile" spec.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || currentRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$playerId = (int) ($_GET['id'] ?? 0);
if (!$conn || $playerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Player not found.']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT p.full_name, p.roll_no, p.email, p.phone, p.status, p.profile_image, p.age, p.gender,
            s.sport_name, c.full_name AS coach_name, t.team_name, pa.average_rating
     FROM players p
     LEFT JOIN sports s ON p.sport_id = s.sport_id
     LEFT JOIN coaches c ON p.coach_id = c.coach_id
     LEFT JOIN teams t ON p.team_id = t.team_id
     LEFT JOIN performance_analysis pa ON pa.player_id = p.player_id
     WHERE p.player_id = ? LIMIT 1'
);
$stmt->bind_param('i', $playerId);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$player) {
    echo json_encode(['success' => false, 'message' => 'Player not found.']);
    exit;
}

$player['profile_image_url'] = $player['profile_image']
    ? UPLOADS_URL . '/' . $player['profile_image']
    : ASSETS_URL . '/images/default-avatar.svg';
$player['average_rating'] = $player['average_rating'] !== null ? round((float) $player['average_rating'], 1) : null;
$player['view_url'] = BASE_URL . '/admin/player/view.php?id=' . $playerId;
$player['edit_url'] = BASE_URL . '/admin/player/edit.php?id=' . $playerId;

echo json_encode(['success' => true, 'player' => $player]);
