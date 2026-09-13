<?php
/**
 * ============================================================
 * gallery.php
 * ------------------------------------------------------------
 * Public photo gallery. Reads from the `gallery` table, which
 * Admin manages at admin/gallery/index.php.
 * ============================================================
 */
$pageTitle = 'Gallery';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$images = [];
if ($conn) {
    $images = $conn->query('SELECT * FROM gallery ORDER BY uploaded_at DESC')->fetch_all(MYSQLI_ASSOC);
}
?>

<section class="section">
  <div class="container" data-aos="fade-up">
    <span class="section-tag">Moments</span>
    <h1 class="section-title">Gallery</h1>
    <p class="section-subtitle">Photos from matches, events, and college sports life.</p>

    <?php if (empty($images)): ?>
      <p class="text-center text-muted mt-4">No photos have been added yet — check back soon.</p>
    <?php else: ?>
      <div class="row g-4 mt-2">
        <?php foreach ($images as $img): ?>
          <div class="col-md-4 col-sm-6" data-aos="fade-up">
            <div class="gallery-photo-card">
              <img src="<?php echo UPLOADS_URL . '/' . $img['image']; ?>" alt="<?php echo safeOut($img['title'] ?? 'Gallery photo'); ?>" loading="lazy">
              <?php if ($img['title']): ?><div class="gallery-photo-caption"><?php echo safeOut($img['title']); ?></div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>
  .gallery-photo-card{ border-radius:14px; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.08); background:#fff; }
  .gallery-photo-card img{ width:100%; height:230px; object-fit:cover; display:block; }
  .gallery-photo-caption{ padding:10px 14px; font-size:.9rem; font-weight:600; color:#14213d; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
