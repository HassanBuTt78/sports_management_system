<?php
/**
 * ============================================================
 * admin/matches/statistics.php
 * ------------------------------------------------------------
 * "View Match Statistics" (admin). Chart.js: Goals, Runs,
 * Points, Wins, Losses, Average Rating.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$statsBySport = ['labels' => [], 'goals' => [], 'runs' => [], 'points' => []];
$winsLosses = ['labels' => [], 'wins' => [], 'losses' => []];
$avgRating = 0;

if ($conn) {
    $res = $conn->query(
        "SELECT s.sport_name,
                COALESCE(SUM(ps.goals),0) g, COALESCE(SUM(ps.runs),0) r, COALESCE(SUM(ps.points),0) p
         FROM sports s
         LEFT JOIN matches m ON m.sport_id = s.sport_id
         LEFT JOIN player_scores ps ON ps.match_id = m.match_id
         GROUP BY s.sport_id ORDER BY s.sport_name"
    );
    while ($row = $res->fetch_assoc()) {
        $statsBySport['labels'][] = $row['sport_name'];
        $statsBySport['goals'][] = (int) $row['g'];
        $statsBySport['runs'][] = (int) $row['r'];
        $statsBySport['points'][] = (int) $row['p'];
    }

    $res = $conn->query(
        'SELECT t.team_name,
                SUM(CASE WHEN m.winner_team = t.team_id THEN 1 ELSE 0 END) wins,
                SUM(CASE WHEN m.status = "Completed" AND m.winner_team IS NOT NULL AND m.winner_team != t.team_id AND (m.team_one = t.team_id OR m.team_two = t.team_id) THEN 1 ELSE 0 END) losses
         FROM teams t
         LEFT JOIN matches m ON (m.team_one = t.team_id OR m.team_two = t.team_id) AND m.status = "Completed"
         GROUP BY t.team_id HAVING (wins + losses) > 0
         ORDER BY wins DESC LIMIT 8'
    );
    while ($row = $res->fetch_assoc()) {
        $winsLosses['labels'][] = $row['team_name'];
        $winsLosses['wins'][] = (int) $row['wins'];
        $winsLosses['losses'][] = (int) $row['losses'];
    }

    $avgRow = $conn->query('SELECT AVG(rating) a FROM player_ratings')->fetch_assoc();
    $avgRating = $avgRow['a'] ? round((float) $avgRow['a'], 2) : 0;
}

$pageTitle = 'Match Statistics';
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
        <div><h1>Match Statistics</h1><p>Goals, runs, points, wins/losses, and average rating across all sports.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
      </div>

      <div class="stat-grid" style="grid-template-columns:repeat(1,1fr); max-width:260px;">
        <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-star"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $avgRating; ?>">0</div><div class="stat-card-label">Average Rating</div></div>
      </div>

      <div class="grid-charts">
        <div class="panel">
          <div class="panel-head"><h2>Goals / Runs / Points by Sport</h2></div>
          <div class="chart-wrap"><canvas id="chartSportStats"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Wins vs Losses (Top Teams)</h2></div>
          <div class="chart-wrap"><canvas id="chartWinsLosses"></canvas></div>
        </div>
      </div>

    </div>
  <?php
  $extraFooterScripts = '
    <script>
      window.matchStatsCharts = {
        sportStats: ' . json_encode($statsBySport) . ',
        winsLosses: ' . json_encode($winsLosses) . '
      };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
      const sportStats = window.matchStatsCharts.sportStats;
      const winsLosses = window.matchStatsCharts.winsLosses;
      new Chart(document.getElementById("chartSportStats"), {
        type: "bar",
        data: { labels: sportStats.labels, datasets: [
          { label: "Goals", data: sportStats.goals, backgroundColor: "#0B5ED7" },
          { label: "Runs", data: sportStats.runs, backgroundColor: "#198754" },
          { label: "Points", data: sportStats.points, backgroundColor: "#FFC107" },
        ] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
      });
      new Chart(document.getElementById("chartWinsLosses"), {
        type: "bar",
        data: { labels: winsLosses.labels, datasets: [
          { label: "Wins", data: winsLosses.wins, backgroundColor: "#198754" },
          { label: "Losses", data: winsLosses.losses, backgroundColor: "#c1443c" },
        ] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
      });
    </script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
