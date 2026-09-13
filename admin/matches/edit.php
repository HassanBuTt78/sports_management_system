<?php
/**
 * ============================================================
 * admin/matches/edit.php
 * ------------------------------------------------------------
 * Edit an existing match.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$matchId = (int) ($_GET['id'] ?? 0);
if ($matchId <= 0) {
    redirectTo(BASE_URL . '/admin/matches/index.php');
}

$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];
$events = $conn ? $conn->query('SELECT event_id, title FROM events ORDER BY event_date DESC')->fetch_all(MYSQLI_ASSOC) : [];

$match = null;
if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM matches WHERE match_id = ? LIMIT 1');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$match) {
    redirectTo(BASE_URL . '/admin/matches/index.php');
}

$old = $match;
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }
    foreach (['match_title','sport_id','event_id','team_one','team_two','coach_id','venue','referee','match_date','match_time','status'] as $key) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
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

        $stmt = $conn->prepare(
            'UPDATE matches SET match_title=?, sport_id=?, event_id=?, coach_id=?, team_one=?, team_two=?, venue=?, referee=?, match_date=?, match_time=?, status=?
             WHERE match_id = ?'
        );
        $stmt->bind_param(
            'siiiiisssssi',
            $matchTitle, $sportId, $eventId, $coachId, $teamOne, $teamTwo, $old['venue'], $referee, $old['match_date'], $matchTime, $old['status'], $matchId
        );

        if ($stmt->execute()) {
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Updated match #{$matchId}");
            $saved = true;
            $match = array_merge($match, $old);
            $old = $match;
        } else {
            $stmt->close();
            $errors[] = 'Could not update the match.';
        }
    }
}

$pageTitle = 'Edit Match';
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
        <div><h1>Edit Match</h1></div>
        <a href="<?php echo BASE_URL; ?>/admin/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
      </div>

      <?php if ($saved): ?><div class="alert alert-success">Match updated.</div><?php endif; ?>
      <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <div class="panel">
        <form method="POST" action="edit.php?id=<?php echo $matchId; ?>" class="needs-validation" novalidate>
          <?php echo csrfField(); ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Match Title</label>
              <input type="text" name="match_title" class="form-control" value="<?php echo safeOut((string) $old['match_title']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Linked Event</label>
              <select name="event_id" class="form-select">
                <option value="">None</option>
                <?php foreach ($events as $e): ?><option value="<?php echo $e['event_id']; ?>" <?php echo $old['event_id'] == $e['event_id'] ? 'selected' : ''; ?>><?php echo safeOut($e['title']); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Sport *</label>
              <select name="sport_id" id="sport_id" class="form-select" required>
                <?php foreach ($sports as $s): ?><option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Team One *</label>
              <select name="team_one" id="team_one" class="form-select" data-current="<?php echo $old['team_one']; ?>" required><option value="">Loading...</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Team Two</label>
              <select name="team_two" id="team_two" class="form-select" data-current="<?php echo $old['team_two']; ?>"><option value="">Loading...</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Assign Coach</label>
              <select name="coach_id" id="coach_id" class="form-select" data-current="<?php echo $old['coach_id']; ?>"><option value="">Loading...</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Venue</label>
              <input type="text" name="venue" class="form-control" value="<?php echo safeOut((string) $old['venue']); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Referee</label>
              <input type="text" name="referee" class="form-control" value="<?php echo safeOut((string) $old['referee']); ?>">
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
              <input type="date" name="match_date" class="form-control" value="<?php echo safeOut(date('Y-m-d', strtotime($old['match_date']))); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Match Time</label>
              <input type="time" name="match_time" class="form-control" value="<?php echo safeOut((string) $old['match_time']); ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-auth-submit mt-4" style="width:auto; padding-left:28px; padding-right:28px;"><i class="fa-solid fa-floppy-disk me-2"></i>Save Changes</button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/matches.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
