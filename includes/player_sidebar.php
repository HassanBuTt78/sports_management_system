<?php
/**
 * ============================================================
 * player_sidebar.php
 * ------------------------------------------------------------
 * Left navigation for every player/*.php page. Mirrors
 * admin_sidebar.php's markup/behavior (same CSS + dashboard.js
 * hooks) so the sidebar toggle, active-link highlighting, and
 * dark-mode styling all work without any JS changes. Expects
 * the including page to have already called requireRole('player').
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
    <a href="<?php echo BASE_URL; ?>/player/dashboard.php" class="sidebar-link <?php echo sidebarActive('dashboard.php', $currentFile); ?>">
      <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
    </a>

    <span class="sidebar-section-label">My Sport</span>
    <?php if (!empty($_SESSION['team_id'])): ?>
    <a href="<?php echo BASE_URL; ?>/player/team/view.php" class="sidebar-link <?php echo sidebarActivePath('/player/team/', $currentPath); ?>">
      <i class="fa-solid fa-people-group"></i><span>My Team</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo BASE_URL; ?>/player/events/index.php" class="sidebar-link <?php echo sidebarActivePath('/player/events/', $currentPath); ?>">
      <i class="fa-solid fa-calendar-days"></i><span>Events</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/player/matches/index.php" class="sidebar-link <?php echo sidebarActivePath('/player/matches/', $currentPath); ?>">
      <i class="fa-solid fa-trophy"></i><span>Matches</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/player/performance.php" class="sidebar-link <?php echo sidebarActive('performance.php', $currentFile); ?>">
      <i class="fa-solid fa-chart-line"></i><span>My Performance</span>
    </a>

    <span class="sidebar-section-label">Communication</span>
    <a href="<?php echo BASE_URL; ?>/chat/index.php" class="sidebar-link <?php echo sidebarActivePath('/chat/', $currentPath); ?>">
      <i class="fa-solid fa-comments"></i><span>Messages</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/notifications/index.php" class="sidebar-link <?php echo sidebarActivePath('/notifications/', $currentPath); ?>">
      <i class="fa-solid fa-bell"></i><span>Notifications</span>
    </a>

    <span class="sidebar-section-label">Account</span>
    <a href="<?php echo BASE_URL; ?>/player/profile.php" class="sidebar-link <?php echo sidebarActive('profile.php', $currentFile); ?>">
      <i class="fa-solid fa-user"></i><span>Profile</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/login/logout.php" class="sidebar-link sidebar-logout">
      <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
    </a>
  </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
