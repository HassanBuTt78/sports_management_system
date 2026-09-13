<?php
/**
 * ============================================================
 * coach/events/edit.php
 * ------------------------------------------------------------
 * "Edit own events" — ownership check (created_by_id = session
 * coach_id AND created_by_role = 'coach') happens in the SELECT
 * itself, not as an afterthought. If it doesn't match, $event is
 * null and the page redirects — a coach can never edit someone
 * else's event, including admin-created ones, by editing a URL.
 * Editing resets is_approved to 0 — a changed event needs to be
 * re-approved, same principle as the initial submission.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$eventId = (int) ($_GET['id'] ?? 0);

$event = null;
if ($conn && $eventId > 0) {
    $stmt = $conn->prepare(
        "SELECT * FROM events WHERE event_id = ? AND created_by_id = ? AND created_by_role = 'coach' LIMIT 1"
    );
    $stmt->bind_param('ii', $eventId, $coachId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$event) {
    redirectTo(BASE_URL . '/coach/events/index.php');
}

$old = $event;
$old['registration_deadline'] = $event['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($event['registration_deadline'])) : '';
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }
    foreach (['title','venue','description','event_date','end_date','start_time','end_time','registration_deadline','max_participants'] as $key) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
    }
    if ($old['title'] === '') $errors[] = 'Event title is required.';
    if ($old['event_date'] === '') $errors[] = 'Start date is required.';

    $bannerCheck = validateUploadedImage($_FILES['banner_image'] ?? []);
    if (!$bannerCheck['valid']) $errors[] = $bannerCheck['error'];

    if (empty($errors)) {
        $bannerPath = $event['banner_image'];
        if (!empty($_FILES['banner_image']['name'])) {
            $bannerResult = storeUploadedImage($_FILES['banner_image'], 'events');
            if ($bannerResult['success'] && $bannerResult['path']) {
                deleteUploadedFile($event['banner_image']);
                $bannerPath = $bannerResult['path'];
            }
        }
        $description = $old['description'] !== '' ? $old['description'] : null;
        $endDate = $old['end_date'] !== '' ? $old['end_date'] : null;
        $startTime = $old['start_time'] !== '' ? $old['start_time'] : null;
        $endTime = $old['end_time'] !== '' ? $old['end_time'] : null;
        $regDeadline = $old['registration_deadline'] !== '' ? str_replace('T', ' ', $old['registration_deadline']) : null;
        $maxParticipants = ($old['max_participants'] !== '' && ctype_digit($old['max_participants'])) ? (int) $old['max_participants'] : null;

        $stmt = $conn->prepare(
            "UPDATE events SET title=?, description=?, venue=?, event_date=?, end_date=?, start_time=?, end_time=?,
             banner_image=?, registration_deadline=?, max_participants=?, is_approved=0
             WHERE event_id = ? AND created_by_id = ? AND created_by_role = 'coach'"
        );
        $stmt->bind_param(
            'sssssssssiii',
            $old['title'], $description, $old['venue'], $old['event_date'], $endDate, $startTime, $endTime,
            $bannerPath, $regDeadline, $maxParticipants, $eventId, $coachId
        );

        if ($stmt->execute()) {
            $stmt->close();
            logActivity($conn, 'coach', $coachId, "Updated own event: {$old['title']} (#{$eventId}) — resubmitted for approval");
            notifyUser($conn, 'admin', null, 'Event Updated — Needs Re-Approval', "\"{$old['title']}\" was edited and needs approval again.");
            $saved = true;
            $event = array_merge($event, $old, ['banner_image' => $bannerPath, 'is_approved' => 0]);
            $old = $event;
            $old['registration_deadline'] = $regDeadline ? date('Y-m-d\TH:i', strtotime($regDeadline)) : '';
        } else {
            $stmt->close();
            $errors[] = 'Could not update the event.';
        }
    }
}

$pageTitle = 'Edit Event';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/player.css">
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
    <div><h1>Edit Event</h1><p class="text-muted">Editing resubmits this event for admin approval.</p></div>
    <a href="<?php echo BASE_URL; ?>/coach/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if ($saved): ?><div class="alert alert-success">Event updated — resubmitted for approval.</div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <div class="panel">
    <form method="POST" action="edit.php?id=<?php echo $eventId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <?php echo csrfField(); ?>
      <div class="row g-4">
        <div class="col-lg-3 text-center">
          <div class="image-upload-preview event-banner-preview" id="imagePreviewWrap">
            <img id="imagePreview" src="<?php echo $event['banner_image'] ? UPLOADS_URL . '/' . $event['banner_image'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="Preview">
          </div>
          <input type="file" class="form-control mt-2" name="banner_image" id="profile_image" accept=".jpg,.jpeg,.png">
        </div>
        <div class="col-lg-9">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Event Title *</label>
              <input type="text" name="title" class="form-control" value="<?php echo safeOut($old['title']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Venue</label>
              <input type="text" name="venue" class="form-control" value="<?php echo safeOut((string) $old['venue']); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Start Date *</label>
              <input type="date" name="event_date" class="form-control" value="<?php echo safeOut(date('Y-m-d', strtotime($old['event_date']))); ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">End Date</label>
              <input type="date" name="end_date" class="form-control" value="<?php echo $old['end_date'] ? safeOut(date('Y-m-d', strtotime($old['end_date']))) : ''; ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Start Time</label>
              <input type="time" name="start_time" class="form-control" value="<?php echo safeOut((string) $old['start_time']); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">End Time</label>
              <input type="time" name="end_time" class="form-control" value="<?php echo safeOut((string) $old['end_time']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Registration Deadline</label>
              <input type="datetime-local" name="registration_deadline" class="form-control" value="<?php echo safeOut($old['registration_deadline']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Maximum Participants</label>
              <input type="number" min="1" name="max_participants" class="form-control" value="<?php echo safeOut((string) $old['max_participants']); ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="3"><?php echo safeOut((string) $old['description']); ?></textarea>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Changes</button>
    </form>
  </div>
</div>
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
