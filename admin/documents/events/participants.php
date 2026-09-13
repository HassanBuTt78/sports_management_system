<?php
/**
 * ============================================================
 * admin/events/participants.php
 * ------------------------------------------------------------
 * Participant Management + View Attendance. Approve/Reject/
 * Remove actions post back to this same file (small, single-
 * purpose forms) rather than a separate AJAX endpoint, since
 * this page's own table needs to re-render immediately after.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$eventId = (int) ($_GET['id'] ?? 0);
if ($eventId <= 0) {
    redirectTo(BASE_URL . '/admin/events/index.php');
}

$event = null;
if ($conn) {
    $stmt = $conn->prepare(
        'SELECT e.*, s.sport_name FROM events e LEFT JOIN sports s ON e.sport_id = s.sport_id WHERE e.event_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$event) {
    redirectTo(BASE_URL . '/admin/events/index.php');
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'danger', 'text' => 'Session expired. Please try again.'];
    } else {
        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $action = cleanInput($_POST['action'] ?? '');

        if ($participantId > 0 && $action === 'remove') {
            $stmt = $conn->prepare('DELETE FROM event_participants WHERE id = ? AND event_id = ?');
            $stmt->bind_param('ii', $participantId, $eventId);
            $stmt->execute();
            $stmt->close();
            $flash = ['type' => 'success', 'text' => 'Participant removed.'];
        } elseif ($participantId > 0 && in_array($action, ['Approved', 'Rejected'], true)) {
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
                $msg = $action === 'Approved'
                    ? "Your registration for \"{$event['title']}\" has been approved."
                    : "Your registration for \"{$event['title']}\" was not approved.";
                notifyUser($conn, 'player', (int) $playerId, $title, $msg);
            }
            $flash = ['type' => 'success', 'text' => "Participant {$action}."];
        }
    }
}

$participants = [];
if ($conn) {
    $stmt = $conn->prepare(
        'SELECT ep.id, ep.participation_status, ep.registered_at, p.player_id, p.full_name, p.roll_no, p.profile_image, p.email
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
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/events.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Participants — <?php echo safeOut($event['title']); ?></h1><p><?php echo safeOut($event['sport_name']); ?> &middot; <?php echo count($participants); ?> registered</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/events/view.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Event</a>
      </div>

      <?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?>"><?php echo safeOut($flash['text']); ?></div><?php endif; ?>

      <div class="panel">
        <?php if (empty($participants)): ?>
          <p class="text-muted mb-0">No one has registered for this event yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle participants-table" id="participantsTable">
              <thead><tr><th>Photo</th><th>Player</th><th>Roll No</th><th>Email</th><th>Registered</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($participants as $p): ?>
                  <tr>
                    <td><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px; height:32px; border-radius:50%; object-fit:cover;"></td>
                    <td><a href="<?php echo BASE_URL; ?>/admin/player/view.php?id=<?php echo $p['player_id']; ?>"><?php echo safeOut($p['full_name']); ?></a></td>
                    <td><?php echo safeOut($p['roll_no']); ?></td>
                    <td><?php echo safeOut($p['email']); ?></td>
                    <td><?php echo timeAgo($p['registered_at']); ?></td>
                    <td><span class="status-pill status-<?php echo $p['participation_status'] === 'Approved' ? 'active' : ($p['participation_status'] === 'Rejected' ? 'inactive' : ''); ?>" style="<?php echo $p['participation_status'] === 'Pending' ? 'background:#fff3cd;color:#856404;' : ''; ?>"><?php echo safeOut($p['participation_status']); ?></span></td>
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
                      <button type="button" class="table-action-btn remove-participant-btn" data-id="<?php echo $p['id']; ?>" data-name="<?php echo safeOut($p['full_name']); ?>" title="Remove"><i class="fa-solid fa-trash"></i></button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Hidden form used by the Remove button via JS (SweetAlert2 confirm first) -->
      <form method="POST" id="removeParticipantForm" class="d-none">
        <?php echo csrfField(); ?>
        <input type="hidden" name="participant_id" id="removeParticipantId">
        <input type="hidden" name="action" value="remove">
      </form>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/events.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
