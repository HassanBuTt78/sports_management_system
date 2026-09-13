<?php
/**
 * ============================================================
 * admin/events/edit.php
 * ------------------------------------------------------------
 * Edit an existing event. Notifies registered participants and
 * the assigned coach that the event was updated.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$eventId = (int) ($_GET['id'] ?? 0);
if ($eventId <= 0) {
    redirectTo(BASE_URL . '/admin/events/index.php');
}

$errors = [];
$saved = false;
$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];

$event = null;
if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM events WHERE event_id = ? LIMIT 1');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$event) {
    redirectTo(BASE_URL . '/admin/events/index.php');
}

// datetime-local inputs need "Y-m-d\TH:i" formatting.
$old = $event;
$old['registration_deadline'] = $event['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($event['registration_deadline'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    foreach (['title','sport_id','organizer','coach_id','venue','description','event_date','end_date',
              'start_time','end_time','registration_deadline','max_participants','status'] as $key) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
    }

    if ($old['title'] === '') $errors[] = 'Event title is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if ($old['event_date'] === '') $errors[] = 'Start date is required.';
    if (!in_array($old['status'], ['Upcoming','Registration Open','Registration Closed','Running','Completed','Cancelled'], true)) {
        $old['status'] = 'Upcoming';
    }

    $coachId = null;
    if ($old['coach_id'] !== '' && ctype_digit($old['coach_id'])) {
        $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
        $stmt->bind_param('i', $old['coach_id']);
        $stmt->execute();
        $coachRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$coachRow || (int) $coachRow['sport_id'] !== (int) $old['sport_id']) {
            $errors[] = 'The selected coach does not belong to the selected sport.';
        } else {
            $coachId = (int) $old['coach_id'];
        }
    }

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

        $sportId = (int) $old['sport_id'];
        $description = $old['description'] !== '' ? $old['description'] : null;
        $endDate = $old['end_date'] !== '' ? $old['end_date'] : null;
        $startTime = $old['start_time'] !== '' ? $old['start_time'] : null;
        $endTime = $old['end_time'] !== '' ? $old['end_time'] : null;
        $regDeadline = $old['registration_deadline'] !== '' ? str_replace('T', ' ', $old['registration_deadline']) : null;
        $maxParticipants = ($old['max_participants'] !== '' && ctype_digit($old['max_participants'])) ? (int) $old['max_participants'] : null;

        $stmt = $conn->prepare(
            'UPDATE events SET sport_id=?, coach_id=?, organizer=?, title=?, description=?, venue=?, event_date=?,
             end_date=?, start_time=?, end_time=?, banner_image=?, status=?, registration_deadline=?, max_participants=?
             WHERE event_id = ?'
        );
        $stmt->bind_param(
            'iisssssssssssii',
            $sportId, $coachId, $old['organizer'], $old['title'], $description, $old['venue'], $old['event_date'],
            $endDate, $startTime, $endTime, $bannerPath, $old['status'], $regDeadline, $maxParticipants, $eventId
        );

        if ($stmt->execute()) {
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Updated event: {$old['title']} (#{$eventId})");

            notifyUser($conn, 'player', null, 'Event Updated: ' . $old['title'], 'Details for this event have changed — check the latest information.');
            if ($coachId) {
                notifyUser($conn, 'coach', $coachId, 'Event Updated', "\"{$old['title']}\" has been updated.");
            }

            $saved = true;
            $event = array_merge($event, $old, ['banner_image' => $bannerPath]);
            $old = $event;
            $old['registration_deadline'] = $regDeadline ? date('Y-m-d\TH:i', strtotime($regDeadline)) : '';
        } else {
            $stmt->close();
            $errors[] = 'Could not update the event. Please try again.';
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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Edit Event</h1><p>Update <?php echo safeOut($event['title']); ?>.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Events</a>
      </div>

      <?php if ($saved): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Event updated and participants notified.</div><?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <div class="panel">
        <form method="POST" action="edit.php?id=<?php echo $eventId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
          <?php echo csrfField(); ?>
          <div class="row g-4">

            <div class="col-lg-3 text-center">
              <label class="form-label fw-semibold d-block">Event Banner</label>
              <div class="image-upload-preview event-banner-preview" id="imagePreviewWrap">
                <img id="imagePreview" src="<?php echo $event['banner_image'] ? UPLOADS_URL . '/' . $event['banner_image'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="Preview">
              </div>
              <input type="file" class="form-control mt-2" name="banner_image" id="profile_image" accept=".jpg,.jpeg,.png">
              <div class="form-text">Leave blank to keep current banner. Max 2MB.</div>
            </div>

            <div class="col-lg-9">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label fw-semibold">Event Title *</label>
                  <input type="text" name="title" class="form-control" value="<?php echo safeOut($old['title']); ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Event ID</label>
                  <input type="text" class="form-control" value="<?php echo safeOut(formatEmployeeId('EVT', $eventId)); ?>" disabled>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Sport *</label>
                  <select name="sport_id" id="sport_id" class="form-select" required>
                    <?php foreach ($sports as $s): ?>
                      <option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Coach</label>
                  <select name="coach_id" id="coach_id" class="form-select" data-current="<?php echo safeOut((string) $old['coach_id']); ?>">
                    <option value="">Loading...</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Organizer</label>
                  <input type="text" name="organizer" class="form-control" value="<?php echo safeOut((string) $old['organizer']); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Venue</label>
                  <input type="text" name="venue" class="form-control" value="<?php echo safeOut((string) $old['venue']); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Status</label>
                  <select name="status" class="form-select">
                    <?php foreach (['Upcoming','Registration Open','Registration Closed','Running','Completed','Cancelled'] as $st): ?>
                      <option value="<?php echo $st; ?>" <?php echo $old['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                  </select>
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

          <button type="submit" class="btn btn-auth-submit mt-4" style="width:auto; padding-left:28px; padding-right:28px;">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
          </button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/events.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
