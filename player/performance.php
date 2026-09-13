<?php
/**
 * ============================================================
 * player/performance.php
 * ------------------------------------------------------------
 * §12/§13: Player's own performance — read only. Uses the
 * session's own player_id, never a URL parameter, so a player
 * can never view (let alone edit) anyone else's performance.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('player');

$playerId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare(
    'SELECT p.*, s.sport_name, t.team_name FROM players p JOIN sports s ON p.sport_id = s.sport_id
     LEFT JOIN teams t ON p.team_id = t.team_id WHERE p.player_id = ? LIMIT 1'
);
$stmt->bind_param('i', $playerId);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare(
    "SELECT m.match_id, m.match_title, m.match_date, m.venue, m.team_one, m.team_two, m.winner_team,
            (SELECT team_name FROM teams WHERE team_id = m.team_one) AS team_a_name,
            (SELECT team_name FROM teams WHERE team_id = m.team_two) AS team_b_name,
            ps.custom_score, pr.rating, pr.review, c.full_name AS coach_name
     FROM matches m
     LEFT JOIN player_scores ps ON ps.match_id = m.match_id AND ps.player_id = ?
     LEFT JOIN player_ratings pr ON pr.match_id = m.match_id AND pr.player_id = ?
     LEFT JOIN coaches c ON pr.coach_id = c.coach_id
     WHERE m.sport_id = ? AND m.status = 'Completed'
     ORDER BY m.match_date DESC"
);
$stmt->bind_param('iii', $playerId, $playerId, $player['sport_id']);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$scores = array_filter(array_column($history, 'custom_score'), fn($s) => $s !== null);
$ratings = array_filter(array_column($history, 'rating'), fn($r) => $r !== null);
$avgScore = !empty($scores) ? array_sum($scores) / count($scores) : null;
$avgRating = !empty($ratings) ? array_sum($ratings) / count($ratings) : null;
$level = classifyStarPerformanceLevel($avgRating !== null ? (float) $avgRating : null);

// Chronological (oldest first) for the trend chart.
$chronological = array_reverse($history);

$pageTitle = 'My Performance';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/performance.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/player_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-chart-line me-2"></i>My Performance</h1></div>
    <div class="d-flex gap-2">
      <?php if (!empty($player['coach_id'])): ?>
        <a href="<?php echo BASE_URL; ?>/chat/message_user.php?role=coach&id=<?php echo $player['coach_id']; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-comment me-1"></i>Message Coach</a>
      <?php endif; ?>
      <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $playerId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chart-pie me-1"></i>Advanced Analysis</a>
      <a href="<?php echo BASE_URL; ?>/player/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <div class="panel perf-profile-header">
    <img src="<?php echo currentProfileImage(); ?>" class="perf-profile-photo">
    <div>
      <h2><?php echo safeOut($player['full_name']); ?></h2>
      <p class="text-muted mb-0"><?php echo safeOut(formatEmployeeId('PL', $playerId)); ?> &middot; <?php echo safeOut($player['sport_name']); ?> &middot; <?php echo safeOut($player['team_name'] ?? 'No Team'); ?></p>
    </div>
    <div class="perf-profile-score">
      <div class="perf-stars"><?php echo $avgRating !== null ? str_repeat('★', (int) round($avgRating)) . str_repeat('☆', 5 - (int) round($avgRating)) : '☆☆☆☆☆'; ?></div>
      <span class="perf-level-badge perf-level-<?php echo str_replace(' ', '', $level); ?>"><?php echo safeOut($level); ?></span>
    </div>
  </div>

  <div class="row g-3 mb-1">
    <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo count($history); ?></div><div class="health-label">Total Matches</div></div></div>
    <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $avgScore !== null ? round($avgScore, 1) : '—'; ?></div><div class="health-label">Average Score</div></div></div>
    <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $avgRating !== null ? round($avgRating, 2) . '/5' : '—'; ?></div><div class="health-label">Average Rating</div></div></div>
  </div>

  <?php if (empty($history)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No performance data available yet.</p></div>
  <?php else: ?>
    <div class="panel">
      <div class="panel-head"><h2><i class="fa-solid fa-chart-line me-2"></i>My Performance Trend</h2></div>
      <div class="chart-wrap"><canvas id="chartTrend"></canvas></div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Match History</h2></div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Match</th><th>Date</th><th>Opponent</th><th>Score</th><th>Rating</th><th>Coach Comment</th></tr></thead>
          <tbody>
            <?php foreach ($history as $h):
              $opponent = $h['team_a_name'] && $h['team_b_name'] ? ($h['team_a_name'] . ' vs ' . $h['team_b_name']) : 'Individual';
            ?>
              <tr>
                <td><?php echo safeOut($h['match_title'] ?: $opponent); ?></td>
                <td><?php echo date('M j, Y', strtotime($h['match_date'])); ?></td>
                <td><?php echo safeOut($opponent); ?></td>
                <td><?php echo $h['custom_score'] !== null ? round($h['custom_score'], 1) : '—'; ?></td>
                <td><?php echo $h['rating'] !== null ? str_repeat('★', (int) $h['rating']) . str_repeat('☆', 5 - (int) $h['rating']) : '—'; ?></td>
                <td><?php echo $h['review'] ? safeOut($h['review']) . ' <span class="text-muted small">— ' . safeOut($h['coach_name'] ?? '') . '</span>' : '<span class="text-muted">—</span>'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<script>
  window.perfHistory = <?php echo json_encode(array_map(fn($h) => [
      'label' => $h['match_title'] ?: date('M j', strtotime($h['match_date'])),
      'score' => $h['custom_score'] !== null ? round((float) $h['custom_score'], 1) : null,
  ], $chronological)); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/performance.js"></script>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
