<?php
/**
 * ============================================================
 * performance/api/leaderboard_data.php
 * ------------------------------------------------------------ */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !$conn) {
    echo json_encode(['success' => false, 'rows' => []]);
    exit;
}

$sportId = (int) ($_GET['sport_id'] ?? 0);
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));

$sql = "SELECT pa.`rank`, pa.performance_score, pa.average_rating, pa.matches_played, pa.trend,
               p.player_id, p.full_name, s.sport_name, t.team_name
        FROM performance_analysis pa
        JOIN players p ON pa.player_id = p.player_id
        JOIN sports s ON pa.sport_id = s.sport_id
        LEFT JOIN teams t ON pa.team_id = t.team_id
        WHERE pa.performance_score IS NOT NULL";
if ($sportId > 0) $sql .= ' AND pa.sport_id = ' . $sportId;
$sql .= ' ORDER BY pa.performance_score DESC LIMIT ' . $limit;

$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success' => true, 'rows' => $rows]);
