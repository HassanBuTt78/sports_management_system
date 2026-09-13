<?php
/**
 * ============================================================
 * footer.php
 * ------------------------------------------------------------
 * Site footer (Quick Links, Contact, Social, Copyright) plus
 * the closing script tags for every page. Bootstrap/AOS/Swiper
 * JS are loaded here (end of body) for performance; script.js
 * is always loaded last since it depends on the libraries above.
 * ============================================================
 */
?>
  <footer class="site-footer">
    <div class="container">
      <div class="row gy-4 footer-top">

        <div class="col-lg-4 col-md-6">
          <a href="<?php echo BASE_URL; ?>/index.php" class="navbar-brand d-flex align-items-center gap-2 mb-3">
            <img src="<?php echo ASSETS_URL; ?>/images/logo.svg" alt="logo" width="36" height="36">
            <span class="brand-text"><span class="brand-main">Sports</span><span class="brand-accent">MS</span></span>
          </a>
          <p class="footer-about"><?php echo COLLEGE_NAME; ?> — a smart digital platform for managing
          college sports, players, coaches, events, teams, and performance analytics.</p>
          <div class="social-icons">
            <a href="<?php echo SOCIAL_FACEBOOK; ?>" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="<?php echo SOCIAL_TWITTER; ?>" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
            <a href="<?php echo SOCIAL_INSTAGRAM; ?>" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            <a href="<?php echo SOCIAL_YOUTUBE; ?>" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
          </div>
        </div>

        <div class="col-lg-2 col-md-6">
          <h6 class="footer-heading">Quick Links</h6>
          <ul class="footer-links">
            <li><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>/pages/about.php">About</a></li>
            <li><a href="<?php echo BASE_URL; ?>/pages/sports.php">Sports</a></li>
            <li><a href="<?php echo BASE_URL; ?>/pages/events.php">Events</a></li>
            <li><a href="<?php echo BASE_URL; ?>/pages/gallery.php">Gallery</a></li>
          </ul>
        </div>

        <div class="col-lg-3 col-md-6">
          <h6 class="footer-heading">Sports Programs</h6>
          <ul class="footer-links">
            <li><a href="<?php echo BASE_URL; ?>/pages/sports.php#football">Football</a></li>
            <li><a href="<?php echo BASE_URL; ?>/pages/sports.php#cricket">Cricket</a></li>
            <li><a href="<?php echo BASE_URL; ?>/pages/sports.php#hockey">Hockey</a></li>
          </ul>
        </div>

        <div class="col-lg-3 col-md-6">
          <h6 class="footer-heading">Contact</h6>
          <ul class="footer-contact">
            <li><i class="fa-solid fa-location-dot"></i><span><?php echo SITE_ADDRESS; ?></span></li>
            <li><i class="fa-solid fa-envelope"></i><span><?php echo SITE_EMAIL; ?></span></li>
            <li><i class="fa-solid fa-phone"></i><span><?php echo SITE_PHONE; ?></span></li>
          </ul>
        </div>

      </div>

      <div class="footer-bottom">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        <p class="mb-0 module-tag">Module 1 — Landing Website</p>
      </div>
    </div>
  </footer>

  <!-- Back to top -->
  <a href="#top" class="back-to-top" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></a>

  <!-- Bootstrap 5 JS Bundle (includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AOS Animation Library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.1/aos.js"></script>
  <!-- Swiper.js (Testimonials slider) -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <!-- Custom site script (loads last, depends on the above) -->
  <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
</body>
</html>
