<?php
/**
 * ============================================================
 * config.php
 * ------------------------------------------------------------
 * Global site configuration + database connection + auth bootstrap.
 *
 * MODULE 2 added the mysqli connection. MODULE 3 adds functions.php
 * and session.php as standard requires here, so EVERY page in the
 * project — public pages and protected dashboards alike — gets a
 * hardened session and the shared auth helpers just by requiring
 * this one file, the same way it already gets $conn.
 * ============================================================
 */
define('SITE_NAME', 'Sports Management System');
define('COLLEGE_NAME', 'Government M.A.O Graduate College Lahore');

// Encryption key for the admin-recoverable credential field — kept in
// its own file (NOT the database) on purpose. See includes/env.php.
require_once __DIR__ . '/env.php';

// ---------- Base paths ----------
// BASE_URL should match your XAMPP htdocs folder name.
define('BASE_URL', '/SportsManagementSystem');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ---------- Contact info (used in footer / contact page) ----------
define('SITE_EMAIL', 'sports@maograduatecollege.edu.pk');
define('SITE_PHONE', '(042) 111-000-222');
define('SITE_ADDRESS', 'Government M.A.O Graduate College, Lahore, Punjab');

// ---------- Social links (placeholder, wire up real handles later) ----------
define('SOCIAL_FACEBOOK', '#');
define('SOCIAL_TWITTER', '#');
define('SOCIAL_INSTAGRAM', '#');
define('SOCIAL_YOUTUBE', '#');

/**
 * Small helper so nav links can mark themselves "active"
 * without a router. Compares against the current file name.
 */
function isActivePage(string $page): string {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}


// ================================================================
// DATABASE CONNECTION (mysqli)
// ================================================================

// ---------- Database credentials ----------
// Defaults match a fresh XAMPP install. Change DB_PASS if your
// MySQL root user has a password set.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sports_management_system');
define('DB_PORT', 3306);

// ---------- Open the connection ----------
// mysqli_report() makes mysqli throw exceptions on error instead of
// silently returning false — easier to debug during development.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// $conn stays null (rather than the script dying) if MySQL isn't
// running yet or schema.sql hasn't been imported. This keeps the
// Module 1 landing pages — which don't touch the database at all —
// working normally. Any page/module that DOES need the database
// should check `if ($conn) { ... } else { /* show a friendly notice */ }`
// before running queries.
$conn = null;
$db_connection_error = null;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    $conn = null;
    $db_connection_error =
        'Database not connected — make sure MySQL is running in XAMPP ' .
        'and that database/schema.sql has been imported. (' . $e->getMessage() . ')';
}

/**
 * Escape + trim a raw input value before using it outside a
 * prepared statement (e.g. building dynamic ORDER BY clauses).
 * Prefer prepared statements ($conn->prepare(...)) for anything
 * that touches WHERE/INSERT/UPDATE values — this helper is only
 * a safety net, not a replacement for them.
 */
function sanitize(mysqli $conn, string $value): string {
    return $conn->real_escape_string(trim($value));
}


// ================================================================
// AUTH BOOTSTRAP (Module 3)
// ================================================================
// Loaded here so every page — public or protected — gets the
// shared helpers and a hardened session without repeating requires.
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

startSecureSession();
