<?php
/**
 * ============================================================
 * admin/events/create.php
 * ------------------------------------------------------------
 * Create Event (admin). Admin-created events are approved by
 * default (is_approved = 1, created_by_role = 'admin') — the
 * approval workflow (Feature: "Approve Coach Events") only
 * applies to coach-created events. Notifies coach + broadcasts
 * to players of the event's sport once saved.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$success = null;
$old = ['title' => '', 'sport_id' => '', 'organizer' => '', 'coach_id' => '', 'venue' => '', 'description' => '',
        'event_date' => '', 'end_date' => '', 'start_time' => '', 'end_time' => '', 'registration_deadline' => '',
        'max_participants' => '', 'status' => 'Upcoming'];

$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    foreach ($old as $key => $default) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? $default));
    }

    if ($old['title'] === '') $errors[] = 'Event title is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if ($old['event_date'] === '') $errors[] = 'Start date is required.';
    if ($old['end_date'] !== '' && $old['event_date'] !== '' && strtotime($old['end_date']) < strtotime($old['event_date'])) {
        $errors[] = 'End date cannot be before the start date.';
    }
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
        $bannerResult = storeUploadedImage($_FILES['banner_image'], 'events');
        $sportId = (int) $old['sport_id'];
        $organizer = $old['organizer'] !== '' ? $old['organizer'] : COLLEGE_NAME;
        $description = $old['description'] !== '' ? $old['description'] : null;
        $endDate = $old['end_date'] !== '' ? $old['end_date'] : null;
        $startTime = $old['start_time'] !== '' ? $old['start_time'] : null;
        $endTime = $old['end_time'] !== '' ? $old['end_time'] : null;
        $regDeadline = $old['registration_deadline'] !== '' ? $old['registration_deadline'] : null;
        $maxParticipants = ($old['max_participants'] !== '' && ctype_digit($old['max_participants'])) ? (int) $old['max_participants'] : null;
        $createdByRole = 'admin';
        $createdById = (int) $_SESSION['user_id'];

        $stmt = $conn->prepare(
            'INSERT INTO events
             (sport_id, coach_id, organizer, title, description, venue, event_date, end_date, start_time, end_time,
              banner_image, status, registration_deadline, max_participants, created_by_role, created_by_id, is_approved)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
        );
        $stmt->bind_param(
            'iisssssssssssisi',
            $sportId, $coachId, $organizer, $old['title'], $description, $old['venue'], $old['event_date'], $endDate,
            $startTime, $endTime, $bannerResult['path'], $old['status'], $regDeadline, $maxParticipants,
            $createdByRole, $createdById
        );

        if ($stmt->execute()) {
            $newEventId = $stmt->insert_id;
            $stmt->close();
            logActivity($conn, 'admin', $createdById, "Created event: {$old['title']}");

            // Notify the assigned coach + broadcast to players of this sport.
            if ($coachId) {
                notifyUser($conn, 'coach', $coachId, 'New Event Assigned', "You've been assigned to \"{$old['title']}\".");
            }
            notifyUser($conn, 'player', null, 'New Event: ' . $old['title'], "A new {$old['status']} event has been posted for your sport.");

            $success = ['event_id' => $newEventId, 'title' => $old['title']];
            $old = array_fill_keys(array_keys($old), '');
            $old['status'] = 'Upcoming';
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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Create Event</h1><p>Set up a new event and notify the relevant sport's players.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Events</a>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check me-1"></i>
          Event <strong><?php echo safeOut($success['title']); ?></strong> created and players notified.
          <a href="<?php echo BASE_URL; ?>/admin/events/view.php?id=<?php echo $success['event_id']; ?>">View Event &rarr;</a>
        </div>
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
              <div class="form-text">JPG, JPEG, or PNG. Max 2MB.</div>
            </div>

            <div class="col-lg-9">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label fw-semibold">Event Title *</label>
                  <input type="text" name="title" class="form-control" value="<?php echo safeOut($old['title']); ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Event ID</label>
                  <input type="text" class="form-control" value="Auto-generated after saving (e.g. EVT-0001)" disabled>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Sport *</label>
                  <select name="sport_id" id="sport_id" class="form-select" required>
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $s): ?>
                      <option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Coach</label>
                  <select name="coach_id" id="coach_id" class="form-select">
                    <option value="">Select Sport First</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Organizer</label>
                  <input type="text" name="organizer" class="form-control" placeholder="<?php echo safeOut(COLLEGE_NAME); ?>" value="<?php echo safeOut($old['organizer']); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Venue</label>
                  <input type="text" name="venue" class="form-control" value="<?php echo safeOut($old['venue']); ?>">
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
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Maximum Participants</label>
                  <input type="number" min="1" name="max_participants" class="form-control" placeholder="Leave blank for unlimited" value="<?php echo safeOut($old['max_participants']); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Description</label>
                  <textarea name="description" class="form-control" rows="3"><?php echo safeOut($old['description']); ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-auth-submit mt-4" style="width:auto; padding-left:28px; padding-right:28px;">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Event
          </button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/events.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
