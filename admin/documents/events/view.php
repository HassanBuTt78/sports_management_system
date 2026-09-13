<?php
/**
 * ============================================================
 * admin/events/view.php
 * ------------------------------------------------------------
 * Event Details Page (admin). Also handles Event Gallery
 * uploads (image/video/PDF schedule/rules document) inline —
 * the brief doesn't list a separate gallery file, so it lives
 * here alongside the rest of the event's details.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$eventId = (int) ($_GET['id'] ?? 0);
$event = null;
$media = [];
$uploadError = null;
$uploadSuccess = false;

if ($conn && $eventId > 0) {
    $stmt = $conn->prepare(
        'SELECT e.*, s.sport_name, c.full_name AS coach_name
         FROM events e LEFT JOIN sports s ON e.sport_id = s.sport_id LEFT JOIN coaches c ON e.coach_id = c.coach_id
         WHERE e.event_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$event) {
    redirectTo(BASE_URL . '/admin/events/index.php');
}

// ---------- Event Gallery upload (image / video / PDF schedule / rules document) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'media_upload' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $uploadError = 'Session expired. Please try again.';
    } else {
        $mediaType = cleanInput($_POST['media_type'] ?? '');
        $file = $_FILES['media_file'] ?? null;

        if (!in_array($mediaType, ['image', 'video', 'pdf_schedule', 'rules_document'], true)) {
            $uploadError = 'Please choose a media type.';
        } elseif (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $uploadError = 'Please choose a file to upload.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $uploadError = 'File must be smaller than 5MB.';
        } else {
            $allowedByType = [
                'image' => ['jpg', 'jpeg', 'png'],
                'video' => ['mp4', 'webm', 'mov'],
                'pdf_schedule' => ['pdf'],
                'rules_document' => ['pdf', 'doc', 'docx'],
            ];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedByType[$mediaType], true)) {
                $uploadError = 'That file type is not allowed for ' . str_replace('_', ' ', $mediaType) . '.';
            } else {
                $filename = 'events_' . $eventId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $targetDir = __DIR__ . '/../../uploads/events/';
                if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
                    $stmt = $conn->prepare(
                        'INSERT INTO event_media (event_id, media_type, file_path, uploaded_by_role, uploaded_by_id) VALUES (?,?,?,?,?)'
                    );
                    $relPath = 'events/' . $filename;
                    $role = 'admin';
                    $uid = (int) $_SESSION['user_id'];
                    $stmt->bind_param('isssi', $eventId, $mediaType, $relPath, $role, $uid);
                    $stmt->execute();
                    $stmt->close();
                    $uploadSuccess = true;
                } else {
                    $uploadError = 'Could not save the uploaded file.';
                }
            }
        }
    }
}

if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM event_media WHERE event_id = ? ORDER BY uploaded_at DESC');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $media = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$participantCount = $conn ? getEventParticipantCount($conn, $eventId) : 0;
$remainingSeats = $event['max_participants'] ? max(0, (int) $event['max_participants'] - $participantCount) : null;

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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Event Details</h1><p><?php echo safeOut(formatEmployeeId('EVT', $eventId)); ?></p></div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/admin/events/edit.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i>Edit</a>
          <a href="<?php echo BASE_URL; ?>/admin/events/participants.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-users me-1"></i>Participants</a>
          <a href="<?php echo BASE_URL; ?>/chat/event_chat.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-comments me-1"></i>Discussion</a>
          <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
      </div>

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
              <tr><th style="width:180px;">Organizer</th><td><?php echo safeOut($event['organizer'] ?? '—'); ?></td></tr>
              <tr><th>Coach</th><td><?php echo safeOut($event['coach_name'] ?? 'Unassigned'); ?></td></tr>
              <tr><th>Start</th><td><?php echo date('M j, Y', strtotime($event['event_date'])); ?><?php echo $event['start_time'] ? ' at ' . date('g:i A', strtotime($event['start_time'])) : ''; ?></td></tr>
              <?php if ($event['end_date']): ?><tr><th>End</th><td><?php echo date('M j, Y', strtotime($event['end_date'])); ?><?php echo $event['end_time'] ? ' at ' . date('g:i A', strtotime($event['end_time'])) : ''; ?></td></tr><?php endif; ?>
              <?php if ($event['registration_deadline']): ?><tr><th>Registration Deadline</th><td><?php echo date('M j, Y g:i A', strtotime($event['registration_deadline'])); ?></td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Participants</h2></div>
          <div class="row g-3 text-center">
            <div class="col-6"><div class="health-item"><div class="health-value"><?php echo $participantCount; ?></div><div class="health-label">Registered</div></div></div>
            <div class="col-6"><div class="health-item"><div class="health-value"><?php echo $remainingSeats ?? '∞'; ?></div><div class="health-label">Seats Remaining</div></div></div>
          </div>
          <a href="<?php echo BASE_URL; ?>/admin/events/participants.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary btn-sm w-100 mt-3">
            <i class="fa-solid fa-users me-1"></i>View / Manage Participants
          </a>
        </div>
      </div>

      <!-- ============ EVENT GALLERY ============ -->
      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-photo-film me-2"></i>Event Gallery &amp; Documents</h2></div>

        <?php if ($uploadSuccess): ?><div class="alert alert-success">File uploaded.</div><?php endif; ?>
        <?php if ($uploadError): ?><div class="alert alert-danger"><?php echo safeOut($uploadError); ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-4">
          <?php echo csrfField(); ?>
          <input type="hidden" name="form_type" value="media_upload">
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Type</label>
            <select name="media_type" class="form-select form-select-sm" required>
              <option value="image">Image</option>
              <option value="video">Video</option>
              <option value="pdf_schedule">PDF Schedule</option>
              <option value="rules_document">Rules Document</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">File</label>
            <input type="file" name="media_file" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-upload me-1"></i>Upload</button>
          </div>
        </form>

        <?php if (empty($media)): ?>
          <p class="text-muted mb-0">No gallery items or documents uploaded yet.</p>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($media as $m): ?>
              <div class="col-md-3 col-6">
                <div class="event-media-card">
                  <?php if ($m['media_type'] === 'image'): ?>
                    <img src="<?php echo UPLOADS_URL . '/' . $m['file_path']; ?>" alt="">
                  <?php else: ?>
                    <div class="event-media-icon"><i class="fa-solid <?php echo $m['media_type'] === 'video' ? 'fa-file-video' : 'fa-file-pdf'; ?>"></i></div>
                  <?php endif; ?>
                  <span><?php echo safeOut(str_replace('_', ' ', ucfirst($m['media_type']))); ?></span>
                  <a href="<?php echo UPLOADS_URL . '/' . $m['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2 w-100">Open</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
