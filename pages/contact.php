<?php
/**
 * ============================================================
 * contact.php
 * ------------------------------------------------------------
 * Public Contact page — college contact details. No message
 * form is wired to a backend yet, so this deliberately shows
 * direct contact info (email/phone) rather than a fake form
 * that would silently go nowhere.
 * ============================================================
 */
$pageTitle = 'Contact';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<section class="section">
  <div class="container text-center" data-aos="fade-up">
    <span class="section-tag">Get In Touch</span>
    <h1 class="section-title">Contact Us</h1>
    <p class="section-subtitle">Questions about registration, events, or your account? Reach the Sports
    Office directly.</p>
  </div>

  <div class="container mt-4">
    <div class="row g-4 justify-content-center">
      <div class="col-md-4" data-aos="fade-up">
        <div class="feature-card h-100 text-center">
          <i class="fa-solid fa-location-dot fa-2x mb-3" style="color:var(--primary,#3366FF);"></i>
          <h4>Address</h4>
          <p class="text-muted small mb-0">Government M.A.O Graduate College<br>Lahore, Punjab, Pakistan</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="feature-card h-100 text-center">
          <i class="fa-solid fa-envelope fa-2x mb-3" style="color:var(--primary,#3366FF);"></i>
          <h4>Email</h4>
          <p class="text-muted small mb-0"><a href="mailto:sports.office@maocollege.edu.pk">sports.office@maocollege.edu.pk</a></p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="feature-card h-100 text-center">
          <i class="fa-solid fa-phone fa-2x mb-3" style="color:var(--primary,#3366FF);"></i>
          <h4>Phone</h4>
          <p class="text-muted small mb-0">+92 42 111 000 000<br><span class="text-muted" style="font-size:.8rem;">Sports Office, Mon–Sat, 9am–4pm</span></p>
        </div>
      </div>
    </div>

    <div class="text-center mt-5" data-aos="fade-up">
      <p class="text-muted">Already have an account? <a href="<?php echo BASE_URL; ?>/login/index.php">Log in here</a> to message your coach or Admin directly through the platform.</p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
