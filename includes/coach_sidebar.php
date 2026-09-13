<?php
/**
 * ============================================================
 * coach_sidebar.php
 * ------------------------------------------------------------
 * Left navigation for every coach/*.php page. Mirrors
 * admin_sidebar.php's markup/behavior (same CSS + dashboard.js
 * hooks) so the sidebar toggle, active-link highlighting, and
 * dark-mode styling all work without any JS changes. Expects
 * the including page to have already called requireRole('coach').
 * ============================================================
 */
$currentFile = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];

if (!function_exists('sidebarActive')) {
    function sidebarActive(string $file, string $current): string {
        return $file === $current ? 'active' : '';
    }
}
if (!function_exists('sidebarActivePath')) {
    function sidebarActivePath(string $needle, string $currentPath): string {
        return (strpos($currentPath, $needle) !== false) ? 'active' : '';
    }
}
?>
<aside class="admin-sidebar" id="adminSidebar">

  <div class="sidebar-brand">
    <img src="<?php echo ASSETS_URL; ?>/images/logo.svg" alt="logo" width="34" height="34">
    <span class="sidebar-brand-text">
      <span class="brand-main">Sports</span><span class="brand-accent">MS</span>
    </span>
    <button class="sidebar-close d-lg-none" id="sidebarClose" aria-label="Close menu">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <span class="sidebar-section-label">Main</span>
    <a href="<?php echo BASE_URL; ?>/coach/dashboard.php" class="sidebar-link <?php echo sidebarActive('dashboard.php', $currentFile); ?>">
      <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
    </a>

    <span class="sidebar-section-label">Coaching</span>
    <a href="<?php echo BASE_URL; ?>/coach/team/index.php" class="sidebar-link <?php echo sidebarActivePath('/coach/team/', $currentPath); ?>">
      <i class="fa-solid fa-people-group"></i><span>My Teams</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/coach/events/index.php" class="sidebar-link <?php echo sidebarActivePath('/coach/events/', $currentPath); ?>">
      <i class="fa-solid fa-calendar-days"></i><span>Events</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/coach/matches/index.php" class="sidebar-link <?php echo sidebarActivePath('/coach/matches/', $currentPath); ?>">
      <i class="fa-solid fa-trophy"></i><span>Matches &amp; Scoring</span>
    </a>

    <span class="sidebar-section-label">Performance</span>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="sidebar-link <?php echo sidebarActivePath('/performance/', $currentPath); ?>">
      <i class="fa-solid fa-chart-line"></i><span>Performance Analysis</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/coach/player_performance.php" class="sidebar-link <?php echo sidebarActive('player_performance.php', $currentFile); ?>">
      <i class="fa-solid fa-star"></i><span>Rate Players</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/performance/compare.php" class="sidebar-link <?php echo sidebarActive('compare.php', $currentFile); ?>">
      <i class="fa-solid fa-scale-balanced"></i><span>Compare Players</span>
    </a>

    <span class="sidebar-section-label">Communication</span>
    <a href="<?php echo BASE_URL; ?>/chat/index.php" class="sidebar-link <?php echo sidebarActivePath('/chat/', $currentPath); ?>">
      <i class="fa-solid fa-comments"></i><span>Messages</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/chat/groups.php" class="sidebar-link <?php echo sidebarActive('groups.php', $currentFile); ?>">
      <i class="fa-solid fa-people-roof"></i><span>Groups</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/notifications/index.php" class="sidebar-link <?php echo sidebarActivePath('/notifications/', $currentPath); ?>">
      <i class="fa-solid fa-bell"></i><span>Notifications</span>
    </a>

    <span class="sidebar-section-label">Account</span>
    <a href="<?php echo BASE_URL; ?>/coach/profile.php" class="sidebar-link <?php echo sidebarActive('profile.php', $currentFile); ?>">
      <i class="fa-solid fa-user"></i><span>Profile</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/login/logout.php" class="sidebar-link sidebar-logout">
      <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
    </a>
  </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
