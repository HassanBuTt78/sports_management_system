<?php
/**
 * ============================================================
 * config.php
 * ------------------------------------------------------------
 * Global site configuration + database connection + auth bootstrap.
 * ============================================================
 */
define('SITE_NAME', 'Sports Management System');
define('COLLEGE_NAME', 'Government M.A.O Graduate College Lahore');

// Encryption key for the admin-recoverable credential field.
require_once __DIR__ . '/env.php';

// ---------- Base paths ----------
// Empty string in production (Render serves from domain root).
// Set BASE_URL=/SportsManagementSystem as an env var only if you're
// still testing this same code under XAMPP in a subfolder.
define('BASE_URL', getenv('BASE_URL') ?: '');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ---------- Contact info ----------
define('SITE_EMAIL', 'sports@maograduatecollege.edu.pk');
define('SITE_PHONE', '(042) 111-000-222');
define('SITE_ADDRESS', 'Government M.A.O Graduate College, Lahore, Punjab');

// ---------- Social links ----------
define('SOCIAL_FACEBOOK', '#');
define('SOCIAL_TWITTER', '#');
define('SOCIAL_INSTAGRAM', '#');
define('SOCIAL_YOUTUBE', '#');

function isActivePage(string $page): string {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}

// ================================================================
// DATABASE CONNECTION (mysqli)
// ================================================================

// ---------- Database credentials ----------
// Reads from environment variables (set these in Render's dashboard).
// Falls back to XAMPP defaults so local development still works
// with zero setup.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'sports_management_system');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// ---------- Open the connection ----------
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = null;
$db_connection_error = null;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    $conn = null;
    $db_connection_error =
        'Database not connected — check DB_HOST/DB_USER/DB_PASS/DB_NAME ' .
        'environment variables and that the schema has been imported. (' . $e->getMessage() . ')';
}

function sanitize(mysqli $conn, string $value): string {
    return $conn->real_escape_string(trim($value));
}

// ================================================================
// AUTH BOOTSTRAP (Module 3)
// ================================================================
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

startSecureSession();
