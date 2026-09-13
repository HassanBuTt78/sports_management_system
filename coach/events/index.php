<?php
/**
 * ============================================================
 * coach/events/index.php
 * ------------------------------------------------------------
 * Events for the coach's own sport (so they see the full sport
 * calendar, not just their own events), scoped with
 * WHERE sport_id = {coach's own sport} in SQL. Edit/Cancel are
 * only enabled on events THIS coach created.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$mySportId = $_SESSION['sport_id'] ?? null;
$events = [];

if ($conn && $mySportId) {
    $stmt = $conn->prepare(
        "SELECT e.*, (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.event_id AND ep.participation_status != 'Rejected') AS participant_count
         FROM events e WHERE e.sport_id = ? ORDER BY e.event_date DESC"
    );
    $stmt->bind_param('i', $mySportId);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'My Sport Events';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> | <?php echo SITE_NAME; ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/events.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/coach_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/coach_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-calendar-days me-2"></i>My Sport's Events</h1><p class="text-muted">Every event for your sport. You can edit or cancel the ones you created.</p></div>
    <div class="d-flex gap-2">
      <a href="<?php echo BASE_URL; ?>/coach/events/create.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-calendar-plus me-1"></i>Create Event</a>
      <a href="<?php echo BASE_URL; ?>/coach/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <?php if (empty($events)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No events for your sport yet.</p></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($events as $e):
        $isMine = (int) $e['created_by_id'] === $coachId && $e['created_by_role'] === 'coach';
      ?>
        <div class="col-md-6 col-lg-4">
          <div class="panel h-100">
            <span class="event-mini-badge badge-<?php echo str_replace(' ', '', $e['status']); ?>"><?php echo safeOut($e['status']); ?></span>
            <?php if ($e['created_by_role'] === 'coach' && !$e['is_approved']): ?><span class="badge bg-warning text-dark ms-1">Pending Approval</span><?php endif; ?>
            <h3 style="font-size:1.05rem; margin:.5em 0 .3em;"><?php echo safeOut($e['title']); ?></h3>
            <p class="text-muted mb-1" style="font-size:.85rem;"><i class="fa-solid fa-location-dot me-1"></i><?php echo safeOut($e['venue'] ?? 'TBA'); ?></p>
            <p class="text-muted mb-2" style="font-size:.85rem;"><i class="fa-solid fa-calendar-day me-1"></i><?php echo date('M j, Y', strtotime($e['event_date'])); ?></p>
            <p class="mb-3" style="font-size:.85rem;"><i class="fa-solid fa-users me-1"></i><?php echo (int) $e['participant_count']; ?><?php echo $e['max_participants'] ? ' / ' . $e['max_participants'] : ''; ?> registered</p>
            <div class="d-flex gap-2">
              <a href="<?php echo BASE_URL; ?>/coach/events/participants.php?id=<?php echo $e['event_id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">Participants</a>
              <?php if ($isMine): ?>
                <a href="<?php echo BASE_URL; ?>/coach/events/edit.php?id=<?php echo $e['event_id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">Edit</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
