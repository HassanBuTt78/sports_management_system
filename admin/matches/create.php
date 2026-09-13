<?php
/**
 * ============================================================
 * admin/matches/create.php
 * ------------------------------------------------------------
 * Create Match. Team One / Team Two / Coach dropdowns reuse the
 * existing admin/player/ajax_get_teams.php and ajax_get_coaches.php
 * endpoints (Module 5) — no new endpoints needed. Team Two is
 * optional (a walkover or forfeit doesn't need a second team).
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$success = null;
$old = ['match_title' => '', 'sport_id' => '', 'event_id' => '', 'team_one' => '', 'team_two' => '', 'coach_id' => '',
        'venue' => '', 'referee' => '', 'match_date' => '', 'match_time' => '', 'status' => 'Upcoming'];

$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];
$events = $conn ? $conn->query("SELECT event_id, title FROM events WHERE status NOT IN ('Completed','Cancelled') ORDER BY event_date DESC")->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }
    foreach ($old as $key => $default) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? $default));
    }

    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if ($old['team_one'] === '' || !ctype_digit($old['team_one'])) $errors[] = 'Please select Team One.';
    if ($old['team_two'] !== '' && $old['team_two'] === $old['team_one']) $errors[] = 'Team Two must be different from Team One.';
    if ($old['match_date'] === '') $errors[] = 'Match date is required.';
    if (!in_array($old['status'], ['Upcoming','Completed','Cancelled'], true)) $old['status'] = 'Upcoming';

    if (empty($errors)) {
        $sportId = (int) $old['sport_id'];
        $teamOne = (int) $old['team_one'];
        $teamTwo = ($old['team_two'] !== '' && ctype_digit($old['team_two'])) ? (int) $old['team_two'] : null;
        $eventId = ($old['event_id'] !== '' && ctype_digit($old['event_id'])) ? (int) $old['event_id'] : null;
        $coachId = ($old['coach_id'] !== '' && ctype_digit($old['coach_id'])) ? (int) $old['coach_id'] : null;
        $matchTitle = $old['match_title'] !== '' ? $old['match_title'] : null;
        $referee = $old['referee'] !== '' ? $old['referee'] : null;
        $matchTime = $old['match_time'] !== '' ? $old['match_time'] : null;
        $createdByRole = 'admin';
        $createdById = (int) $_SESSION['user_id'];

        $stmt = $conn->prepare(
            'INSERT INTO matches (match_title, sport_id, event_id, coach_id, team_one, team_two, venue, referee, match_date, match_time, status, created_by_role, created_by_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'siiiiissssssi',
            $matchTitle, $sportId, $eventId, $coachId, $teamOne, $teamTwo, $old['venue'], $referee, $old['match_date'], $matchTime, $old['status'], $createdByRole, $createdById
        );

        if ($stmt->execute()) {
            $newMatchId = $stmt->insert_id;
            $stmt->close();
            logActivity($conn, 'admin', $createdById, 'Created match #' . $newMatchId);

            // Notify both teams' coaches, plus the explicitly assigned coach.
            $notified = [];
            foreach (array_filter([$teamOne, $teamTwo]) as $tid) {
                $stmt2 = $conn->prepare('SELECT coach_id FROM teams WHERE team_id = ? LIMIT 1');
                $stmt2->bind_param('i', $tid);
                $stmt2->execute();
                $cid = $stmt2->get_result()->fetch_assoc()['coach_id'] ?? null;
                $stmt2->close();
                if ($cid && !in_array((int) $cid, $notified, true)) {
                    notifyUser($conn, 'coach', (int) $cid, 'New Match Scheduled', 'A new match has been scheduled for your team.');
                    $notified[] = (int) $cid;
                }
            }
            if ($coachId && !in_array($coachId, $notified, true)) {
                notifyUser($conn, 'coach', $coachId, 'New Match Assigned', 'You have been assigned to oversee a new match.');
            }

            $success = ['match_id' => $newMatchId];
            $old = array_fill_keys(array_keys($old), '');
            $old['status'] = 'Upcoming';
        } else {
            $stmt->close();
            $errors[] = 'Could not save the match. Please try again.';
        }
    }
}

$pageTitle = 'Create Match';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/matches.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Create Match</h1><p>Set up a fixture between two teams (or a solo heat for individual sports).</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check me-1"></i>Match created.
          <a href="<?php echo BASE_URL; ?>/admin/matches/view.php?id=<?php echo $success['match_id']; ?>">View Match &rarr;</a>
        </div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <div class="panel">
        <form method="POST" action="create.php" class="needs-validation" novalidate>
          <?php echo csrfField(); ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Match Title</label>
              <input type="text" name="match_title" class="form-control" placeholder="e.g. Semi-Final" value="<?php echo safeOut($old['match_title']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Linked Event (optional)</label>
              <select name="event_id" class="form-select">
                <option value="">None</option>
                <?php foreach ($events as $e): ?><option value="<?php echo $e['event_id']; ?>" <?php echo $old['event_id'] == $e['event_id'] ? 'selected' : ''; ?>><?php echo safeOut($e['title']); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Sport *</label>
              <select name="sport_id" id="sport_id" class="form-select" required>
                <option value="">Select Sport</option>
                <?php foreach ($sports as $s): ?><option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Team One (Assign Teams) *</label>
              <select name="team_one" id="team_one" class="form-select" required><option value="">Select Sport First</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Team Two <span class="text-muted small">(optional — individual sports)</span></label>
              <select name="team_two" id="team_two" class="form-select"><option value="">Select Sport First</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Assign Coach</label>
              <select name="coach_id" id="coach_id" class="form-select"><option value="">Select Sport First</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Assign Venue</label>
              <input type="text" name="venue" class="form-control" value="<?php echo safeOut($old['venue']); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Referee</label>
              <input type="text" name="referee" class="form-control" value="<?php echo safeOut($old['referee']); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Match Status</label>
              <select name="status" class="form-select">
                <?php foreach (['Upcoming' => 'Scheduled', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'] as $val => $label): ?>
                  <option value="<?php echo $val; ?>" <?php echo $old['status'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Match Date *</label>
              <input type="date" name="match_date" class="form-control" value="<?php echo safeOut($old['match_date']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Match Time</label>
              <input type="time" name="match_time" class="form-control" value="<?php echo safeOut($old['match_time']); ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-auth-submit mt-4" style="width:auto; padding-left:28px; padding-right:28px;"><i class="fa-solid fa-floppy-disk me-2"></i>Save Match</button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/matches.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
