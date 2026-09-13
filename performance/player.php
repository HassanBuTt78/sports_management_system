<?php
/**
 * ============================================================
 * performance/player.php
 * ------------------------------------------------------------
 * §4 Player Performance Profile + §11 trend chart + §12
 * improvement + §13 smart insights + §14 classification.
 * Access (§29): admin=any, coach=only their own sport's players,
 * player=only themselves.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/performance_engine.php';

requireRole('admin', 'coach', 'player');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$playerId = (int) ($_GET['id'] ?? 0);

if ($myRole === 'player' && $playerId !== $myId) {
    redirectTo(BASE_URL . '/performance/player.php?id=' . $myId);
}

if (!$conn || $playerId <= 0) {
    redirectTo(BASE_URL . '/performance/index.php');
}

$stmt = $conn->prepare(
    'SELECT p.*, s.sport_name, c.full_name AS coach_name, t.team_name FROM players p
     JOIN sports s ON p.sport_id = s.sport_id LEFT JOIN coaches c ON p.coach_id = c.coach_id
     LEFT JOIN teams t ON p.team_id = t.team_id WHERE p.player_id = ? LIMIT 1'
);
$stmt->bind_param('i', $playerId);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$player) {
    redirectTo(BASE_URL . '/performance/index.php');
}

// Coach access: only their own sport's players.
if ($myRole === 'coach') {
    $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
    $stmt->bind_param('i', $myId);
    $stmt->execute();
    $mySportId = $stmt->get_result()->fetch_assoc()['sport_id'] ?? 0;
    $stmt->close();
    if ((int) $mySportId !== (int) $player['sport_id']) {
        redirectTo(BASE_URL . '/performance/index.php');
    }
}

$stmt = $conn->prepare('SELECT * FROM performance_analysis WHERE player_id = ? LIMIT 1');
$stmt->bind_param('i', $playerId);
$stmt->execute();
$perf = $stmt->get_result()->fetch_assoc();
$stmt->close();

$live = calculatePlayerPerformance($conn, $playerId);

$stmt = $conn->prepare(
    "SELECT h.*, m.match_title, m.match_date FROM player_performance_history h
     JOIN matches m ON h.match_id = m.match_id WHERE h.player_id = ? ORDER BY m.match_date ASC"
);
$stmt->bind_param('i', $playerId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare('SELECT pr.rating, pr.review, pr.created_at, c.full_name AS coach_name FROM player_ratings pr JOIN coaches c ON pr.coach_id = c.coach_id WHERE pr.player_id = ? ORDER BY pr.created_at DESC LIMIT 10');
$stmt->bind_param('i', $playerId);
$stmt->execute();
$ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---- Smart insights (§13) — explainable, grounded only in what was actually computed. ----
$insights = [];
if ($live['has_data']) {
    if ($live['trend'] === 'Improving') $insights[] = 'Player performance has improved in recent matches.';
    if ($live['trend'] === 'Declining') $insights[] = 'Player performance has declined in recent matches.';
    if ($live['components']['coach_rating'] !== null && $live['components']['coach_rating'] >= 80 && $live['matches_played'] < 3) {
        $insights[] = 'Player has a high coach rating but limited match participation.';
    }
    if ($live['components']['consistency'] !== null && $live['components']['consistency'] >= 80) {
        $insights[] = 'Player has been consistently performing at a stable level.';
    }
    if ($live['limited_data']) {
        $insights[] = 'Limited data — ranking may change as more matches are completed.';
    }
    if (empty($insights)) $insights[] = 'Player has a steady performance record based on the matches played so far.';
} else {
    $insights[] = 'Player has insufficient match data for reliable analysis.';
}

$pageTitle = $player['full_name'] . ' — Performance';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/performance.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/role_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/role_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1>Performance Profile</h1></div>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="panel perf-profile-header">
    <img src="<?php echo $player['profile_image'] ? UPLOADS_URL . '/' . $player['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" class="perf-profile-photo">
    <div>
      <h2><?php echo safeOut($player['full_name']); ?></h2>
      <p class="text-muted mb-1"><?php echo safeOut(formatEmployeeId('PLR', $playerId)); ?> &middot; <?php echo safeOut($player['sport_name']); ?> &middot; <?php echo safeOut($player['team_name'] ?? 'No Team'); ?></p>
      <p class="text-muted mb-0">Coach: <?php echo safeOut($player['coach_name'] ?? 'Unassigned'); ?></p>
    </div>
    <?php if ($live['has_data']): ?>
      <div class="perf-profile-score">
        <div class="perf-score-circle"><?php echo round($live['performance_score'], 1); ?></div>
        <div class="perf-stars"><?php echo str_repeat('★', (int) floor($live['star_rating'])) . (fmod($live['star_rating'], 1) >= 0.5 ? '½' : '') . str_repeat('☆', 5 - (int) ceil($live['star_rating'])); ?></div>
        <span class="perf-level-badge perf-level-<?php echo str_replace(' ', '', $live['performance_level']); ?>"><?php echo safeOut($live['performance_level']); ?></span>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$live['has_data']): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No performance data available yet.</p></div>
  <?php else: ?>

    <?php if ($live['limited_data']): ?>
      <div class="alert alert-warning">Limited data — ranking may change as more matches are completed.</div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="health-item"><div class="health-value"><?php echo $live['matches_played']; ?></div><div class="health-label">Matches Played</div></div></div>
      <div class="col-6 col-md-3"><div class="health-item"><div class="health-value" style="color:#198754;"><?php echo $live['matches_won']; ?></div><div class="health-label">Won</div></div></div>
      <div class="col-6 col-md-3"><div class="health-item"><div class="health-value" style="color:#c1443c;"><?php echo $live['matches_lost']; ?></div><div class="health-label">Lost</div></div></div>
      <div class="col-6 col-md-3"><div class="health-item"><div class="health-value"><?php echo $live['matches_drawn']; ?></div><div class="health-label">Drawn</div></div></div>
    </div>

    <div class="grid-2col">
      <div class="panel">
        <div class="panel-head"><h2>How This Score Was Calculated</h2></div>
        <p class="fw-bold mb-3">Performance Score: <?php echo round($live['performance_score'], 1); ?> / 100</p>
        <?php
        $labels = ['coach_rating' => 'Coach Rating', 'match_performance' => 'Match Performance', 'sport_statistics' => 'Sport Statistics', 'consistency' => 'Consistency', 'improvement' => 'Improvement'];
        foreach ($labels as $key => $label):
          $val = $live['components'][$key];
          $weight = round($live['weights'][$key] * 100);
        ?>
          <div class="perf-component-row">
            <div class="d-flex justify-content-between"><span><?php echo $label; ?> (<?php echo $weight; ?>%)</span><span><?php echo $val !== null ? round($val, 1) . '/100' : 'No data'; ?></span></div>
            <div class="progress" style="height:6px;"><div class="progress-bar" style="width:<?php echo $val !== null ? round($val) : 0; ?>%"></div></div>
          </div>
        <?php endforeach; ?>
        <p class="text-muted small mt-2 mb-0">Weights not available for a player are proportionally redistributed across the remaining components — this player's final score used only what's shown above.</p>
      </div>

      <div class="panel">
        <div class="panel-head"><h2>Smart Insights</h2></div>
        <ul class="perf-insights-list">
          <?php foreach ($insights as $i): ?><li><i class="fa-solid fa-lightbulb"></i><?php echo safeOut($i); ?></li><?php endforeach; ?>
        </ul>
        <hr>
        <p class="mb-1"><strong>Trend:</strong> <span class="perf-trend-badge perf-trend-<?php echo strtolower($live['trend']); ?>"><?php echo safeOut($live['trend']); ?></span></p>
        <?php if ($perf && $perf['rank']): ?>
          <p class="mb-0"><strong>Rank:</strong> #<?php echo $perf['rank']; ?>
            <?php if ($perf['rank_change'] !== null): ?>
              <?php if ($perf['rank_change'] > 0): ?><span class="text-success">↑ <?php echo $perf['rank_change']; ?></span>
              <?php elseif ($perf['rank_change'] < 0): ?><span class="text-danger">↓ <?php echo abs($perf['rank_change']); ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            <?php else: ?><span class="badge bg-info">NEW</span><?php endif; ?>
            <?php if ($perf['previous_rank']): ?><span class="text-muted small">(was #<?php echo $perf['previous_rank']; ?>)</span><?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h2>Performance Trend</h2>
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-outline-secondary trend-range-btn active" data-range="7">7 Matches</button>
          <button type="button" class="btn btn-outline-secondary trend-range-btn" data-range="10">10 Matches</button>
          <button type="button" class="btn btn-outline-secondary trend-range-btn" data-range="all">All Time</button>
        </div>
      </div>
      <?php if (empty($history)): ?>
        <p class="text-muted mb-0">No performance data available yet.</p>
      <?php else: ?>
        <div class="chart-wrap"><canvas id="chartTrend"></canvas></div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Coach Ratings &amp; Reviews</h2></div>
      <?php if (empty($ratings)): ?>
        <p class="text-muted mb-0">No performance data available yet.</p>
      <?php else: ?>
        <?php foreach ($ratings as $r): ?>
          <div class="perf-rating-row">
            <span class="perf-stars-small"><?php echo str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']); ?></span>
            <div>
              <strong><?php echo safeOut($r['coach_name']); ?></strong> <span class="text-muted small"><?php echo timeAgo($r['created_at']); ?></span>
              <?php if ($r['review']): ?><p class="mb-0 small"><?php echo safeOut($r['review']); ?></p><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</div>
<script>
  window.perfHistory = <?php echo json_encode(array_map(fn($h) => [
      'label' => ($h['match_title'] ?: date('M j', strtotime($h['match_date']))),
      'score' => $h['performance_score'] !== null ? round((float) $h['performance_score'], 1) : null,
  ], $history)); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/performance.js"></script>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
