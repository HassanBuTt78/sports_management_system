<?php
/**
 * ============================================================
 * index.php
 * ------------------------------------------------------------
 * Application entry point. Assembles the landing page from
 * header + navbar + home content + footer. Kept intentionally
 * thin — page-specific markup lives in pages/home.php.
 * ============================================================
 */

$pageTitle = 'Home';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/pages/home.php';
require_once __DIR__ . '/includes/footer.php';
