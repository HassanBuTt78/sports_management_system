<?php
/**
 * ============================================================
 * role_sidebar.php
 * ------------------------------------------------------------
 * For pages reachable by more than one role (performance/*,
 * chat/*, notifications/*) — includes the correct role-specific
 * sidebar instead of hardcoding admin_sidebar.php, so a coach or
 * player visiting a shared page still sees their own nav instead
 * of admin-only links. Falls back to the admin sidebar for
 * 'admin' and any unrecognized role. Expects requireRole(...) to
 * have already run and __DIR__ of the *including* page to be one
 * level below the project root (e.g. performance/, chat/,
 * notifications/) — same depth this file itself lives at.
 * ============================================================
 */
switch ($_SESSION['role'] ?? '') {
    case 'coach':
        require_once __DIR__ . '/coach_sidebar.php';
        break;
    case 'player':
        require_once __DIR__ . '/player_sidebar.php';
        break;
    default:
        require_once __DIR__ . '/admin_sidebar.php';
        break;
}
