<?php
/**
 * ============================================================
 * coach/matches/results.php
 * ------------------------------------------------------------
 * "Update match results, Declare winner, Upload match highlights,
 * Upload match images, Add match remarks" — all four of the
 * coach's remaining match abilities live on this one page since
 * the brief's file list has no separate files for them. Scoped
 * to the coach's own sport via the match lookup itself.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/performance_engine.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$matchId = (int) ($_GET['id'] ?? 0);

$match = null;
if ($conn && $matchId > 0) {
    $stmt = $conn->prepare(
        'SELECT m.*, ta.team_name AS team_a_name, tb.team_name AS team_b_name
         FROM matches m LEFT JOIN teams ta ON m.team_one = ta.team_id LEFT JOIN teams tb ON m.team_two = tb.team_id
         WHERE m.match_id = ? AND m.sport_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $matchId, $mySportId);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$match) {
    redirectTo(BASE_URL . '/coach/matches/index.php');
}

$teamPlayers = [];
if ($conn) {
    $ids = array_filter([$match['team_one'], $match['team_two']]);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT player_id, full_name FROM players WHERE team_id IN ($placeholders) ORDER BY full_name");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $teamPlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$resultSaved = false;
$remarkSaved = false;
$mediaSaved = false;
$errors = [];
$mediaError = null;

// ---------- Declare winner / result summary ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'result' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Please try again.';
    } else {
        $winnerTeam = cleanInput($_POST['winner_team'] ?? '');
        $summary = cleanInput($_POST['result_summary'] ?? '');
        $winnerId = ($winnerTeam !== '' && ctype_digit($winnerTeam)) ? (int) $winnerTeam : null;
        $summaryVal = $summary !== '' ? $summary : null;

        // ---------- Required-workflow validation (cannot be bypassed) ----------
        // A match may only move to Completed once a final score, at least
        // one participating player's score, and at least one player rating
        // have actually been recorded — otherwise it stays Scheduled and
        // the coach is told exactly what's missing.
        if ($summaryVal === null) {
            $errors[] = 'Please enter the final score / result summary before completing the match.';
        }

        $scoreCount = 0;
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM player_scores WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $scoreCount = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        if ($scoreCount === 0) {
            $errors[] = 'Please enter at least one participating player\'s score (Score Entry) before completing the match.';
        }

        $ratingCount = 0;
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM player_ratings WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $ratingCount = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        if ($ratingCount === 0) {
            $errors[] = 'Please rate at least one participating player (Rate Players) before completing the match.';
        }

        if ($match['status'] === 'Completed') {
            $errors[] = 'This match has already been completed.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE matches SET winner_team=?, result_summary=?, status='Completed' WHERE match_id = ?");
            $stmt->bind_param('isi', $winnerId, $summaryVal, $matchId);
            if ($stmt->execute()) {
                $stmt->close();
                logActivity($conn, 'coach', $coachId, "Declared result for match #{$matchId}");

                // §24 — automatic recalculation whenever a match becomes Completed, no manual step required.
                recalculateAllPerformance($conn);
                notifyUser($conn, 'admin', null, 'Match Result Posted', 'A coach posted a result for a match — review at your convenience.');
                $resultSaved = true;
                $match['winner_team'] = $winnerId;
                $match['result_summary'] = $summaryVal;
                $match['status'] = 'Completed';
            } else {
                $stmt->close();
                $errors[] = 'Could not save the result.';
            }
        }
    }
}

// ---------- Add match remarks ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'remark' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Please try again.';
    } else {
        $remarkText = cleanInput($_POST['remark'] ?? '');
        if ($remarkText === '') {
            $errors[] = 'Please write a remark before saving.';
        } else {
            $stmt = $conn->prepare('INSERT INTO match_remarks (match_id, coach_id, remark) VALUES (?,?,?)');
            $stmt->bind_param('iis', $matchId, $coachId, $remarkText);
            $stmt->execute();
            $stmt->close();
            $remarkSaved = true;
        }
    }
}

// ---------- Upload highlights / images ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'media' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $mediaError = 'Session expired. Please try again.';
    } else {
        $mediaType = cleanInput($_POST['media_type'] ?? '');
        $caption = cleanInput($_POST['caption'] ?? '');
        $allowedByType = ['photo' => ['jpg','jpeg','png'], 'video' => ['mp4','webm','mov']];
        if (!isset($allowedByType[$mediaType])) {
            $mediaError = 'Please choose Photo or Video.';
        } else {
            $fileCheck = validateUploadedFile($_FILES['media_file'] ?? [], $allowedByType[$mediaType], 10 * 1024 * 1024);
            if (!$fileCheck['valid']) {
                $mediaError = $fileCheck['error'];
            } else {
                $result = storeUploadedFile($_FILES['media_file'], 'matches');
                if ($result['success'] && $result['path']) {
                    $role = 'coach';
                    $captionVal = $caption !== '' ? $caption : ($mediaType === 'video' ? 'Match Highlight' : null);
                    $stmt = $conn->prepare('INSERT INTO match_media (match_id, media_type, file_path, caption, uploaded_by_role, uploaded_by_id) VALUES (?,?,?,?,?,?)');
                    $stmt->bind_param('issssi', $matchId, $mediaType, $result['path'], $captionVal, $role, $coachId);
                    $stmt->execute();
                    $stmt->close();
                    $mediaSaved = true;
                } else {
                    $mediaError = 'Could not save the uploaded file.';
                }
            }
        }
    }
}

$remarks = [];
$media = [];
if ($conn) {
    $stmt = $conn->prepare('SELECT r.remark, r.created_at, c.full_name FROM match_remarks r JOIN coaches c ON r.coach_id = c.coach_id WHERE r.match_id = ? ORDER BY r.created_at DESC');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $remarks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM match_media WHERE match_id = ? AND media_type IN ('photo','video') ORDER BY uploaded_at DESC");
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $media = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Match Results';
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
    <div><h1>Match Results</h1><p class="text-muted"><?php echo safeOut($match['team_a_name'] ?? 'TBD') . ($match['team_b_name'] ? ' vs ' . safeOut($match['team_b_name']) : ''); ?></p></div>
    <a href="<?php echo BASE_URL; ?>/coach/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <?php if ($match['status'] !== 'Completed'): ?>
    <div class="alert alert-info">
      <i class="fa-solid fa-list-check me-1"></i>
      Before this match can be marked Completed, make sure you've: entered at least one player's score on
      <a href="<?php echo BASE_URL; ?>/coach/matches/score_entry.php?id=<?php echo $matchId; ?>">Score Entry</a>,
      rated at least one player on
      <a href="<?php echo BASE_URL; ?>/coach/matches/player_rating.php?id=<?php echo $matchId; ?>">Rate Players</a>,
      and filled in the Result Summary below.
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2>Declare Winner &amp; Result</h2></div>
    <?php if ($resultSaved): ?><div class="alert alert-success">Result saved.</div><?php endif; ?>
    <form method="POST" class="row g-3">
      <?php echo csrfField(); ?>
      <input type="hidden" name="form_type" value="result">
      <?php $isCompleted = $match['status'] === 'Completed'; ?>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Winner Team</label>
        <select name="winner_team" class="form-select" <?php echo $isCompleted ? 'disabled' : ''; ?>>
          <option value="">— None —</option>
          <?php if ($match['team_one']): ?><option value="<?php echo $match['team_one']; ?>" <?php echo (int) $match['winner_team'] === (int) $match['team_one'] ? 'selected' : ''; ?>><?php echo safeOut($match['team_a_name']); ?></option><?php endif; ?>
          <?php if ($match['team_two']): ?><option value="<?php echo $match['team_two']; ?>" <?php echo (int) $match['winner_team'] === (int) $match['team_two'] ? 'selected' : ''; ?>><?php echo safeOut($match['team_b_name']); ?></option><?php endif; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Status</label>
        <input type="text" class="form-control" value="<?php echo $match['status'] === 'Upcoming' ? 'Scheduled' : safeOut($match['status']); ?>" disabled>
      </div>
      <div class="col-12">
        <label class="form-label fw-semibold">Result Summary</label>
        <textarea name="result_summary" class="form-control" rows="2" <?php echo $isCompleted ? 'disabled' : ''; ?>><?php echo safeOut((string) $match['result_summary']); ?></textarea>
      </div>
      <div class="col-12">
        <?php if ($isCompleted): ?>
          <span class="text-muted"><i class="fa-solid fa-circle-check text-success me-1"></i>This match has already been completed and can't be re-completed.</span>
        <?php else: ?>
          <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to complete this match? This cannot be undone.');">Save Result &amp; Mark Completed</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Match Remarks</h2></div>
    <?php if ($remarkSaved): ?><div class="alert alert-success">Remark added.</div><?php endif; ?>
    <form method="POST" class="mb-3">
      <?php echo csrfField(); ?>
      <input type="hidden" name="form_type" value="remark">
      <textarea name="remark" class="form-control mb-2" rows="2" placeholder="Add a note about this match..."></textarea>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Add Remark</button>
    </form>
    <?php foreach ($remarks as $r): ?>
      <div class="mb-2 pb-2 border-bottom"><strong><?php echo safeOut($r['full_name']); ?></strong> <span class="text-muted small">— <?php echo timeAgo($r['created_at']); ?></span><p class="mb-0"><?php echo nl2br(safeOut($r['remark'])); ?></p></div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Upload Highlights &amp; Images</h2></div>
    <?php if ($mediaSaved): ?><div class="alert alert-success">Uploaded.</div><?php endif; ?>
    <?php if ($mediaError): ?><div class="alert alert-danger"><?php echo safeOut($mediaError); ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
      <?php echo csrfField(); ?>
      <input type="hidden" name="form_type" value="media">
      <div class="col-md-3"><label class="form-label small fw-semibold">Type</label>
        <select name="media_type" class="form-select form-select-sm" required><option value="photo">Image</option><option value="video">Video Highlight</option></select>
      </div>
      <div class="col-md-4"><label class="form-label small fw-semibold">File</label><input type="file" name="media_file" class="form-control form-control-sm" required></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Caption</label><input type="text" name="caption" class="form-control form-control-sm"></div>
      <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Upload</button></div>
    </form>
    <?php if (!empty($media)): ?>
      <div class="row g-3">
        <?php foreach ($media as $m): ?>
          <div class="col-md-3 col-6">
            <div class="event-media-card">
              <?php if ($m['media_type'] === 'photo'): ?><img src="<?php echo UPLOADS_URL . '/' . $m['file_path']; ?>" alt=""><?php else: ?><div class="event-media-icon"><i class="fa-solid fa-film"></i></div><?php endif; ?>
              <span><?php echo safeOut($m['caption'] ?: ucfirst($m['media_type'])); ?></span>
              <a href="<?php echo UPLOADS_URL . '/' . $m['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2 w-100">Open</a>
            </div>
          </div>
        <?php endforeach; ?>
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
