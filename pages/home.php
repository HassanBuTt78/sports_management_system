<?php
/**
 * ============================================================
 * home.php
 * ------------------------------------------------------------
 * Landing page content only (no <head>/<body> tags here — those
 * live in header.php / footer.php). Included by index.php.
 *
 * Sections: Hero, Sports, Features, Statistics, Upcoming Events,
 * Gallery, Testimonials, Call To Action.
 *
 * All data below is placeholder content for Module 1. Real data
 * will come from the database once the backend modules are built.
 * ============================================================
 */

// ---- Placeholder data (will be replaced by DB queries later) ----
$sportsData = [
    ['id' => 'football', 'name' => 'Football', 'img' => 'sport-football.svg', 'desc' => 'Full-contact 11-a-side league with a season-long knockout bracket.'],
    ['id' => 'cricket',  'name' => 'Cricket',  'img' => 'sport-cricket.svg',  'desc' => 'Inter-department T20 and one-day league fixtures every season.'],
    ['id' => 'hockey',   'name' => 'Hockey',   'img' => 'sport-hockey.svg',   'desc' => 'Fast-paced field hockey league with inter-department fixtures all season.'],
];

$featuresData = [
    ['icon' => 'fa-user-group',       'title' => 'Player Management',     'desc' => 'Centralized player profiles, rosters, and sport assignments.'],
    ['icon' => 'fa-whistle',          'title' => 'Coach Management',      'desc' => 'Dedicated coach accounts scoped to their own sport only.'],
    ['icon' => 'fa-calendar-check',   'title' => 'Event Management',      'desc' => 'Create, approve, and track events from proposal to completion.'],
    ['icon' => 'fa-chart-line',       'title' => 'Performance Analytics', 'desc' => 'AI-assisted scoring, rankings, and season-long player trends.'],
    ['icon' => 'fa-comments',         'title' => 'Live Communication',    'desc' => 'Direct chat between admins, coaches, and players.'],
    ['icon' => 'fa-file-invoice',     'title' => 'Reports & Statistics',  'desc' => 'Exportable reports on top players, teams, and coaches.'],
];

$statsData = [
    ['count' => 150, 'suffix' => '+', 'label' => 'Players'],
    ['count' => 12,  'suffix' => '',  'label' => 'Coaches'],
    ['count' => 40,  'suffix' => '',  'label' => 'Teams'],
    ['count' => 120, 'suffix' => '',  'label' => 'Events'],
];

$eventsData = [
    ['name' => 'Inter-Dept Football Opener',  'date' => 'Aug 14, 2026', 'time' => '4:00 PM', 'venue' => 'Main Ground',    'status' => 'Upcoming'],
    ['name' => 'Cricket League Trials',       'date' => 'Aug 21, 2026', 'time' => '9:00 AM', 'venue' => 'Nets Complex',   'status' => 'Upcoming'],
    ['name' => 'Hockey League Trials',        'date' => 'Aug 29, 2026', 'time' => '3:00 PM', 'venue' => 'Hockey Ground', 'status' => 'Running'],
];

$galleryData = ['gallery-1.svg','gallery-2.svg','gallery-3.svg','gallery-4.svg','gallery-5.svg','gallery-6.svg','gallery-7.svg','gallery-8.svg'];

$testimonialsData = [
    ['name' => 'Ahmad Raza',  'role' => 'Student, BS Computer Science', 'img' => 'testimonial-student.svg',   'quote' => 'Registering for the football trials took two minutes on my phone instead of standing in a line outside the sports office.'],
    ['name' => 'Coach M. Hassan', 'role' => 'Head Coach, Cricket',      'img' => 'testimonial-coach.svg',     'quote' => 'I can rate every player after a match and see the season trend instantly, instead of keeping a paper notebook.'],
    ['name' => 'Dr. S. Khalid',   'role' => 'Sports Administrator',     'img' => 'testimonial-coach.svg', 'quote' => 'For the first time I can see every sport, every coach, and every event from one dashboard.'],
];
?>

<!-- ============================================================
     HERO SECTION
============================================================ -->
<header class="hero-section d-flex align-items-center" id="top"
        style="background-image: linear-gradient(180deg, rgba(6,20,45,.55), rgba(6,20,45,.85)), url('<?php echo ASSETS_URL; ?>/images/hero-banner.svg');">
  <div class="container">
    <div class="row justify-content-center text-center">
      <div class="col-lg-9" data-aos="fade-up">
        <span class="hero-eyebrow">Government M.A.O Graduate College Lahore</span>
        <h1 class="hero-title">SPORTS MANAGEMENT SYSTEM</h1>
        <p class="hero-desc">A smart digital platform for managing college sports, players,
        coaches, events, teams, and performance analytics.</p>
        <div class="hero-buttons">
          <a href="<?php echo BASE_URL; ?>/login/index.php" class="btn btn-accent btn-lg">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Login
          </a>
          <a href="#sports" class="btn btn-outline-light btn-lg">
            <i class="fa-solid fa-futbol me-2"></i>Explore Sports
          </a>
        </div>
      </div>
    </div>
  </div>
  <a href="#sports" class="scroll-indicator" aria-label="Scroll down"><i class="fa-solid fa-chevron-down"></i></a>
</header>

<!-- ============================================================
     SPORTS SECTION
============================================================ -->
<section class="section sports-section" id="sports">
  <div class="container">
    <div class="section-header text-center" data-aos="fade-up">
      <span class="section-tag">Sports Included</span>
      <h2 class="section-title">Three Sports, One Platform</h2>
      <p class="section-subtitle">Every sport runs with its own coach, roster, and events.</p>
    </div>

    <div class="row g-4">
      <?php foreach ($sportsData as $i => $sport): ?>
      <div class="col-lg-3 col-md-6" id="<?php echo $sport['id']; ?>" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
        <div class="sport-card">
          <div class="sport-card-img">
            <img src="<?php echo ASSETS_URL; ?>/images/<?php echo $sport['img']; ?>" alt="<?php echo $sport['name']; ?>" loading="lazy">
          </div>
          <div class="sport-card-body">
            <h3><?php echo $sport['name']; ?></h3>
            <p><?php echo $sport['desc']; ?></p>
            <a href="<?php echo BASE_URL; ?>/pages/sports.php#<?php echo $sport['id']; ?>" class="sport-card-link">
              Learn More <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     FEATURES SECTION
============================================================ -->
<section class="section features-section">
  <div class="container">
    <div class="section-header text-center" data-aos="fade-up">
      <span class="section-tag">Why This System</span>
      <h2 class="section-title">Everything The Sports Office Needs</h2>
      <p class="section-subtitle">Six core modules working together as one platform.</p>
    </div>

    <div class="row g-4">
      <?php foreach ($featuresData as $i => $f): ?>
      <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo ($i % 3) * 100; ?>">
        <div class="feature-card">
          <div class="feature-icon"><i class="fa-solid <?php echo $f['icon']; ?>"></i></div>
          <h3><?php echo $f['title']; ?></h3>
          <p><?php echo $f['desc']; ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     STATISTICS SECTION (animated counters)
============================================================ -->
<section class="section stats-section">
  <div class="container">
    <div class="row g-4">
      <?php foreach ($statsData as $s): ?>
      <div class="col-lg-3 col-6" data-aos="zoom-in">
        <div class="stat-box">
          <div class="stat-number">
            <span class="counter" data-count="<?php echo $s['count']; ?>">0</span><?php echo $s['suffix']; ?>
          </div>
          <div class="stat-label"><?php echo $s['label']; ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     UPCOMING EVENTS SECTION
============================================================ -->
<section class="section events-section">
  <div class="container">
    <div class="section-header text-center" data-aos="fade-up">
      <span class="section-tag">Calendar</span>
      <h2 class="section-title">Upcoming Events</h2>
      <p class="section-subtitle">What's next across all four programs.</p>
    </div>

    <div class="row g-4">
      <?php foreach ($eventsData as $i => $e):
          $badgeClass = $e['status'] === 'Running' ? 'badge-running' : 'badge-upcoming';
      ?>
      <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
        <div class="event-card">
          <span class="event-badge <?php echo $badgeClass; ?>"><?php echo $e['status']; ?></span>
          <h3><?php echo $e['name']; ?></h3>
          <ul class="event-meta">
            <li><i class="fa-solid fa-calendar-days"></i><?php echo $e['date']; ?></li>
            <li><i class="fa-solid fa-clock"></i><?php echo $e['time']; ?></li>
            <li><i class="fa-solid fa-location-dot"></i><?php echo $e['venue']; ?></li>
          </ul>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?php echo BASE_URL; ?>/pages/events.php" class="btn btn-outline-primary-custom">View All Events</a>
    </div>
  </div>
</section>

<!-- ============================================================
     GALLERY SECTION
============================================================ -->
<section class="section gallery-section">
  <div class="container">
    <div class="section-header text-center" data-aos="fade-up">
      <span class="section-tag">Moments</span>
      <h2 class="section-title">Gallery</h2>
      <p class="section-subtitle">Snapshots from across the season.</p>
    </div>

    <div class="row g-3">
      <?php foreach ($galleryData as $i => $img): ?>
      <div class="col-lg-3 col-md-4 col-6" data-aos="fade-up" data-aos-delay="<?php echo ($i % 4) * 80; ?>">
        <div class="gallery-item">
          <img src="<?php echo ASSETS_URL; ?>/images/<?php echo $img; ?>" alt="Gallery photo <?php echo $i + 1; ?>" loading="lazy">
          <div class="gallery-overlay"><i class="fa-solid fa-magnifying-glass-plus"></i></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     TESTIMONIALS SECTION (Swiper carousel)
============================================================ -->
<section class="section testimonials-section">
  <div class="container">
    <div class="section-header text-center" data-aos="fade-up">
      <span class="section-tag">Voices</span>
      <h2 class="section-title">What People Are Saying</h2>
    </div>

    <div class="swiper testimonial-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php foreach ($testimonialsData as $t): ?>
        <div class="swiper-slide">
          <div class="testimonial-card">
            <i class="fa-solid fa-quote-left quote-icon"></i>
            <p class="testimonial-quote">"<?php echo $t['quote']; ?>"</p>
            <div class="testimonial-author">
              <img src="<?php echo ASSETS_URL; ?>/images/<?php echo $t['img']; ?>" alt="<?php echo $t['name']; ?>">
              <div>
                <h5><?php echo $t['name']; ?></h5>
                <span><?php echo $t['role']; ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </div>
</section>

<!-- ============================================================
     CALL TO ACTION SECTION
============================================================ -->
<section class="cta-section" data-aos="zoom-in">
  <div class="container text-center">
    <h2>Join College Sports Today</h2>
    <p>Register and participate in upcoming tournaments across football, cricket, and hockey.</p>
    <a href="<?php echo BASE_URL; ?>/login/index.php" class="btn btn-accent btn-lg">
      Get Started <i class="fa-solid fa-arrow-right ms-2"></i>
    </a>
  </div>
</section>
