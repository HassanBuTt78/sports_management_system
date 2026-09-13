<?php
/**
 * ============================================================
 * admin/coach/reset_password.php
 * ------------------------------------------------------------
 * Resets a coach's password to a NEW, RANDOMLY-GENERATED value —
 * Admin never types or chooses it (predictable admin-picked
 * passwords are exactly what this policy exists to avoid). The
 * plaintext is returned ONCE in this JSON response so Admin can
 * copy it, then stored as:
 *   - `password`            bcrypt hash, via password_hash() —
 *                            this is what login actually checks
 *                            with password_verify().
 *   - `encrypted_password`  AES-256-GCM ciphertext, via
 *                            encryptCredential() — lets Admin's
 *                            "Show Password" action recover it
 *                            later without ever touching bcrypt.
 * The plaintext itself is never written to disk/logs.
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

$coachId = (int) ($_POST['coach_id'] ?? 0);
if ($coachId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid coach.']);
    exit;
}

$stmt = $conn->prepare('SELECT full_name, email FROM coaches WHERE coach_id = ? LIMIT 1');
$stmt->bind_param('i', $coachId);
$stmt->execute();
$coach = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coach) {
    echo json_encode(['success' => false, 'message' => 'Coach not found.']);
    exit;
}

$plainPassword = generateSecurePassword();
$hash = password_hash($plainPassword, PASSWORD_DEFAULT);
$encrypted = encryptCredential($plainPassword);

$stmt = $conn->prepare('UPDATE coaches SET password = ?, encrypted_password = ? WHERE coach_id = ?');
$stmt->bind_param('ssi', $hash, $encrypted, $coachId);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Reset password for coach #{$coachId}");
}

echo json_encode([
    'success'    => $success,
    'message'    => $success ? 'Password reset successfully.' : 'Could not reset password.',
    'coach_name' => $coach['full_name'],
    'password'   => $success ? $plainPassword : null,
]);
