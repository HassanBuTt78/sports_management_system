<?php
/**
 * ============================================================
 * admin_sidebar.php
 * ------------------------------------------------------------
 * Left navigation for every admin/*.php page. Expects the
 * including page to already have called requireRole('admin').
 *
 * Only Dashboard, Profile, Settings, and Logout point at real
 * files (built in this module). Every other item is a working
 * <a> tag to its future module's planned URL — "connected with
 * placeholder links" per the brief — so the sidebar is already
 * complete and just needs those files added later; nothing here
 * will need to change when Module 5+ lands.
 * ============================================================
 */
$currentFile = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];

/** Small helper: is this sidebar item the active one? */
function sidebarActive(string $file, string $current): string {
    return $file === $current ? 'active' : '';
}

/** MODULE 5 ADDITION: active-state check for nested module folders (e.g. /admin/player/*). */
function sidebarActivePath(string $needle, string $currentPath): string {
    return (strpos($currentPath, $needle) !== false) ? 'active' : '';
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
    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="sidebar-link <?php echo sidebarActive('dashboard.php', $currentFile); ?>">
      <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
    </a>

    <span class="sidebar-section-label">Management</span>
    <a href="<?php echo BASE_URL; ?>/admin/player/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/player/', $currentPath); ?>">
      <i class="fa-solid fa-person-running"></i><span>Player Management</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/coach/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/coach/', $currentPath); ?>">
      <i class="fa-solid fa-whistle"></i><span>Coach Management</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/team/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/team/', $currentPath); ?>">
      <i class="fa-solid fa-people-group"></i><span>Team Management</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/sports/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/sports/', $currentPath); ?>">
      <i class="fa-solid fa-futbol"></i><span>Sports</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/events/', $currentPath); ?>">
      <i class="fa-solid fa-calendar-days"></i><span>Events</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/matches/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/matches/', $currentPath); ?>">
      <i class="fa-solid fa-trophy"></i><span>Matches</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="sidebar-link <?php echo sidebarActivePath('/performance/', $currentPath); ?>">
      <i class="fa-solid fa-chart-line"></i><span>Performance</span>
    </a>

    <span class="sidebar-section-label">Insights</span>
    <a href="<?php echo BASE_URL; ?>/performance/reports.php" class="sidebar-link">
      <i class="fa-solid fa-file-invoice"></i><span>Reports</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/chat/index.php" class="sidebar-link">
      <i class="fa-solid fa-comments"></i><span>Messages</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/notifications/index.php" class="sidebar-link">
      <i class="fa-solid fa-bell"></i><span>Notifications</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/gallery/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/gallery/', $currentPath); ?>">
      <i class="fa-solid fa-images"></i><span>Gallery</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/documents/index.php" class="sidebar-link <?php echo sidebarActivePath('/admin/documents/', $currentPath); ?>">
      <i class="fa-solid fa-folder-open"></i><span>Documents</span>
    </a>

    <span class="sidebar-section-label">Account</span>
    <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="sidebar-link <?php echo sidebarActive('settings.php', $currentFile); ?>">
      <i class="fa-solid fa-gear"></i><span>Settings</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/profile.php" class="sidebar-link <?php echo sidebarActive('profile.php', $currentFile); ?>">
      <i class="fa-solid fa-user"></i><span>Profile</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/login/logout.php" class="sidebar-link sidebar-logout">
      <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
    </a>
  </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
