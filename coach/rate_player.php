<?php
/**
 * ============================================================
 * coach/rate_player.php
 * ------------------------------------------------------------
 * Rate Player workflow (§3). Ownership is verified in the
 * player lookup's own WHERE clause — a coach can never reach
 * another coach's sport's player by editing the URL (§18); if
 * the row doesn't match, $player is null and we redirect away.
 *
 * The match dropdown only ever lists Completed matches for the
 * coach's own sport (§16 — no rating on Upcoming/Cancelled
 * matches). Selecting a match AJAX-fetches any existing score/
 * rating for that (player, match) pair so the form becomes an
 * edit instead of risking a duplicate (§4).
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$playerId = (int) ($_GET['player_id'] ?? 0);

$stmt = $conn->prepare('SELECT sport_id, full_name FROM coaches WHERE coach_id = ? LIMIT 1');
$stmt->bind_param('i', $coachId);
$stmt->execute();
$coachRow = $stmt->get_result()->fetch_assoc();
$mySportId = (int) ($coachRow['sport_id'] ?? 0);
$stmt->close();

$stmt = $conn->prepare(
    'SELECT p.*, t.team_name, s.sport_name FROM players p LEFT JOIN teams t ON p.team_id = t.team_id
     JOIN sports s ON p.sport_id = s.sport_id WHERE p.player_id = ? AND p.sport_id = ? LIMIT 1'
);
$stmt->bind_param('ii', $playerId, $mySportId);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$player) {
    // Either the player doesn't exist, or belongs to another coach's sport — Access Denied either way.
    redirectTo(BASE_URL . '/coach/player_performance.php');
}

$flash = null;
$flashType = 'success';

// ---------- Handle submission ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'Session expired. Please try again.';
        $flashType = 'danger';
    } else {
        $matchId = (int) ($_POST['match_id'] ?? 0);
        $score = (string) ($_POST['score'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = cleanInput($_POST['comment'] ?? '');

        // ---- Server-side validation (never trust JS alone, §24) ----
        $stmt = $conn->prepare("SELECT status, sport_id FROM matches WHERE match_id = ? LIMIT 1");
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $match = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$match || (int) $match['sport_id'] !== $mySportId) {
            $flash = 'Invalid match selection.';
            $flashType = 'danger';
        } elseif ($match['status'] !== 'Completed') {
            $flash = 'Only Completed matches can be rated.';
            $flashType = 'danger';
        } elseif ($score === '' || !is_numeric($score) || (float) $score < 0) {
            $flash = 'Please enter a valid, non-negative score.';
            $flashType = 'danger';
        } elseif ($rating < 1 || $rating > 5) {
            $flash = 'Rating must be between 1 and 5.';
            $flashType = 'danger';
        } else {
            $scoreVal = (float) $score;
            $commentVal = $comment !== '' ? $comment : null;

            // player_scores: reuse existing UNIQUE(player_id, match_id) — insert or update custom_score.
            $stmt = $conn->prepare(
                'INSERT INTO player_scores (player_id, coach_id, match_id, custom_score) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE custom_score = VALUES(custom_score), coach_id = VALUES(coach_id)'
            );
            $stmt->bind_param('iiid', $playerId, $coachId, $matchId, $scoreVal);
            $stmt->execute();
            $stmt->close();

            // player_ratings: reuse existing UNIQUE(player_id, match_id) — insert or update rating/review.
            $stmt = $conn->prepare(
                'INSERT INTO player_ratings (player_id, coach_id, match_id, rating, review) VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review), coach_id = VALUES(coach_id)'
            );
            $stmt->bind_param('iiiis', $playerId, $coachId, $matchId, $rating, $commentVal);
            $stmt->execute();
            $wasNew = $stmt->affected_rows === 1; // mysqli: 1 = fresh insert, 2 = update-on-duplicate
            $stmt->close();

            logActivity($conn, 'coach', $coachId, "Rated player {$player['full_name']} for match #{$matchId}");
            notifyUser($conn, 'player', $playerId, 'Performance Updated', "Your performance has been updated by Coach {$coachRow['full_name']}.");

            $flash = $wasNew ? 'Performance submitted successfully.' : 'Performance updated successfully.';
            $flashType = 'success';
        }
    }
}

// ---------- Completed matches for this sport (dropdown) ----------
$stmt = $conn->prepare(
    "SELECT match_id, match_title, match_date, team_one, team_two,
            (SELECT team_name FROM teams WHERE team_id = m.team_one) AS team_a_name,
            (SELECT team_name FROM teams WHERE team_id = m.team_two) AS team_b_name
     FROM matches m WHERE m.sport_id = ? AND m.status = 'Completed' ORDER BY m.match_date DESC"
);
$stmt->bind_param('i', $mySportId);
$stmt->execute();
$completedMatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Match history (score + rating + comment per completed match) ----------
$stmt = $conn->prepare(
    "SELECT m.match_id, m.match_title, m.match_date,
            (SELECT team_name FROM teams WHERE team_id = m.team_one) AS team_a_name,
            (SELECT team_name FROM teams WHERE team_id = m.team_two) AS team_b_name,
            ps.custom_score, pr.rating, pr.review
     FROM matches m
     LEFT JOIN player_scores ps ON ps.match_id = m.match_id AND ps.player_id = ?
     LEFT JOIN player_ratings pr ON pr.match_id = m.match_id AND pr.player_id = ?
     WHERE m.sport_id = ? AND m.status = 'Completed'
     ORDER BY m.match_date ASC"
);
$stmt->bind_param('iii', $playerId, $playerId, $mySportId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$scores = array_column($history, 'custom_score');
$ratings = array_filter(array_column($history, 'rating'), fn($r) => $r !== null);
$avgScore = !empty(array_filter($scores, fn($s) => $s !== null)) ? array_sum(array_filter($scores, fn($s) => $s !== null)) / count(array_filter($scores, fn($s) => $s !== null)) : null;
$avgRating = !empty($ratings) ? array_sum($ratings) / count($ratings) : null;

$pageTitle = 'Rate ' . $player['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo safeOut($pageTitle); ?> | <?php echo SITE_NAME; ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/performance.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/coach_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/coach_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><?php echo safeOut($player['full_name']); ?></h1><p class="text-muted"><?php echo safeOut($player['sport_name']); ?> &middot; <?php echo safeOut($player['team_name'] ?? 'No Team'); ?></p></div>
    <div class="d-flex gap-2">
      <a href="<?php echo BASE_URL; ?>/chat/message_user.php?role=player&id=<?php echo $playerId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-comment me-1"></i>Message Player</a>
      <a href="<?php echo BASE_URL; ?>/coach/player_performance.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert alert-<?php echo $flashType; ?>"><?php echo safeOut($flash); ?></div><?php endif; ?>

  <div class="row g-3 mb-1">
    <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo count($history); ?></div><div class="health-label">Matches</div></div></div>
    <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $avgScore !== null ? round($avgScore, 1) : '—'; ?></div><div class="health-label">Average Score</div></div></div>
    <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $avgRating !== null ? round($avgRating, 2) . '/5' : '—'; ?></div><div class="health-label">Average Rating</div></div></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><i class="fa-solid fa-star me-2"></i>Rate Player</h2></div>

    <?php if (empty($completedMatches)): ?>
      <p class="text-muted mb-0">No completed matches for your sport yet — ratings can only be submitted after a match is marked Completed.</p>
    <?php else: ?>
      <form method="POST" class="row g-3" id="rateForm">
        <?php echo csrfField(); ?>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Match *</label>
          <select name="match_id" id="matchSelect" class="form-select" required>
            <option value="">Select a completed match</option>
            <?php foreach ($completedMatches as $m): ?>
              <option value="<?php echo $m['match_id']; ?>">
                <?php echo safeOut($m['match_title'] ?: (($m['team_a_name'] ?? 'TBD') . ($m['team_b_name'] ? ' vs ' . $m['team_b_name'] : ''))); ?>
                — <?php echo date('M j, Y', strtotime($m['match_date'])); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Score *</label>
          <input type="number" step="0.01" min="0" name="score" id="scoreField" class="form-control" required>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Rating *</label>
          <div class="star-rating-input" id="starRatingInput">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="fa-solid fa-star star-choice" data-value="<?php echo $i; ?>"></i>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="ratingField" value="0" required>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Performance Comment</label>
          <textarea name="comment" id="commentField" class="form-control" rows="3" placeholder="e.g. Excellent batting performance and strong fielding."></textarea>
        </div>
        <div class="col-12">
          <div id="duplicateNotice" class="alert alert-info d-none mb-3">This player has already been rated for this match — submitting will update the existing rating.</div>
          <button type="submit" class="btn btn-auth-submit" style="width:auto; padding-left:28px; padding-right:28px;" id="submitBtn">
            <i class="fa-solid fa-floppy-disk me-2"></i>Submit Performance
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <div class="panel" id="history">
    <div class="panel-head"><h2>Match History</h2></div>
    <?php if (empty($history)): ?>
      <p class="text-muted mb-0">No performance data available yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Match</th><th>Date</th><th>Score</th><th>Rating</th><th>Comment</th></tr></thead>
          <tbody>
            <?php foreach (array_reverse($history) as $h): ?>
              <tr>
                <td><?php echo safeOut($h['match_title'] ?: (($h['team_a_name'] ?? 'TBD') . ($h['team_b_name'] ? ' vs ' . $h['team_b_name'] : ''))); ?></td>
                <td><?php echo date('M j, Y', strtotime($h['match_date'])); ?></td>
                <td><?php echo $h['custom_score'] !== null ? round($h['custom_score'], 1) : '—'; ?></td>
                <td><?php echo $h['rating'] !== null ? str_repeat('★', (int) $h['rating']) . str_repeat('☆', 5 - (int) $h['rating']) : '—'; ?></td>
                <td><?php echo $h['review'] ? safeOut($h['review']) : '<span class="text-muted">—</span>'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="chart-wrap mt-3"><canvas id="chartTrend"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<style>
.star-rating-input { font-size: 1.6rem; color: #d8dde6; cursor: pointer; }
.star-rating-input .star-choice.active { color: #FFC107; }
</style>

<script>
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
  window.SMS_PLAYER_ID = <?php echo $playerId; ?>;
  window.perfHistory = <?php echo json_encode(array_map(fn($h) => [
      'label' => $h['match_title'] ?: date('M j', strtotime($h['match_date'])),
      'score' => $h['custom_score'] !== null ? round((float) $h['custom_score'], 1) : null,
  ], $history)); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/performance.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/rate_player.js"></script>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
