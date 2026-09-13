<?php
/**
 * ============================================================
 * admin/team/statistics.php
 * ------------------------------------------------------------
 * Team Statistics with Chart.js: number of players, average
 * rating, average score per team, and a computed Team Ranking
 * (ranked within each sport, since cross-sport score comparison
 * isn't meaningful — a Cricket average score and a Hockey
 * average score aren't the same unit).
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$teamRows = [];
$bySport = [];
if ($conn) {
    $res = $conn->query(
        'SELECT t.team_id, t.team_name, s.sport_name,
                (SELECT COUNT(*) FROM players p WHERE p.team_id = t.team_id) AS player_count
         FROM teams t LEFT JOIN sports s ON t.sport_id = s.sport_id
         WHERE t.status = "active" ORDER BY s.sport_name, t.team_name'
    );
    $teamRows = $res->fetch_all(MYSQLI_ASSOC);
    foreach ($teamRows as &$row) {
        $stats = getTeamStats($conn, (int) $row['team_id']);
        $row = array_merge($row, $stats);
    }
    unset($row);

    // Rank within each sport by avg_score, descending.
    foreach ($teamRows as $row) {
        $bySport[$row['sport_name']][] = $row;
    }
    foreach ($bySport as $sportName => &$group) {
        usort($group, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);
    }
    unset($group);
}

$chartLabels = array_column($teamRows, 'team_name');
$chartPlayers = array_column($teamRows, 'player_count');
$chartRatings = array_column($teamRows, 'avg_rating');
$chartScores  = array_column($teamRows, 'avg_score');

$pageTitle = 'Team Statistics';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/team.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Team Statistics</h1><p>Player counts, ratings, and rankings across every active team.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/team/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Teams</a>
      </div>

      <div class="grid-charts">
        <div class="panel">
          <div class="panel-head"><h2>Players Per Team</h2></div>
          <div class="chart-wrap"><canvas id="chartPlayersPerTeam"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Average Team Rating</h2></div>
          <div class="chart-wrap"><canvas id="chartAvgRating"></canvas></div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Average Team Score</h2></div>
        <div class="chart-wrap"><canvas id="chartAvgScore"></canvas></div>
      </div>

      <!-- ============ TEAM RANKING ============ -->
      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-ranking-star me-2"></i>Team Ranking <span class="panel-sub">(by sport, avg. score)</span></h2></div>
        <?php if (empty($bySport)): ?>
          <p class="text-muted mb-0">No active teams yet.</p>
        <?php else: ?>
          <?php foreach ($bySport as $sportName => $group): ?>
            <h6 class="mt-3 mb-2"><?php echo safeOut($sportName); ?></h6>
            <div class="table-responsive mb-3">
              <table class="table align-middle">
                <thead><tr><th>Rank</th><th>Team</th><th>Players</th><th>Avg Rating</th><th>Avg Score</th><th>W-L-D</th></tr></thead>
                <tbody>
                  <?php foreach ($group as $i => $t): ?>
                    <tr>
                      <td><strong>#<?php echo $i + 1; ?></strong></td>
                      <td><a href="<?php echo BASE_URL; ?>/admin/team/view.php?id=<?php echo $t['team_id']; ?>"><?php echo safeOut($t['team_name']); ?></a></td>
                      <td><?php echo $t['player_count']; ?></td>
                      <td><?php echo $t['avg_rating'] ?: '—'; ?></td>
                      <td><?php echo $t['avg_score'] ?: '—'; ?></td>
                      <td><?php echo $t['wins']; ?>-<?php echo $t['losses']; ?>-<?php echo $t['draws']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  <?php
  $extraFooterScripts = '
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
      window.teamStatsCharts = {
        labels: ' . json_encode($chartLabels) . ',
        players: ' . json_encode($chartPlayers) . ',
        ratings: ' . json_encode($chartRatings) . ',
        scores: ' . json_encode($chartScores) . '
      };
    </script>
    <script src="' . BASE_URL . '/assets/js/team.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
