<?php
/**
 * ============================================================
 * events.php
 * ------------------------------------------------------------
 * Public Events page. Shows real events from the `events` table
 * (Upcoming and Running only — not Completed/Cancelled clutter).
 * ============================================================
 */
$pageTitle = 'Events';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$events = [];
if ($conn) {
    $events = $conn->query(
        "SELECT e.*, s.sport_name FROM events e
         JOIN sports s ON e.sport_id = s.sport_id
         WHERE e.status IN ('Upcoming','Running')
         ORDER BY e.event_date ASC, e.start_time ASC"
    )->fetch_all(MYSQLI_ASSOC);
}
?>

<section class="section">
  <div class="container">
    <div class="section-header text-center" data-aos="fade-up">
      <span class="section-tag">What's Happening</span>
      <h1 class="section-title">Upcoming Events</h1>
      <p class="section-subtitle">Trials, tournaments, and fixtures across Cricket, Football, and Hockey.</p>
    </div>

    <?php if (empty($events)): ?>
      <p class="text-center text-muted mt-4">No events are scheduled right now — check back soon.</p>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($events as $i => $ev): ?>
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
            <div class="event-list-card">
              <div class="event-list-date">
                <span class="event-list-day"><?php echo date('d', strtotime($ev['event_date'])); ?></span>
                <span class="event-list-month"><?php echo date('M', strtotime($ev['event_date'])); ?></span>
              </div>
              <div class="event-list-body">
                <span class="badge <?php echo $ev['status'] === 'Running' ? 'badge-running' : 'badge-upcoming'; ?>"><?php echo safeOut($ev['status']); ?></span>
                <h3><?php echo safeOut($ev['title']); ?></h3>
                <p class="text-muted small mb-2"><i class="fa-solid fa-futbol me-1"></i><?php echo safeOut($ev['sport_name']); ?></p>
                <?php if ($ev['description']): ?><p class="small mb-2"><?php echo safeOut($ev['description']); ?></p><?php endif; ?>
                <p class="small text-muted mb-0">
                  <?php if ($ev['start_time']): ?><i class="fa-regular fa-clock me-1"></i><?php echo date('g:i A', strtotime($ev['start_time'])); ?><br><?php endif; ?>
                  <?php if ($ev['venue']): ?><i class="fa-solid fa-location-dot me-1"></i><?php echo safeOut($ev['venue']); ?><?php endif; ?>
                </p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>
  .event-list-card{ display:flex; gap:16px; background:#fff; border-radius:14px; padding:18px; box-shadow:0 4px 18px rgba(0,0,0,.06); height:100%; }
  .event-list-date{ flex:0 0 60px; text-align:center; background:var(--primary,#3366FF); color:#fff; border-radius:10px; padding:10px 4px; height:fit-content; }
  .event-list-day{ display:block; font-size:1.4rem; font-weight:800; line-height:1; }
  .event-list-month{ display:block; font-size:.75rem; text-transform:uppercase; opacity:.85; }
  .event-list-body h3{ font-size:1.05rem; margin:6px 0; }
  .badge-upcoming{ background:#e7f0ff; color:#3366FF; }
  .badge-running{ background:#fff4e0; color:#d97706; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
