<?php
/**
 * ============================================================
 * about.php
 * ------------------------------------------------------------
 * Public About page — static informational content about the
 * platform and how it's organized.
 * ============================================================
 */
$pageTitle = 'About';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<section class="section">
  <div class="container" data-aos="fade-up">
    <span class="section-tag">About Us</span>
    <h1 class="section-title">Government M.A.O Graduate College Lahore</h1>
    <p class="section-subtitle">A digital home for the college's sports programs — built to replace paper
    registers with a single, organized system for every coach and player.</p>
  </div>

  <div class="container mt-4">
    <div class="row g-4">
      <div class="col-md-6" data-aos="fade-up">
        <h3>What This Platform Does</h3>
        <p class="text-muted">The Sports Management System brings together everything the college's sports
        programs need in one place: player and coach profiles, team rosters, event scheduling, match results,
        and season-long performance tracking. What used to live across spreadsheets and notebooks now lives
        in one system everyone can log into.</p>
      </div>
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
        <h3>How It's Organized</h3>
        <p class="text-muted">The system has three kinds of accounts: an <strong>Admin</strong> who oversees
        the whole college program, <strong>Coaches</strong> who each manage one sport's players and matches,
        and <strong>Players</strong> who track their own schedule and progress. Every coach and player belongs
        to exactly one of our three sports — <strong>Cricket, Football, and Hockey</strong>.</p>
      </div>
    </div>

    <div class="row g-4 mt-2">
      <div class="col-md-4" data-aos="fade-up">
        <div class="feature-card h-100">
          <i class="fa-solid fa-trophy fa-2x mb-3" style="color:var(--primary,#3366FF);"></i>
          <h4>Fair Competition</h4>
          <p class="text-muted small mb-0">Every match follows the same process — final score, player ratings,
          and a written result — before it counts toward the season.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="feature-card h-100">
          <i class="fa-solid fa-chart-line fa-2x mb-3" style="color:var(--primary,#3366FF);"></i>
          <h4>Real Performance Data</h4>
          <p class="text-muted small mb-0">Player rankings update automatically after every completed match,
          so progress is always based on real results, not memory.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="feature-card h-100">
          <i class="fa-solid fa-shield-halved fa-2x mb-3" style="color:var(--primary,#3366FF);"></i>
          <h4>Secure by Design</h4>
          <p class="text-muted small mb-0">Every account is protected with securely hashed passwords, and
          each role only ever sees the information relevant to them.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
