<?php
/**
 * ============================================================
 * admin/player/ajax_get_teams.php
 * ------------------------------------------------------------
 * Returns teams for a given sport_id as JSON. Powers the
 * "Assign Team" dropdown on create.php/edit.php — only teams
 * belonging to the currently-selected sport are ever shown.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || currentRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'teams' => []]);
    exit;
}

$sportId = (int) ($_GET['sport_id'] ?? 0);
$teams = [];

if ($conn && $sportId > 0) {
    $stmt = $conn->prepare('SELECT team_id, team_name FROM teams WHERE sport_id = ? ORDER BY team_name');
    $stmt->bind_param('i', $sportId);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode(['success' => true, 'teams' => $teams]);
