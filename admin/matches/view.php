<?php
/**
 * ============================================================
 * admin/matches/view.php
 * ------------------------------------------------------------
 * Match Details + Score table. The score table is sport-aware —
 * Cricket/Football/Hockey each show their own relevant columns
 * from player_scores (per the brief's Score Entry field list),
 * and handles match media uploads (photos/videos/scoresheets)
 * inline. Matches move straight from Scheduled to Completed once
 * a coach/admin enters the result — there is no in-progress state.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$matchId = (int) ($_GET['id'] ?? 0);
$match = null;

if ($conn && $matchId > 0) {
    $stmt = $conn->prepare(
        "SELECT m.*, s.sport_name, e.title AS event_title, ta.team_name AS team_a_name, tb.team_name AS team_b_name,
                c.full_name AS coach_name, tw.team_name AS winner_name, tr.team_name AS runnerup_name, mvp.full_name AS mvp_name
         FROM matches m
         LEFT JOIN sports s ON m.sport_id = s.sport_id
         LEFT JOIN events e ON m.event_id = e.event_id
         LEFT JOIN teams ta ON m.team_one = ta.team_id
         LEFT JOIN teams tb ON m.team_two = tb.team_id
         LEFT JOIN coaches c ON m.coach_id = c.coach_id
         LEFT JOIN teams tw ON m.winner_team = tw.team_id
         LEFT JOIN teams tr ON m.runner_up_team = tr.team_id
         LEFT JOIN players mvp ON m.mvp_player_id = mvp.player_id
         WHERE m.match_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$match) {
    redirectTo(BASE_URL . '/admin/matches/index.php');
}

/** Renders the sport-aware player-scores table — shared by the full page and the live-poll partial. */
function renderScoresTable(array $scores, string $sportName): void {
    if (empty($scores)) {
        echo '<p class="text-muted mb-0">No scores recorded yet.</p>';
        return;
    }
    $sport = strtolower($sportName);
    if ($sport === 'cricket') {
        $cols = ['runs' => 'Runs', 'balls' => 'Balls', 'wickets' => 'Wickets', 'overs' => 'Overs', 'catches' => 'Catches', 'run_outs' => 'Run Outs', 'strike_rate' => 'Strike Rate', 'economy' => 'Economy'];
    } elseif ($sport === 'football') {
        $cols = ['goals' => 'Goals', 'assists' => 'Assists', 'yellow_cards' => 'Yellow Cards', 'red_cards' => 'Red Cards', 'saves' => 'Saves'];
    } elseif ($sport === 'hockey') {
        $cols = ['goals' => 'Goals', 'assists' => 'Assists', 'yellow_cards' => 'Yellow/Green Cards', 'red_cards' => 'Red Cards', 'saves' => 'Saves'];
    } else {
        $cols = ['goals' => 'Goals', 'runs' => 'Runs', 'points' => 'Points', 'custom_score' => 'Score'];
    }

    echo '<div class="table-responsive"><table class="table align-middle">';
    echo '<thead><tr><th>Player</th>';
    foreach ($cols as $label) echo '<th>' . $label . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($scores as $sc) {
        $avatar = $sc['profile_image'] ? UPLOADS_URL . '/' . $sc['profile_image'] : ASSETS_URL . '/images/default-avatar.svg';
        echo '<tr><td class="mini-profile"><img src="' . $avatar . '" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">' . safeOut($sc['full_name']) . '</td>';
        foreach (array_keys($cols) as $key) {
            $val = $sc[$key] ?? null;
            echo '<td>' . ($val !== null ? safeOut((string) $val) : '—') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

if ($conn) {
    $stmt = $conn->prepare(
        "SELECT ps.*, p.full_name, p.profile_image FROM player_scores ps
         JOIN players p ON ps.player_id = p.player_id WHERE ps.match_id = ? ORDER BY p.full_name"
    );
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $scores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $scores = [];
}

// ?partial=scores returns just the score-table fragment (used when the
// page needs to refresh it after an edit, without a full reload).
if (($_GET['partial'] ?? '') === 'scores') {
    renderScoresTable($scores, $match['sport_name'] ?? '');
    exit;
}

$media = [];
$awards = [];
$remarks = [];
$uploadError = null;
$uploadSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'media_upload' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $uploadError = 'Session expired. Please try again.';
    } else {
        $mediaType = cleanInput($_POST['media_type'] ?? '');
        $caption = cleanInput($_POST['caption'] ?? '');
        $allowedByType = ['photo' => ['jpg','jpeg','png'], 'video' => ['mp4','webm','mov'], 'scoresheet' => ['pdf','jpg','jpeg','png']];
        if (!isset($allowedByType[$mediaType])) {
            $uploadError = 'Please choose a media type.';
        } else {
            $fileCheck = validateUploadedFile($_FILES['media_file'] ?? [], $allowedByType[$mediaType], 5 * 1024 * 1024);
            if (!$fileCheck['valid']) {
                $uploadError = $fileCheck['error'];
            } else {
                $result = storeUploadedFile($_FILES['media_file'], 'matches');
                if ($result['success'] && $result['path']) {
                    $role = 'admin';
                    $uid = (int) $_SESSION['user_id'];
                    $captionVal = $caption !== '' ? $caption : null;
                    $stmt = $conn->prepare('INSERT INTO match_media (match_id, media_type, file_path, caption, uploaded_by_role, uploaded_by_id) VALUES (?,?,?,?,?,?)');
                    $stmt->bind_param('issssi', $matchId, $mediaType, $result['path'], $captionVal, $role, $uid);
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
    $stmt = $conn->prepare('SELECT * FROM match_media WHERE match_id = ? ORDER BY uploaded_at DESC');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $media = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT ma.award_type, p.full_name FROM match_awards ma JOIN players p ON ma.player_id = p.player_id WHERE ma.match_id = ?');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $awards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT r.remark, r.created_at, c.full_name FROM match_remarks r JOIN coaches c ON r.coach_id = c.coach_id WHERE r.match_id = ? ORDER BY r.created_at DESC');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $remarks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Match Details';
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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1><?php echo safeOut($match['match_title'] ?: 'Match Details'); ?></h1><p><?php echo safeOut($match['sport_name'] ?? '—'); ?></p></div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/admin/matches/edit.php?id=<?php echo $matchId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i>Edit</a>
          <a href="<?php echo BASE_URL; ?>/admin/matches/results.php?id=<?php echo $matchId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-medal me-1"></i>Results</a>
          <a href="<?php echo BASE_URL; ?>/chat/match_chat.php?match_id=<?php echo $matchId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-comments me-1"></i>Discussion</a>
          <a href="<?php echo BASE_URL; ?>/admin/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
      </div>

      <div class="grid-2col">
        <div class="panel">
          <div class="panel-head"><h2>Match Info</h2></div>
          <table class="table table-borderless">
            <tbody>
              <tr><th style="width:160px;">Event</th><td><?php echo safeOut($match['event_title'] ?? '—'); ?></td></tr>
              <tr><th>Matchup</th><td><?php echo safeOut($match['team_a_name'] ?? 'TBD') . ($match['team_b_name'] ? ' vs ' . safeOut($match['team_b_name']) : ' (individual)'); ?></td></tr>
              <tr><th>Coach</th><td><?php echo safeOut($match['coach_name'] ?? '—'); ?></td></tr>
              <tr><th>Venue</th><td><?php echo safeOut($match['venue'] ?? '—'); ?></td></tr>
              <tr><th>Referee</th><td><?php echo safeOut($match['referee'] ?? '—'); ?></td></tr>
              <tr><th>Date</th><td><?php echo date('M j, Y', strtotime($match['match_date'])); ?><?php echo $match['match_time'] ? ' at ' . date('g:i A', strtotime($match['match_time'])) : ''; ?></td></tr>
              <tr><th>Status</th><td><span class="event-mini-badge badge-<?php echo $match['status']; ?>"><?php echo $match['status'] === 'Upcoming' ? 'Scheduled' : safeOut($match['status']); ?></span></td></tr>
            </tbody>
          </table>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Result</h2></div>
          <?php if ($match['status'] === 'Completed'): ?>
            <table class="table table-borderless">
              <tbody>
                <tr><th style="width:160px;">Winner</th><td><?php echo safeOut($match['winner_name'] ?? '—'); ?></td></tr>
                <tr><th>Runner-up</th><td><?php echo safeOut($match['runnerup_name'] ?? '—'); ?></td></tr>
                <tr><th>MVP</th><td><?php echo safeOut($match['mvp_name'] ?? '—'); ?></td></tr>
                <tr><th>Summary</th><td><?php echo safeOut($match['result_summary'] ?? '—'); ?></td></tr>
              </tbody>
            </table>
            <?php if (!empty($awards)): ?>
              <hr>
              <p class="fw-semibold mb-2">Awards</p>
              <ul class="mb-0">
                <?php foreach ($awards as $a): ?><li><?php echo safeOut($a['award_type']); ?>: <?php echo safeOut($a['full_name']); ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>
          <?php else: ?>
            <p class="text-muted mb-0">Result will be available once this match is marked Completed.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-chart-simple me-2"></i>Score</h2></div>
        <div id="scoreTableBox">
          <?php renderScoresTable($scores, $match['sport_name'] ?? ''); ?>
        </div>
      </div>

      <?php if (!empty($remarks)): ?>
      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-comment-dots me-2"></i>Coach Remarks</h2></div>
        <?php foreach ($remarks as $r): ?>
          <div class="mb-2 pb-2 border-bottom"><strong><?php echo safeOut($r['full_name']); ?></strong> <span class="text-muted small">— <?php echo timeAgo($r['created_at']); ?></span><p class="mb-0"><?php echo nl2br(safeOut($r['remark'])); ?></p></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-photo-film me-2"></i>Match Media <span class="panel-sub">(photos / videos / scoresheets)</span></h2></div>
        <?php if ($uploadSuccess): ?><div class="alert alert-success">Uploaded.</div><?php endif; ?>
        <?php if ($uploadError): ?><div class="alert alert-danger"><?php echo safeOut($uploadError); ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-4">
          <?php echo csrfField(); ?>
          <input type="hidden" name="form_type" value="media_upload">
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Type</label>
            <select name="media_type" class="form-select form-select-sm" required>
              <option value="photo">Photo</option><option value="video">Video (Highlight)</option><option value="scoresheet">Scoresheet</option>
            </select>
          </div>
          <div class="col-md-4"><label class="form-label small fw-semibold">File</label><input type="file" name="media_file" class="form-control form-control-sm" required></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Caption</label><input type="text" name="caption" class="form-control form-control-sm"></div>
          <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Upload</button></div>
        </form>
        <?php if (empty($media)): ?>
          <p class="text-muted mb-0">No media uploaded yet.</p>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($media as $m): ?>
              <div class="col-md-3 col-6">
                <div class="event-media-card">
                  <?php if ($m['media_type'] === 'photo'): ?><img src="<?php echo UPLOADS_URL . '/' . $m['file_path']; ?>" alt="">
                  <?php else: ?><div class="event-media-icon"><i class="fa-solid <?php echo $m['media_type'] === 'video' ? 'fa-file-video' : 'fa-file-lines'; ?>"></i></div><?php endif; ?>
                  <span><?php echo safeOut($m['caption'] ?: ucfirst($m['media_type'])); ?></span>
                  <a href="<?php echo UPLOADS_URL . '/' . $m['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2 w-100">Open</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php
  $extraFooterScripts = '
    <script>
      window.SMS_BASE_URL = ' . json_encode(BASE_URL) . ';
      window.SMS_MATCH_ID = ' . (int) $matchId . ';
    </script>
    <script src="' . BASE_URL . '/assets/js/matches.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
