<?php
/**
 * ============================================================
 * navbar.php
 * ------------------------------------------------------------
 * Sticky main navigation. Uses isActivePage() from config.php
 * to highlight the current page. Kept separate from header.php
 * so it can be reused / swapped independently (e.g. a logged-in
 * navbar variant later) without touching the <head> markup.
 * ============================================================
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top main-navbar" id="mainNavbar">
  <div class="container">

    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/index.php">
      <img src="<?php echo ASSETS_URL; ?>/images/logo.svg" alt="<?php echo SITE_NAME; ?> logo" width="38" height="38">
      <span class="brand-text">
        <span class="brand-main">Sports</span><span class="brand-accent">MS</span>
      </span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"
            aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item"><a class="nav-link <?php echo isActivePage('index.php'); ?>" href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link <?php echo isActivePage('about.php'); ?>" href="<?php echo BASE_URL; ?>/pages/about.php">About</a></li>
        <li class="nav-item"><a class="nav-link <?php echo isActivePage('sports.php'); ?>" href="<?php echo BASE_URL; ?>/pages/sports.php">Sports</a></li>
        <li class="nav-item"><a class="nav-link <?php echo isActivePage('events.php'); ?>" href="<?php echo BASE_URL; ?>/pages/events.php">Events</a></li>
        <li class="nav-item"><a class="nav-link <?php echo isActivePage('gallery.php'); ?>" href="<?php echo BASE_URL; ?>/pages/gallery.php">Gallery</a></li>
        <li class="nav-item"><a class="nav-link <?php echo isActivePage('contact.php'); ?>" href="<?php echo BASE_URL; ?>/pages/contact.php">Contact</a></li>
        <li class="nav-item ms-lg-2">
          <a class="btn btn-login" href="<?php echo BASE_URL; ?>/login/index.php">
            <i class="fa-solid fa-right-to-bracket me-1"></i> Login
          </a>
        </li>
      </ul>
    </div>

  </div>
</nav>
