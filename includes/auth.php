<?php
/**
 * ============================================================
 * auth.php
 * ------------------------------------------------------------
 * Core authentication logic shared by all four login pages:
 *   - attemptLogin()           full login flow incl. lockout
 *   - issueRememberToken()     "Remember Me" cookie creation
 *   - tryAutoLoginFromCookie() silent login from that cookie
 *
 * Every query here uses prepared statements (mysqli bind_param) —
 * no user input is ever concatenated into SQL. Passwords are only
 * ever compared with password_verify(), never stored or compared
 * as plain text.
 * ============================================================
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_MINUTES     = 15;
const REMEMBER_DAYS       = 30;
const REMEMBER_COOKIE     = 'sms_remember';

/**
 * Returns the active lockout expiry (as a DateTime) for this
 * account, or null if it isn't currently locked.
 */
function getLockoutUntil(mysqli $conn, string $role, int $userId): ?DateTime {
    $stmt = $conn->prepare(
        'SELECT locked_until FROM login_security WHERE user_role = ? AND user_id = ? LIMIT 1'
    );
    $stmt->bind_param('si', $role, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !$row['locked_until']) {
        return null;
    }
    $lockedUntil = new DateTime($row['locked_until']);
    return ($lockedUntil > new DateTime()) ? $lockedUntil : null;
}

/**
 * Increments the failed-attempt counter for this account and
 * applies a 15-minute lock once it reaches MAX_FAILED_ATTEMPTS.
 * Uses INSERT ... ON DUPLICATE KEY UPDATE so the first failed
 * attempt for an account creates its login_security row.
 */
function recordFailedAttempt(mysqli $conn, string $role, int $userId): int {
    $stmt = $conn->prepare(
        'INSERT INTO login_security (user_role, user_id, failed_attempts, last_attempt_at)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE
            failed_attempts = failed_attempts + 1,
            last_attempt_at = NOW()'
    );
    $stmt->bind_param('si', $role, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT failed_attempts FROM login_security WHERE user_role = ? AND user_id = ? LIMIT 1'
    );
    $stmt->bind_param('si', $role, $userId);
    $stmt->execute();
    $attempts = (int) ($stmt->get_result()->fetch_assoc()['failed_attempts'] ?? 0);
    $stmt->close();

    if ($attempts >= MAX_FAILED_ATTEMPTS) {
        $lockMinutes = LOCKOUT_MINUTES;
        $stmt = $conn->prepare(
            'UPDATE login_security
             SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
             WHERE user_role = ? AND user_id = ?'
        );
        $stmt->bind_param('isi', $lockMinutes, $role, $userId);
        $stmt->execute();
        $stmt->close();
    }

    return $attempts;
}

/** Clears failed-attempt count + lock after a successful login. */
function resetFailedAttempts(mysqli $conn, string $role, int $userId): void {
    $stmt = $conn->prepare(
        'INSERT INTO login_security (user_role, user_id, failed_attempts, locked_until)
         VALUES (?, ?, 0, NULL)
         ON DUPLICATE KEY UPDATE failed_attempts = 0, locked_until = NULL'
    );
    $stmt->bind_param('si', $role, $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Full login attempt for a given role. Returns:
 *   ['success' => bool, 'message' => string, 'redirect' => ?string]
 */
function attemptLogin(mysqli $conn, string $role, string $email, string $password, bool $remember): array {
    $cfg = roleConfig($role);
    if (!$cfg) {
        return ['success' => false, 'message' => 'Unknown account type.', 'redirect' => null];
    }

    $email = cleanInput($email);
    if ($email === '' || $password === '') {
        return ['success' => false, 'message' => 'Please enter both email and password.', 'redirect' => null];
    }
    if (!isValidEmail($email)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.', 'redirect' => null];
    }

    $table = $cfg['table'];
    $idCol = $cfg['id_column'];

    // Select the extra columns each role's session needs (spec: sport_id
    // for coach; coach_id/sport_id/team_id for player).
    $extraSelect = ', NULL AS sport_id, NULL AS coach_id, NULL AS team_id';
    if ($role === 'coach')  $extraSelect = ', sport_id, NULL AS coach_id, NULL AS team_id';
    if ($role === 'player') $extraSelect = ', sport_id, coach_id, team_id';

    $stmt = $conn->prepare(
        "SELECT {$idCol} AS id, full_name, email, password, profile_image, status {$extraSelect}
         FROM {$table} WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // Deliberately generic — do not reveal whether the email exists.
        return ['success' => false, 'message' => 'Invalid email or password.', 'redirect' => null];
    }

    $userId = (int) $user['id'];

    // 1. Already locked out?
    $lockedUntil = getLockoutUntil($conn, $role, $userId);
    if ($lockedUntil) {
        $minutesLeft = max(1, (int) ceil(($lockedUntil->getTimestamp() - time()) / 60));
        return [
            'success' => false,
            'message' => "Too many failed attempts. This account is locked for {$minutesLeft} more minute(s).",
            'redirect' => null,
        ];
    }

    // 2. Verify password.
    if (!password_verify($password, $user['password'])) {
        $attempts = recordFailedAttempt($conn, $role, $userId);
        $remaining = max(0, MAX_FAILED_ATTEMPTS - $attempts);
        $message = $remaining > 0
            ? "Invalid email or password. {$remaining} attempt(s) remaining before this account is locked."
            : 'Too many failed attempts. This account is now locked for ' . LOCKOUT_MINUTES . ' minutes.';
        return ['success' => false, 'message' => $message, 'redirect' => null];
    }

    // 3. Account status.
    if ($user['status'] !== 'active') {
        return [
            'success' => false,
            'message' => 'Your account has been disabled. Please contact the Administrator.',
            'redirect' => null,
        ];
    }

    // 4. Success — reset security state, start session, log it.
    resetFailedAttempts($conn, $role, $userId);
    createUserSession($user, $role);
    logActivity($conn, $role, $userId, 'Login successful (' . getBrowserName() . ')');

    if ($remember) {
        issueRememberToken($conn, $role, $userId);
    }

    return ['success' => true, 'message' => 'Login successful.', 'redirect' => $cfg['dashboard']];
}

/**
 * Issues a new Remember Me cookie + database record using the
 * selector/validator pattern described in schema_module3.sql.
 */
function issueRememberToken(mysqli $conn, string $role, int $userId): void {
    $selector      = randomToken(9);   // looked up directly, not secret
    $validator     = randomToken(32);  // secret; only its hash is stored
    $validatorHash = hash('sha256', $validator);
    $expiresAt     = (new DateTime())->modify('+' . REMEMBER_DAYS . ' days')->format('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        'INSERT INTO remember_tokens (user_role, user_id, selector, validator_hash, expires_at)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sisss', $role, $userId, $selector, $validatorHash, $expiresAt);
    $stmt->execute();
    $stmt->close();

    setRememberCookie($selector, $validator);
}

/**
 * Attempts a silent login from the Remember Me cookie, if present
 * and valid. Should be called early on any login page (before
 * rendering the form) so a returning visitor skips straight to
 * their dashboard. Rotates the token on every use.
 */
function tryAutoLoginFromCookie(mysqli $conn): void {
    if (isLoggedIn() || empty($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) {
        return;
    }
    [$selector, $validator] = $parts;

    $stmt = $conn->prepare('SELECT * FROM remember_tokens WHERE selector = ? LIMIT 1');
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $token = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$token || strtotime($token['expires_at']) < time()) {
        return;
    }

    if (!hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        // Validator mismatch on a valid selector = possible token theft.
        // Revoke it outright rather than silently failing.
        $del = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $del->bind_param('s', $selector);
        $del->execute();
        $del->close();
        return;
    }

    $role = $token['user_role'];
    $cfg  = roleConfig($role);
    if (!$cfg) return;

    $extraSelect = ', NULL AS sport_id, NULL AS coach_id, NULL AS team_id';
    if ($role === 'coach')  $extraSelect = ', sport_id, NULL AS coach_id, NULL AS team_id';
    if ($role === 'player') $extraSelect = ', sport_id, coach_id, team_id';

    $userIdInt = (int) $token['user_id'];
    $stmt = $conn->prepare(
        "SELECT {$cfg['id_column']} AS id, full_name, email, profile_image, status {$extraSelect}
         FROM {$cfg['table']} WHERE {$cfg['id_column']} = ? LIMIT 1"
    );
    $stmt->bind_param('i', $userIdInt);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['status'] !== 'active') {
        return;
    }

    // Rotate: delete the used token, issue a fresh one.
    $del = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
    $del->bind_param('s', $selector);
    $del->execute();
    $del->close();

    createUserSession($user, $role);
    logActivity($conn, $role, (int) $user['id'], 'Auto-login via Remember Me (' . getBrowserName() . ')');
    issueRememberToken($conn, $role, (int) $user['id']);
}

/**
 * Sets the Remember Me cookie itself (selector:validator, both
 * plain in the cookie — that's expected for this pattern; only
 * the validator's HASH ever touches the database).
 */
function setRememberCookie(string $selector, string $validator): void {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(
        REMEMBER_COOKIE,
        $selector . ':' . $validator,
        [
            'expires'  => time() + (REMEMBER_DAYS * 86400),
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

/** Clears the Remember Me cookie + its database row. Used by logout.php. */
function clearRememberCookie(mysqli $conn): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (!empty($parts[0])) {
            $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $stmt->bind_param('s', $parts[0]);
            $stmt->execute();
            $stmt->close();
        }
    }
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
}
