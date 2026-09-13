<?php
/**
 * ============================================================
 * coach/events/create.php
 * ------------------------------------------------------------
 * "Coach can create events only for their assigned sport" —
 * sport_id is never taken from the form, it's pulled straight
 * from the coach's own session, so there's no field to tamper
 * with. Saved with is_approved = 0 — "Approve Coach Events" is
 * an admin-only action (see admin/events/delete.php's 'approve'
 * case), so the event stays pending until then.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$mySportName = null;
if ($conn && $mySportId) {
    $stmt = $conn->prepare('SELECT sport_name FROM sports WHERE sport_id = ? LIMIT 1');
    $stmt->bind_param('i', $mySportId);
    $stmt->execute();
    $mySportName = $stmt->get_result()->fetch_assoc()['sport_name'] ?? null;
    $stmt->close();
}

$errors = [];
$success = null;
$old = ['title' => '', 'venue' => '', 'description' => '', 'event_date' => '', 'end_date' => '',
        'start_time' => '', 'end_time' => '', 'registration_deadline' => '', 'max_participants' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }
    foreach ($old as $key => $default) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? $default));
    }

    if ($old['title'] === '') $errors[] = 'Event title is required.';
    if ($old['event_date'] === '') $errors[] = 'Start date is required.';

    $bannerCheck = validateUploadedImage($_FILES['banner_image'] ?? []);
    if (!$bannerCheck['valid']) $errors[] = $bannerCheck['error'];

    if (empty($errors)) {
        $bannerResult = storeUploadedImage($_FILES['banner_image'], 'events');
        $description = $old['description'] !== '' ? $old['description'] : null;
        $endDate = $old['end_date'] !== '' ? $old['end_date'] : null;
        $startTime = $old['start_time'] !== '' ? $old['start_time'] : null;
        $endTime = $old['end_time'] !== '' ? $old['end_time'] : null;
        $regDeadline = $old['registration_deadline'] !== '' ? str_replace('T', ' ', $old['registration_deadline']) : null;
        $maxParticipants = ($old['max_participants'] !== '' && ctype_digit($old['max_participants'])) ? (int) $old['max_participants'] : null;
        $organizer = COLLEGE_NAME;
        $status = 'Upcoming';
        $createdByRole = 'coach';

        $stmt = $conn->prepare(
            'INSERT INTO events
             (sport_id, coach_id, organizer, title, description, venue, event_date, end_date, start_time, end_time,
              banner_image, status, registration_deadline, max_participants, created_by_role, created_by_id, is_approved)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)'
        );
        $stmt->bind_param(
            'iisssssssssssisi',
            $mySportId, $coachId, $organizer, $old['title'], $description, $old['venue'], $old['event_date'], $endDate,
            $startTime, $endTime, $bannerResult['path'], $status, $regDeadline, $maxParticipants, $createdByRole, $coachId
        );

        if ($stmt->execute()) {
            $newEventId = $stmt->insert_id;
            $stmt->close();
            logActivity($conn, 'coach', $coachId, "Created event (pending approval): {$old['title']}");
            notifyUser($conn, 'admin', null, 'Event Awaiting Approval', "A coach created \"{$old['title']}\" and it needs your approval.");

            $success = ['event_id' => $newEventId, 'title' => $old['title']];
            $old = array_fill_keys(array_keys($old), '');
        } else {
            $stmt->close();
            $errors[] = 'Could not save the event. Please try again.';
        }
    }
}

$pageTitle = 'Create Event';
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
    <div><h1>Create Event</h1><p class="text-muted">For <?php echo safeOut($mySportName ?? 'your sport'); ?> only. New events need admin approval before they go live.</p></div>
    <a href="<?php echo BASE_URL; ?>/coach/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Event <strong><?php echo safeOut($success['title']); ?></strong> submitted — the Administrator has been notified for approval.</div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="panel">
    <form method="POST" action="create.php" enctype="multipart/form-data" class="needs-validation" novalidate>
      <?php echo csrfField(); ?>
      <div class="row g-4">
        <div class="col-lg-3 text-center">
          <label class="form-label fw-semibold d-block">Event Banner</label>
          <div class="image-upload-preview event-banner-preview" id="imagePreviewWrap">
            <img id="imagePreview" src="<?php echo ASSETS_URL; ?>/images/logo.svg" alt="Preview">
          </div>
          <input type="file" class="form-control mt-2" name="banner_image" id="profile_image" accept=".jpg,.jpeg,.png">
          <div class="form-text">Max 2MB.</div>
        </div>
        <div class="col-lg-9">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Event Title *</label>
              <input type="text" name="title" class="form-control" value="<?php echo safeOut($old['title']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Sport</label>
              <input type="text" class="form-control" value="<?php echo safeOut($mySportName ?? '—'); ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Venue</label>
              <input type="text" name="venue" class="form-control" value="<?php echo safeOut($old['venue']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Maximum Participants</label>
              <input type="number" min="1" name="max_participants" class="form-control" placeholder="Unlimited" value="<?php echo safeOut($old['max_participants']); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Start Date *</label>
              <input type="date" name="event_date" class="form-control" value="<?php echo safeOut($old['event_date']); ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">End Date</label>
              <input type="date" name="end_date" class="form-control" value="<?php echo safeOut($old['end_date']); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Start Time</label>
              <input type="time" name="start_time" class="form-control" value="<?php echo safeOut($old['start_time']); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">End Time</label>
              <input type="time" name="end_time" class="form-control" value="<?php echo safeOut($old['end_time']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Registration Deadline</label>
              <input type="datetime-local" name="registration_deadline" class="form-control" value="<?php echo safeOut($old['registration_deadline']); ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="3"><?php echo safeOut($old['description']); ?></textarea>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk me-2"></i>Submit for Approval</button>
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
