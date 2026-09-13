<?php
/**
 * ============================================================
 * logout.php
 * ------------------------------------------------------------
 * Logs out whichever role is currently signed in: destroys the
 * session, revokes + clears the Remember Me cookie/token (so a
 * stolen cookie can't be replayed after logout), logs the event,
 * and redirects to the public home page.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($conn) {
    if (isLoggedIn()) {
        logActivity($conn, currentRole(), (int) $_SESSION['user_id'], 'Logout');
    }
    clearRememberCookie($conn);
}
destroySession();

redirectTo(BASE_URL . '/index.php');
