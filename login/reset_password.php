<?php
/**
 * ============================================================
 * reset_password.php
 * ------------------------------------------------------------
 * Superseded — password reset is now a single 3-step flow
 * entirely inside forgot_password.php (security question, then
 * new password), so this file just redirects there. Kept in
 * place only so any old bookmarked/shared link doesn't 404.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';

redirectTo(BASE_URL . '/login/forgot_password.php');
