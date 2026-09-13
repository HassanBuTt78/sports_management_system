<?php
/**
 * ============================================================
 * admin/player/reveal_password.php
 * ------------------------------------------------------------
 * Admin-only "Show Password" action. Decrypts the player's
 * `encrypted_password` column server-side (AES-256-GCM, key from
 * includes/env.php — never the database) and returns the
 * plaintext ONCE in this JSON response. See
 * admin/coach/reveal_password.php for the same pattern/safeguards.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

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

$stmt = $conn->prepare('SELECT full_name, encrypted_password FROM players WHERE player_id = ? LIMIT 1');
$stmt->bind_param('i', $playerId);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$player) {
    echo json_encode(['success' => false, 'message' => 'Player not found.']);
    exit;
}

$plaintext = decryptCredential($player['encrypted_password']);

if ($plaintext === null) {
    echo json_encode([
        'success' => false,
        'message' => 'No recoverable password on file for this account yet — use Reset Password to generate one.',
    ]);
    exit;
}

logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Revealed password for player: {$player['full_name']} (#{$playerId})");

echo json_encode(['success' => true, 'password' => $plaintext]);
