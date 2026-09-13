<?php
/**
 * ============================================================
 * coach/events/participants.php
 * ------------------------------------------------------------
 * "View registered players. Approve participation if required."
 * Coaches can view participants for ANY event of their sport
 * (so they know who's showing up), but can only Approve/Reject
 * on events they personally created — enforced with the same
 * ownership check pattern as edit.php.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$eventId = (int) ($_GET['id'] ?? 0);

$event = null;
if ($conn && $eventId > 0) {
    $stmt = $conn->prepare('SELECT * FROM events WHERE event_id = ? AND sport_id = ? LIMIT 1');
    $stmt->bind_param('ii', $eventId, $mySportId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$event) {
    redirectTo(BASE_URL . '/coach/events/index.php');
}
$canManage = (int) $event['created_by_id'] === $coachId && $event['created_by_role'] === 'coach';

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && $canManage) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'danger', 'text' => 'Session expired.'];
    } else {
        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $action = cleanInput($_POST['action'] ?? '');
        if ($participantId > 0 && in_array($action, ['Approved', 'Rejected'], true)) {
            $stmt = $conn->prepare('UPDATE event_participants SET participation_status = ? WHERE id = ? AND event_id = ?');
            $stmt->bind_param('sii', $action, $participantId, $eventId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('SELECT player_id FROM event_participants WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $participantId);
            $stmt->execute();
            $playerId = $stmt->get_result()->fetch_assoc()['player_id'] ?? null;
            $stmt->close();
            if ($playerId) {
                $title = $action === 'Approved' ? 'Registration Approved' : 'Registration Rejected';
                notifyUser($conn, 'player', (int) $playerId, $title, "Your registration for \"{$event['title']}\" was {$action}.");
            }
            $flash = ['type' => 'success', 'text' => "Participant {$action}."];
        }
    }
}

$participants = [];
if ($conn) {
    $stmt = $conn->prepare(
        'SELECT ep.id, ep.participation_status, ep.registered_at, p.player_id, p.full_name, p.roll_no, p.profile_image
         FROM event_participants ep JOIN players p ON ep.player_id = p.player_id
         WHERE ep.event_id = ? ORDER BY ep.registered_at DESC'
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $participants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Event Participants';
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
    <div><h1>Participants — <?php echo safeOut($event['title']); ?></h1><p class="text-muted"><?php echo count($participants); ?> registered<?php echo !$canManage ? ' (view only — not your event)' : ''; ?></p></div>
    <a href="<?php echo BASE_URL; ?>/coach/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?>"><?php echo safeOut($flash['text']); ?></div><?php endif; ?>

  <div class="panel">
    <?php if (empty($participants)): ?>
      <p class="text-muted mb-0">No one has registered yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Photo</th><th>Player</th><th>Roll No</th><th>Registered</th><th>Status</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($participants as $p): ?>
              <tr>
                <td><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px; height:32px; border-radius:50%; object-fit:cover;"></td>
                <td><?php echo safeOut($p['full_name']); ?></td>
                <td><?php echo safeOut($p['roll_no']); ?></td>
                <td><?php echo timeAgo($p['registered_at']); ?></td>
                <td><span class="status-pill" style="<?php echo $p['participation_status'] === 'Approved' ? 'background:#e7f7ee;color:#198754;' : ($p['participation_status'] === 'Rejected' ? 'background:#fdecea;color:#c1443c;' : 'background:#fff3cd;color:#856404;'); ?>"><?php echo safeOut($p['participation_status']); ?></span></td>
                <?php if ($canManage): ?>
                <td class="text-nowrap">
                  <?php if ($p['participation_status'] !== 'Approved'): ?>
                    <form method="POST" class="d-inline">
                      <?php echo csrfField(); ?>
                      <input type="hidden" name="participant_id" value="<?php echo $p['id']; ?>">
                      <input type="hidden" name="action" value="Approved">
                      <button type="submit" class="table-action-btn" title="Approve"><i class="fa-solid fa-check text-success"></i></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($p['participation_status'] !== 'Rejected'): ?>
                    <form method="POST" class="d-inline">
                      <?php echo csrfField(); ?>
                      <input type="hidden" name="participant_id" value="<?php echo $p['id']; ?>">
                      <input type="hidden" name="action" value="Rejected">
                      <button type="submit" class="table-action-btn" title="Reject"><i class="fa-solid fa-xmark text-danger"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
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
