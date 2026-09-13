<?php
/**
 * ============================================================
 * index.php (login/)
 * ------------------------------------------------------------
 * Simple role picker so the site's single "Login" nav button has
 * one place to send everyone, who then choose which of the 4
 * role-specific login pages they need.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<h2 class="auth-heading">Who's Signing In?</h2>
<p class="auth-subheading">Choose your role to continue.</p>

<div class="d-grid gap-3">
  <a href="admin_login.php" class="btn btn-auth-submit d-flex align-items-center justify-content-center">
    <i class="fa-solid fa-user-shield me-2"></i>Administrator
  </a>
  <a href="coach_login.php" class="btn btn-auth-submit d-flex align-items-center justify-content-center">
    <i class="fa-solid fa-whistle me-2"></i>Coach
  </a>
  <a href="player_login.php" class="btn btn-auth-submit d-flex align-items-center justify-content-center">
    <i class="fa-solid fa-person-running me-2"></i>Player
  </a>
</div>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
