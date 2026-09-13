<?php
/**
 * ============================================================
 * admin/coach/reveal_password.php
 * ------------------------------------------------------------
 * Admin-only "Show Password" action. Decrypts the coach's
 * `encrypted_password` column server-side (AES-256-GCM, key from
 * includes/env.php — never the database) and returns the
 * plaintext ONCE in this JSON response.
 *
 * Safeguards:
 *   - Session + role re-checked here, not just trusted from the
 *     page that linked here.
 *   - Every reveal is written to activity_logs for an audit trail.
 *   - Nothing is echoed into HTML — this is a pure JSON endpoint
 *     fetched by JS, so the password never sits in page source.
 *   - No caching headers, so browsers/proxies won't retain a copy.
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

$coachId = (int) ($_POST['coach_id'] ?? 0);
if ($coachId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid coach.']);
    exit;
}

$stmt = $conn->prepare('SELECT full_name, encrypted_password FROM coaches WHERE coach_id = ? LIMIT 1');
$stmt->bind_param('i', $coachId);
$stmt->execute();
$coach = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coach) {
    echo json_encode(['success' => false, 'message' => 'Coach not found.']);
    exit;
}

$plaintext = decryptCredential($coach['encrypted_password']);

if ($plaintext === null) {
    echo json_encode([
        'success' => false,
        'message' => 'No recoverable password on file for this account yet — use Reset Password to generate one.',
    ]);
    exit;
}

logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Revealed password for coach: {$coach['full_name']} (#{$coachId})");

echo json_encode(['success' => true, 'password' => $plaintext]);
