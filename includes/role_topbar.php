<?php
/**
 * ============================================================
 * role_topbar.php
 * ------------------------------------------------------------
 * Companion to role_sidebar.php — includes the correct
 * role-specific topbar (unread counts scoped to the right role)
 * for pages shared across multiple roles. See role_sidebar.php
 * for the full rationale.
 * ============================================================
 */
switch ($_SESSION['role'] ?? '') {
    case 'coach':
        require_once __DIR__ . '/coach_topbar.php';
        break;
    case 'player':
        require_once __DIR__ . '/player_topbar.php';
        break;
    default:
        require_once __DIR__ . '/admin_topbar.php';
        break;
}
