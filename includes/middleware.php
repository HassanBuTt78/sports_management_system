<?php
/**
 * ============================================================
 * middleware.php
 * ------------------------------------------------------------
 * Role-Based Access Control (RBAC) guard. Every protected page
 * (dashboards, and any future admin/coach/player page)
 * should `require` config.php first, then this file, then call
 * requireRole('admin') / requireRole('coach') / etc. as its very
 * first executable line — before any HTML or query output.
 *
 * This is what implements "Direct URL Protection": a Player who
 * types /admin/dashboard.php into the address bar never reaches
 * any admin content — they're bounced to the correct login page
 * (or to their own dashboard, if they're logged in as something
 * else) before a single line of the page renders.
 * ============================================================
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

/**
 * Guards a page to one or more roles.
 *
 * @param string ...$allowedRoles e.g. requireRole('admin') or
 *               requireRole('admin', 'coach') for a page
 *               shared by two roles.
 */
function requireRole(string ...$allowedRoles): void {
    // Not logged in at all -> send to the login page for the role
    // this page is guarding (first argument = the "home" role).
    if (!isLoggedIn()) {
        $cfg = roleConfig($allowedRoles[0] ?? 'admin');
        redirectTo($cfg['login_page'] ?? (BASE_URL . '/login/index.php'));
    }

    // Logged in, but as a role this page doesn't allow — this is
    // the "Coach cannot access Admin pages" case. Send them back
    // to THEIR OWN dashboard rather than a login page, since they
    // are authenticated, just not authorized for this resource.
    if (!in_array(currentRole(), $allowedRoles, true)) {
        $ownCfg = roleConfig(currentRole());
        redirectTo($ownCfg['dashboard'] ?? (BASE_URL . '/index.php'));
    }
}

/**
 * Optional stricter guard for "coach can only see their own sport"
 * style checks later (Module 5+). Not used by the placeholder
 * dashboards in this module, but included now so future modules
 * have a consistent place to extend RBAC without touching this
 * file's core requireRole() logic.
 */
function requireOwnResource(bool $condition): void {
    if (!$condition) {
        http_response_code(403);
        exit('403 — You do not have permission to access this resource.');
    }
}
