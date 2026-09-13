<?php
/**
 * ============================================================
 * session.php
 * ------------------------------------------------------------
 * Secure session lifecycle: starting sessions with hardened
 * cookie params, writing the authenticated-user session payload,
 * reading it back, and tearing it down on logout.
 *
 * config.php already calls session_start() with PHP defaults;
 * this file's startSecureSession() is the one every auth-aware
 * page should actually call — it sets cookie flags BEFORE the
 * session starts, which config.php's plain session_start() can't
 * do retroactively.
 * ============================================================
 */

/**
 * Start a session with hardened cookie parameters. Safe to call
 * more than once per request (checks session_status() first).
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // only sent over HTTPS once you deploy with it
        'httponly' => true,       // not readable via JavaScript (XSS mitigation)
        'samesite' => 'Lax',      // CSRF mitigation for cross-site requests
    ]);

    session_start();
}

/**
 * Write the full authenticated-user payload into the session
 * after a successful login, per the spec: user id, name, email,
 * role, profile image, sport_id (coach), coach_id/team_id (player),
 * plus login time. Regenerates the session ID first to defeat
 * session fixation attacks.
 */
function createUserSession(array $user, string $role): void {
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $role;
    $_SESSION['profile_image'] = $user['profile_image'] ?: null;
    $_SESSION['sport_id']      = $user['sport_id'] ?? null;
    $_SESSION['coach_id']      = $user['coach_id'] ?? null;
    $_SESSION['team_id']       = $user['team_id'] ?? null;
    $_SESSION['login_time']    = date('Y-m-d H:i:s');
}

/** True if any of the four roles is currently logged in. */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/** Convenience accessor for the current session's role, or null. */
function currentRole(): ?string {
    return $_SESSION['role'] ?? null;
}

/** Profile image path with a default-avatar fallback (spec requirement). */
function currentProfileImage(): string {
    $img = $_SESSION['profile_image'] ?? null;
    if ($img) {
        return UPLOADS_URL . '/' . ltrim($img, '/');
    }
    return ASSETS_URL . '/images/default-avatar.svg';
}

/**
 * Fully destroy the session: clear all session data, remove the
 * session cookie from the browser, and destroy the session on
 * the server. Cookie/token cleanup for "Remember Me" is handled
 * separately in logout.php (it needs the database connection).
 */
function destroySession(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
