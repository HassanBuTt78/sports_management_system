<?php
/**
 * ============================================================
 * player/events/index.php
 * ------------------------------------------------------------
 * "View all events related to their sport" — scoped with
 * WHERE sport_id = {player's own sport}. Coach-created events
 * that are still pending admin approval are excluded — players
 * never see unapproved events.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('player');

$playerId = (int) $_SESSION['user_id'];
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$events = [];

if ($conn && $mySportId) {
    $stmt = $conn->prepare(
        "SELECT e.*,
                (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.event_id AND ep.participation_status != 'Rejected') AS participant_count,
                (SELECT participation_status FROM event_participants ep2 WHERE ep2.event_id = e.event_id AND ep2.player_id = ?) AS my_status
         FROM events e
         WHERE e.sport_id = ? AND (e.is_approved = 1 OR e.created_by_role = 'admin')
         ORDER BY e.event_date DESC"
    );
    $stmt->bind_param('ii', $playerId, $mySportId);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Events';
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
  <?php require_once __DIR__ . '/../../includes/player_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/player_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-calendar-days me-2"></i>Events</h1><p class="text-muted">Every event for your sport.</p></div>
    <a href="<?php echo BASE_URL; ?>/player/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
  </div>

  <?php if (empty($events)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No events for your sport yet.</p></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($events as $e): ?>
        <div class="col-md-6 col-lg-4">
          <div class="event-mini-card h-100">
            <span class="event-mini-badge badge-<?php echo str_replace(' ', '', $e['status']); ?>"><?php echo safeOut($e['status']); ?></span>
            <?php if ($e['my_status']): ?>
              <span class="badge bg-primary ms-1">You: <?php echo safeOut($e['my_status']); ?></span>
            <?php endif; ?>
            <h3 style="font-size:1.05rem; margin:.5em 0 .3em;"><?php echo safeOut($e['title']); ?></h3>
            <ul class="event-mini-meta">
              <li><i class="fa-solid fa-location-dot"></i><?php echo safeOut($e['venue'] ?? 'TBA'); ?></li>
              <li><i class="fa-solid fa-calendar-day"></i><?php echo date('M j, Y', strtotime($e['event_date'])); ?></li>
              <li><i class="fa-solid fa-users"></i><?php echo (int) $e['participant_count']; ?><?php echo $e['max_participants'] ? ' / ' . $e['max_participants'] : ''; ?> registered</li>
            </ul>
            <a href="<?php echo BASE_URL; ?>/player/events/view.php?id=<?php echo $e['event_id']; ?>" class="btn btn-primary btn-sm w-100 mt-2">View Details</a>
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
