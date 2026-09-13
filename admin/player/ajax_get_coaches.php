<?php
/**
 * ============================================================
 * admin/player/ajax_get_coaches.php
 * ------------------------------------------------------------
 * Returns coaches for a given sport_id as JSON. Powers the
 * "Assign Coach" dropdown on create.php/edit.php — only coaches
 * belonging to the currently-selected sport are ever shown, per
 * the brief.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn() || currentRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'coaches' => []]);
    exit;
}

$sportId = (int) ($_GET['sport_id'] ?? 0);
$coaches = [];

if ($conn && $sportId > 0) {
    $stmt = $conn->prepare(
        "SELECT coach_id, full_name FROM coaches WHERE sport_id = ? AND status = 'active' ORDER BY full_name"
    );
    $stmt->bind_param('i', $sportId);
    $stmt->execute();
    $coaches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode(['success' => true, 'coaches' => $coaches]);
