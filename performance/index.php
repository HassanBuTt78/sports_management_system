<?php
/**
 * ============================================================
 * performance/index.php
 * ------------------------------------------------------------
 * Role-aware dashboard:
 *  - admin: full analytics (all players/teams/sports)
 *  - coach: scoped to their own sport (§15)
 *  - player: redirected straight to their own profile (§29 —
 *    "Player can view ONLY their own performance")
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/performance_engine.php';

requireRole('admin', 'coach', 'player');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];

if ($myRole === 'player') {
    redirectTo(BASE_URL . '/performance/player.php?id=' . $myId);
}

$data = [];

if ($conn && $myRole === 'admin') {
    $data['total_players'] = (int) $conn->query('SELECT COUNT(*) c FROM players')->fetch_assoc()['c'];
    $data['players_with_data'] = (int) $conn->query('SELECT COUNT(*) c FROM performance_analysis WHERE performance_score IS NOT NULL')->fetch_assoc()['c'];
    $data['avg_rating'] = $conn->query('SELECT AVG(average_rating) a FROM performance_analysis WHERE average_rating IS NOT NULL')->fetch_assoc()['a'];

    $best = $conn->query(
        "SELECT pa.*, p.full_name, p.profile_image, s.sport_name FROM performance_analysis pa
         JOIN players p ON pa.player_id = p.player_id JOIN sports s ON pa.sport_id = s.sport_id
         WHERE pa.performance_score IS NOT NULL ORDER BY pa.performance_score DESC LIMIT 1"
    )->fetch_assoc();
    $data['best_player'] = $best;

    $bestTeam = $conn->query(
        "SELECT tp.*, t.team_name FROM team_performance tp JOIN teams t ON tp.team_id = t.team_id
         WHERE tp.avg_team_performance IS NOT NULL ORDER BY tp.avg_team_performance DESC LIMIT 1"
    )->fetch_assoc();
    $data['best_team'] = $bestTeam;

    $bestSport = $conn->query(
        "SELECT s.sport_name, AVG(pa.performance_score) avg_score FROM performance_analysis pa
         JOIN sports s ON pa.sport_id = s.sport_id WHERE pa.performance_score IS NOT NULL
         GROUP BY s.sport_id ORDER BY avg_score DESC LIMIT 1"
    )->fetch_assoc();
    $data['best_sport'] = $bestSport;

    $mostImproved = $conn->query(
        "SELECT pa.*, p.full_name, p.profile_image FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id
         WHERE pa.improvement_component IS NOT NULL ORDER BY pa.improvement_component DESC LIMIT 1"
    )->fetch_assoc();
    $data['most_improved'] = $mostImproved;

    $data['needing_improvement'] = $conn->query(
        "SELECT pa.*, p.full_name, p.profile_image, s.sport_name FROM performance_analysis pa
         JOIN players p ON pa.player_id = p.player_id JOIN sports s ON pa.sport_id = s.sport_id
         WHERE pa.performance_level = 'Needs Improvement' ORDER BY pa.performance_score ASC LIMIT 8"
    )->fetch_all(MYSQLI_ASSOC);

    // ---- Charts ----
    $data['chart_by_sport'] = $conn->query(
        "SELECT s.sport_name, AVG(pa.performance_score) avg_score, COUNT(*) c FROM performance_analysis pa
         JOIN sports s ON pa.sport_id = s.sport_id WHERE pa.performance_score IS NOT NULL GROUP BY s.sport_id"
    )->fetch_all(MYSQLI_ASSOC);

    $data['chart_top10'] = $conn->query(
        "SELECT p.full_name, pa.performance_score FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id
         WHERE pa.performance_score IS NOT NULL ORDER BY pa.performance_score DESC LIMIT 10"
    )->fetch_all(MYSQLI_ASSOC);

    $data['chart_distribution'] = $conn->query(
        "SELECT performance_level, COUNT(*) c FROM performance_analysis WHERE performance_level IS NOT NULL GROUP BY performance_level"
    )->fetch_all(MYSQLI_ASSOC);

    $data['chart_rating_distribution'] = $conn->query(
        "SELECT FLOOR(average_rating) AS bucket, COUNT(*) c FROM performance_analysis WHERE average_rating IS NOT NULL GROUP BY bucket ORDER BY bucket"
    )->fetch_all(MYSQLI_ASSOC);

} elseif ($conn && $myRole === 'coach') {
    $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
    $stmt->bind_param('i', $myId);
    $stmt->execute();
    $mySportId = $stmt->get_result()->fetch_assoc()['sport_id'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT pa.*, p.full_name, p.profile_image FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id
         WHERE p.sport_id = ? AND pa.performance_score IS NOT NULL ORDER BY pa.performance_score DESC"
    );
    $stmt->bind_param('i', $mySportId);
    $stmt->execute();
    $sportPlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $data['best_player'] = $sportPlayers[0] ?? null;
    $data['top5'] = array_slice($sportPlayers, 0, 5);
    $data['improving'] = array_values(array_filter($sportPlayers, fn($p) => $p['trend'] === 'Improving'));
    $data['declining'] = array_values(array_filter($sportPlayers, fn($p) => $p['trend'] === 'Declining'));
    $data['needing_improvement'] = array_values(array_filter($sportPlayers, fn($p) => $p['performance_level'] === 'Needs Improvement'));
    $scores = array_column($sportPlayers, 'performance_score');
    $data['avg_team_performance'] = !empty($scores) ? array_sum($scores) / count($scores) : null;
    $data['sport_players_count'] = count($sportPlayers);

}

$pageTitle = 'Performance Analysis';
$isAdmin = $myRole === 'admin';
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

<?php if ($isAdmin): ?>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/role_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/role_topbar.php'; ?>
    <div class="admin-content">
<?php else: ?>
<div class="container py-5">
<?php endif; ?>

      <div class="page-heading">
        <div><h1><i class="fa-solid fa-chart-line me-2"></i>Performance Analysis</h1><p>
          <?php if ($myRole === 'admin'): ?>Smart ranking and performance analytics across all sports.
          <?php elseif ($myRole === 'coach'): ?>Performance for players in your sport.
          <?php else: ?>College-wide performance summary.<?php endif; ?>
        </p></div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/performance/leaderboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-ranking-star me-1"></i>Leaderboard</a>
          <?php if ($isAdmin): ?><a href="<?php echo BASE_URL; ?>/admin/player_performance.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-star me-1"></i>Ratings View</a><?php endif; ?>
          <?php if ($isAdmin): ?><a href="<?php echo BASE_URL; ?>/performance/recalculate.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-rotate me-1"></i>Recalculate</a><?php endif; ?>
          <?php if (!$isAdmin): ?><a href="<?php echo BASE_URL . '/' . $myRole; ?>/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a><?php endif; ?>
        </div>
      </div>

      <?php if ($myRole === 'admin'): ?>
        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
          <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-card-value" data-counter="<?php echo $data['total_players']; ?>">0</div><div class="stat-card-label">Total Players</div></div>
          <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-chart-simple"></i></div>
            <div class="stat-card-value" data-counter="<?php echo $data['players_with_data']; ?>">0</div><div class="stat-card-label">Players With Data</div></div>
          <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-star"></i></div>
            <div class="stat-card-value"><?php echo $data['avg_rating'] ? round($data['avg_rating'], 2) : '—'; ?></div><div class="stat-card-label">Avg College Rating</div></div>
          <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div>
            <div class="stat-card-value" style="font-size:1rem;"><?php echo safeOut($data['best_sport']['sport_name'] ?? '—'); ?></div><div class="stat-card-label">Best Sport</div></div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <div class="panel perf-highlight-card">
              <span class="perf-highlight-label">🏆 Best Player</span>
              <?php if ($data['best_player']): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                  <img src="<?php echo $data['best_player']['profile_image'] ? UPLOADS_URL . '/' . $data['best_player']['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                  <div><strong><?php echo safeOut($data['best_player']['full_name']); ?></strong><br><span class="text-muted small"><?php echo safeOut($data['best_player']['sport_name']); ?> &middot; <?php echo round($data['best_player']['performance_score'], 1); ?>/100</span></div>
                </div>
              <?php else: ?><p class="text-muted mb-0">No performance data available yet.</p><?php endif; ?>
            </div>
          </div>
          <div class="col-md-4">
            <div class="panel perf-highlight-card">
              <span class="perf-highlight-label">🥇 Best Team</span>
              <?php if ($data['best_team']): ?>
                <div class="mt-2"><strong><?php echo safeOut($data['best_team']['team_name']); ?></strong><br><span class="text-muted small"><?php echo round($data['best_team']['avg_team_performance'], 1); ?>/100 avg performance</span></div>
              <?php else: ?><p class="text-muted mb-0">No performance data available yet.</p><?php endif; ?>
            </div>
          </div>
          <div class="col-md-4">
            <div class="panel perf-highlight-card">
              <span class="perf-highlight-label">📈 Most Improved</span>
              <?php if ($data['most_improved']): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                  <img src="<?php echo $data['most_improved']['profile_image'] ? UPLOADS_URL . '/' . $data['most_improved']['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                  <div><strong><?php echo safeOut($data['most_improved']['full_name']); ?></strong><br><span class="text-muted small">Improvement score: <?php echo round($data['most_improved']['improvement_component'], 1); ?></span></div>
                </div>
              <?php else: ?><p class="text-muted mb-0">No performance data available yet.</p><?php endif; ?>
            </div>
          </div>
        </div>

        <div class="grid-charts">
          <div class="panel"><div class="panel-head"><h2>Performance by Sport</h2></div><div class="chart-wrap"><canvas id="chartBySport"></canvas></div></div>
          <div class="panel"><div class="panel-head"><h2>Performance Level Distribution</h2></div><div class="chart-wrap"><canvas id="chartDistribution"></canvas></div></div>
        </div>
        <div class="grid-charts">
          <div class="panel"><div class="panel-head"><h2>Top 10 Players</h2></div><div class="chart-wrap"><canvas id="chartTop10"></canvas></div></div>
          <div class="panel"><div class="panel-head"><h2>Rating Distribution</h2></div><div class="chart-wrap"><canvas id="chartRating"></canvas></div></div>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Players Needing Improvement</h2></div>
          <?php if (empty($data['needing_improvement'])): ?>
            <p class="text-muted mb-0">No performance data available yet.</p>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($data['needing_improvement'] as $p): ?>
                <div class="col-md-3 col-6">
                  <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $p['player_id']; ?>" class="perf-mini-player">
                    <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>">
                    <span><?php echo safeOut($p['full_name']); ?></span>
                    <small><?php echo round($p['performance_score'], 1); ?>/100</small>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      <?php elseif ($myRole === 'coach'): ?>
        <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
          <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-card-value" data-counter="<?php echo $data['sport_players_count']; ?>">0</div><div class="stat-card-label">Players With Data</div></div>
          <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="stat-card-value"><?php echo $data['avg_team_performance'] ? round($data['avg_team_performance'], 1) : '—'; ?></div><div class="stat-card-label">Avg Team Performance</div></div>
          <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div>
            <div class="stat-card-value" style="font-size:1rem;"><?php echo safeOut($data['best_player']['full_name'] ?? '—'); ?></div><div class="stat-card-label">Best Player</div></div>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Top 5 Players</h2></div>
          <?php if (empty($data['top5'])): ?><p class="text-muted mb-0">No performance data available yet.</p>
          <?php else: ?>
            <?php foreach ($data['top5'] as $i => $p): ?>
              <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $p['player_id']; ?>" class="perf-rank-row">
                <span class="perf-rank-num">#<?php echo $i + 1; ?></span>
                <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>">
                <span class="flex-fill"><?php echo safeOut($p['full_name']); ?></span>
                <span class="perf-score-pill"><?php echo round($p['performance_score'], 1); ?></span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <div class="panel"><div class="panel-head"><h2 class="text-success">Improving (<?php echo count($data['improving']); ?>)</h2></div>
              <?php foreach ($data['improving'] as $p): ?><div class="py-1"><?php echo safeOut($p['full_name']); ?></div><?php endforeach; ?>
              <?php if (empty($data['improving'])): ?><p class="text-muted mb-0 small">None currently.</p><?php endif; ?>
            </div>
          </div>
          <div class="col-md-4">
            <div class="panel"><div class="panel-head"><h2 class="text-danger">Declining (<?php echo count($data['declining']); ?>)</h2></div>
              <?php foreach ($data['declining'] as $p): ?><div class="py-1"><?php echo safeOut($p['full_name']); ?></div><?php endforeach; ?>
              <?php if (empty($data['declining'])): ?><p class="text-muted mb-0 small">None currently.</p><?php endif; ?>
            </div>
          </div>
          <div class="col-md-4">
            <div class="panel"><div class="panel-head"><h2>Needs Improvement (<?php echo count($data['needing_improvement']); ?>)</h2></div>
              <?php foreach ($data['needing_improvement'] as $p): ?><div class="py-1"><?php echo safeOut($p['full_name']); ?></div><?php endforeach; ?>
              <?php if (empty($data['needing_improvement'])): ?><p class="text-muted mb-0 small">None currently.</p><?php endif; ?>
            </div>
          </div>
        </div>

      <?php endif; ?>

<?php if ($isAdmin): ?>
    </div>
  </main>
</div>
<?php else: ?>
</div>
<?php endif; ?>

<script>
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
  <?php if ($myRole === 'admin'): ?>
  window.perfCharts = {
    bySport: <?php echo json_encode($data['chart_by_sport']); ?>,
    top10: <?php echo json_encode($data['chart_top10']); ?>,
    distribution: <?php echo json_encode($data['chart_distribution']); ?>,
    ratingDistribution: <?php echo json_encode($data['chart_rating_distribution']); ?>
  };
  <?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/performance.js"></script>
</body>
</html>
