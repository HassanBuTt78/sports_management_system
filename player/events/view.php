<?php
/**
 * ============================================================
 * player/events/view.php
 * ------------------------------------------------------------
 * Event Details Page for a player: banner, info, participant
 * count, remaining seats, Join button (only shown when actually
 * eligible — see checkJoinEligibility()), and Download Schedule.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('player');

$playerId = (int) $_SESSION['user_id'];
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$eventId = (int) ($_GET['id'] ?? 0);

$event = null;
if ($conn && $eventId > 0) {
    $stmt = $conn->prepare(
        "SELECT e.*, s.sport_name, c.full_name AS coach_name FROM events e
         LEFT JOIN sports s ON e.sport_id = s.sport_id LEFT JOIN coaches c ON e.coach_id = c.coach_id
         WHERE e.event_id = ? AND (e.is_approved = 1 OR e.created_by_role = 'admin') LIMIT 1"
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$event) {
    redirectTo(BASE_URL . '/player/events/index.php');
}

$myRegistration = null;
if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM event_participants WHERE event_id = ? AND player_id = ? LIMIT 1');
    $stmt->bind_param('ii', $eventId, $playerId);
    $stmt->execute();
    $myRegistration = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$participantCount = $conn ? getEventParticipantCount($conn, $eventId) : 0;
$remainingSeats = $event['max_participants'] ? max(0, (int) $event['max_participants'] - $participantCount) : null;
$eligibility = ($conn && !$myRegistration) ? checkJoinEligibility($conn, $event, $playerId, $mySportId) : ['eligible' => false, 'reason' => null];

$scheduleFile = null;
if ($conn) {
    $stmt = $conn->prepare("SELECT file_path FROM event_media WHERE event_id = ? AND media_type = 'pdf_schedule' ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $scheduleFile = $stmt->get_result()->fetch_assoc()['file_path'] ?? null;
    $stmt->close();
}

$pageTitle = 'Event Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo safeOut($event['title']); ?> | <?php echo SITE_NAME; ?></title>
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
    <div><h1>Event Details</h1></div>
    <a href="<?php echo BASE_URL; ?>/player/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Events</a>
    <a href="<?php echo BASE_URL; ?>/chat/event_chat.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-comments me-1"></i>Discussion</a>
  </div>

  <div id="joinFlashArea"></div>

  <div class="event-banner-hero" style="background-image:url('<?php echo $event['banner_image'] ? UPLOADS_URL . '/' . $event['banner_image'] : ASSETS_URL . '/images/hero-banner.svg'; ?>');">
    <div class="event-banner-overlay">
      <span class="event-mini-badge badge-<?php echo str_replace(' ', '', $event['status']); ?>"><?php echo safeOut($event['status']); ?></span>
      <h1><?php echo safeOut($event['title']); ?></h1>
      <p><?php echo safeOut($event['sport_name'] ?? '—'); ?> &middot; <?php echo safeOut($event['venue'] ?? 'Venue TBA'); ?></p>
    </div>
  </div>

  <div class="grid-2col">
    <div class="panel">
      <div class="panel-head"><h2>Description</h2></div>
      <p><?php echo $event['description'] ? nl2br(safeOut($event['description'])) : '<span class="text-muted">No description provided.</span>'; ?></p>
      <table class="table table-borderless mt-3">
        <tbody>
          <tr><th style="width:160px;">Coach</th><td><?php echo safeOut($event['coach_name'] ?? 'TBA'); ?></td></tr>
          <tr><th>Date</th><td><?php echo date('M j, Y', strtotime($event['event_date'])); ?><?php echo $event['start_time'] ? ' at ' . date('g:i A', strtotime($event['start_time'])) : ''; ?></td></tr>
          <?php if ($event['registration_deadline']): ?><tr><th>Registration Closes</th><td><?php echo date('M j, Y g:i A', strtotime($event['registration_deadline'])); ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="panel" id="joinPanel">
      <div class="panel-head"><h2>Registration</h2></div>
      <div class="row g-3 text-center mb-3">
        <div class="col-6"><div class="health-item"><div class="health-value"><?php echo $participantCount; ?></div><div class="health-label">Participants</div></div></div>
        <div class="col-6"><div class="health-item"><div class="health-value"><?php echo $remainingSeats ?? '∞'; ?></div><div class="health-label">Seats Remaining</div></div></div>
      </div>

      <?php if ($myRegistration): ?>
        <div class="alert alert-info mb-2">You're registered — status: <strong><?php echo safeOut($myRegistration['participation_status']); ?></strong></div>
        <?php if (in_array($event['status'], ['Upcoming', 'Registration Open']) && (!$event['registration_deadline'] || strtotime($event['registration_deadline']) > time())): ?>
          <button type="button" class="btn btn-outline-danger btn-sm w-100" id="cancelBtn"><i class="fa-solid fa-xmark me-1"></i>Cancel Participation</button>
        <?php endif; ?>
      <?php elseif ($eligibility['eligible']): ?>
        <button type="button" class="btn btn-primary w-100" id="joinBtn"><i class="fa-solid fa-hand-fist me-1"></i>Join Event</button>
      <?php else: ?>
        <div class="alert alert-warning mb-0"><?php echo safeOut($eligibility['reason'] ?? 'Registration is not available.'); ?></div>
      <?php endif; ?>

      <?php if ($scheduleFile): ?>
        <a href="<?php echo UPLOADS_URL . '/' . $scheduleFile; ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100 mt-2">
          <i class="fa-solid fa-file-pdf me-1"></i>Download Schedule
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" id="printScheduleBtn">
          <i class="fa-solid fa-print me-1"></i>Download Schedule
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="printableSchedule" style="display:none;">
  <h2><?php echo safeOut(SITE_NAME); ?></h2>
  <h3><?php echo safeOut($event['title']); ?> — Schedule</h3>
  <p><strong>Sport:</strong> <?php echo safeOut($event['sport_name'] ?? '—'); ?></p>
  <p><strong>Venue:</strong> <?php echo safeOut($event['venue'] ?? 'TBA'); ?></p>
  <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($event['event_date'])); ?><?php echo $event['start_time'] ? ' at ' . date('g:i A', strtotime($event['start_time'])) : ''; ?></p>
</div>

<script>
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
  window.SMS_CSRF_TOKEN = <?php echo json_encode(csrfToken()); ?>;
  window.SMS_EVENT_ID = <?php echo $eventId; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/events.js"></script>
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
