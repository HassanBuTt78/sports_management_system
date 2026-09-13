<?php
/**
 * ============================================================
 * admin/player_performance.php
 * ------------------------------------------------------------
 * Dual mode:
 *  - no ?id= : Admin Performance Dashboard (§9/§10/§11) — total
 *    rated players, Top 10, highest rating/score, averages,
 *    distribution, sport filter, charts. Admin sees ALL sports
 *    without opening each Coach's dashboard.
 *  - ?id=X   : one player's full detail (§17) — profile, team,
 *    coach, sport, match history, scores, ratings, trend graph.
 * Uses the simple average-score/average-rating system (reusing
 * player_scores.custom_score + player_ratings.rating directly),
 * matching this module's brief — distinct from, and independent
 * of, the separate weighted performance_engine.php system.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin');

$playerId = (int) ($_GET['id'] ?? 0);

if ($playerId > 0) {
    // ============ SINGLE PLAYER DETAIL ============
    $stmt = $conn->prepare(
        'SELECT p.*, s.sport_name, t.team_name, c.full_name AS coach_name FROM players p
         JOIN sports s ON p.sport_id = s.sport_id LEFT JOIN teams t ON p.team_id = t.team_id
         LEFT JOIN coaches c ON p.coach_id = c.coach_id WHERE p.player_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$player) {
        redirectTo(BASE_URL . '/admin/player_performance.php');
    }

    $stmt = $conn->prepare(
        "SELECT m.match_id, m.match_title, m.match_date, m.team_one, m.team_two,
                (SELECT team_name FROM teams WHERE team_id = m.team_one) AS team_a_name,
                (SELECT team_name FROM teams WHERE team_id = m.team_two) AS team_b_name,
                ps.custom_score, pr.rating, pr.review, c2.full_name AS coach_name
         FROM matches m
         LEFT JOIN player_scores ps ON ps.match_id = m.match_id AND ps.player_id = ?
         LEFT JOIN player_ratings pr ON pr.match_id = m.match_id AND pr.player_id = ?
         LEFT JOIN coaches c2 ON pr.coach_id = c2.coach_id
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
    $chronological = array_reverse($history);
}

// ============ DASHBOARD (also computed for the "back to dashboard" link's data, sport filter) ============
$sportFilter = (int) ($_GET['sport_id'] ?? 0);
$sports = $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC);

$dash = ['total_rated' => 0, 'highest_rating' => null, 'highest_score' => null, 'avg_rating' => null, 'avg_score' => null];
$top10 = [];
$distribution = [];

if (!$playerId) {
    $sportWhere = $sportFilter > 0 ? "AND p.sport_id = {$sportFilter}" : '';

    $sql = "SELECT p.player_id, p.full_name, p.profile_image, s.sport_name, t.team_name,
                   AVG(pr.rating) AS avg_rating, AVG(ps.custom_score) AS avg_score,
                   COUNT(DISTINCT pr.match_id) AS matches_rated
            FROM players p
            JOIN sports s ON p.sport_id = s.sport_id
            LEFT JOIN teams t ON p.team_id = t.team_id
            LEFT JOIN player_ratings pr ON pr.player_id = p.player_id
            LEFT JOIN player_scores ps ON ps.player_id = p.player_id AND ps.match_id = pr.match_id
            WHERE 1=1 {$sportWhere}
            GROUP BY p.player_id
            HAVING avg_rating IS NOT NULL
            ORDER BY avg_rating DESC, avg_score DESC
            LIMIT 10";
    $top10 = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    $countSql = "SELECT COUNT(DISTINCT pr.player_id) c FROM player_ratings pr JOIN players p ON pr.player_id = p.player_id WHERE 1=1 {$sportWhere}";
    $dash['total_rated'] = (int) $conn->query($countSql)->fetch_assoc()['c'];

    $ratingSql = "SELECT MAX(pr.rating) hi, AVG(pr.rating) avgr FROM player_ratings pr JOIN players p ON pr.player_id = p.player_id WHERE 1=1 {$sportWhere}";
    $r = $conn->query($ratingSql)->fetch_assoc();
    $dash['highest_rating'] = $r['hi'];
    $dash['avg_rating'] = $r['avgr'];

    $scoreSql = "SELECT MAX(ps.custom_score) hi, AVG(ps.custom_score) avgs FROM player_scores ps JOIN players p ON ps.player_id = p.player_id WHERE 1=1 {$sportWhere}";
    $sc = $conn->query($scoreSql)->fetch_assoc();
    $dash['highest_score'] = $sc['hi'];
    $dash['avg_score'] = $sc['avgs'];

    // Distribution: classify each rated player's avg rating.
    $allRatedSql = "SELECT p.player_id, AVG(pr.rating) avg_rating FROM players p JOIN player_ratings pr ON pr.player_id = p.player_id WHERE 1=1 {$sportWhere} GROUP BY p.player_id";
    $allRated = $conn->query($allRatedSql)->fetch_all(MYSQLI_ASSOC);
    $levelCounts = ['Excellent' => 0, 'Very Good' => 0, 'Good' => 0, 'Average' => 0, 'Needs Improvement' => 0];
    foreach ($allRated as $r2) {
        $lvl = classifyStarPerformanceLevel((float) $r2['avg_rating']);
        if (isset($levelCounts[$lvl])) $levelCounts[$lvl]++;
    }
    $distribution = $levelCounts;
}

$pageTitle = $playerId ? 'Player Performance' : 'Player Performance Dashboard';
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
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>
    <div class="admin-content">

      <?php if ($playerId && $player): ?>
        <!-- ============ SINGLE PLAYER DETAIL ============ -->
        <div class="page-heading">
          <div><h1>Player Performance</h1></div>
          <div class="d-flex gap-2">
            <a href="<?php echo BASE_URL; ?>/admin/player/view.php?id=<?php echo $playerId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-id-card me-1"></i>Full Profile</a>
            <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $playerId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chart-pie me-1"></i>Advanced Analysis</a>
            <a href="<?php echo BASE_URL; ?>/admin/player_performance.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
          </div>
        </div>

        <div class="panel perf-profile-header">
          <img src="<?php echo $player['profile_image'] ? UPLOADS_URL . '/' . $player['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" class="perf-profile-photo">
          <div>
            <h2><?php echo safeOut($player['full_name']); ?></h2>
            <p class="text-muted mb-0"><?php echo safeOut(formatEmployeeId('PL', $playerId)); ?> &middot; <?php echo safeOut($player['sport_name']); ?> &middot; <?php echo safeOut($player['team_name'] ?? 'No Team'); ?> &middot; Coach: <?php echo safeOut($player['coach_name'] ?? 'Unassigned'); ?></p>
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
            <div class="panel-head"><h2>Performance Trend</h2></div>
            <div class="chart-wrap"><canvas id="chartTrend"></canvas></div>
          </div>
          <div class="panel">
            <div class="panel-head"><h2>Match History</h2></div>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Match</th><th>Date</th><th>Score</th><th>Rating</th><th>Coach Comment</th></tr></thead>
                <tbody>
                  <?php foreach ($history as $h): ?>
                    <tr>
                      <td><?php echo safeOut($h['match_title'] ?: (($h['team_a_name'] ?? 'TBD') . ($h['team_b_name'] ? ' vs ' . $h['team_b_name'] : ''))); ?></td>
                      <td><?php echo date('M j, Y', strtotime($h['match_date'])); ?></td>
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

        <script>
          window.perfHistory = <?php echo json_encode(array_map(fn($h) => [
              'label' => $h['match_title'] ?: date('M j', strtotime($h['match_date'])),
              'score' => $h['custom_score'] !== null ? round((float) $h['custom_score'], 1) : null,
          ], $chronological)); ?>;
        </script>

      <?php else: ?>
        <!-- ============ ADMIN PERFORMANCE DASHBOARD ============ -->
        <div class="page-heading">
          <div><h1><i class="fa-solid fa-star me-2"></i>Player Performance</h1><p>Admin sees every rated player, across all sports, without opening each Coach's dashboard.</p></div>
        </div>

        <div class="panel mb-3">
          <div class="d-flex gap-2 flex-wrap">
            <a href="?sport_id=0" class="btn btn-sm <?php echo $sportFilter === 0 ? 'btn-primary' : 'btn-outline-secondary'; ?>">All Sports</a>
            <?php foreach ($sports as $s): ?>
              <a href="?sport_id=<?php echo $s['sport_id']; ?>" class="btn btn-sm <?php echo $sportFilter === (int) $s['sport_id'] ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo safeOut($s['sport_name']); ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="stat-grid" style="grid-template-columns:repeat(5,1fr);">
          <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-users"></i></div><div class="stat-card-value"><?php echo $dash['total_rated']; ?></div><div class="stat-card-label">Total Rated Players</div></div>
          <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-star"></i></div><div class="stat-card-value"><?php echo $dash['highest_rating'] !== null ? round($dash['highest_rating'], 1) : '—'; ?></div><div class="stat-card-label">Highest Rating</div></div>
          <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-chart-simple"></i></div><div class="stat-card-value"><?php echo $dash['highest_score'] !== null ? round($dash['highest_score'], 1) : '—'; ?></div><div class="stat-card-label">Highest Score</div></div>
          <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-star-half-stroke"></i></div><div class="stat-card-value"><?php echo $dash['avg_rating'] !== null ? round($dash['avg_rating'], 2) : '—'; ?></div><div class="stat-card-label">Average Rating</div></div>
          <div class="stat-card" style="background:linear-gradient(135deg,#6f42c1,#4a2a8a);"><div class="stat-card-icon"><i class="fa-solid fa-chart-line"></i></div><div class="stat-card-value"><?php echo $dash['avg_score'] !== null ? round($dash['avg_score'], 1) : '—'; ?></div><div class="stat-card-label">Average Score</div></div>
        </div>

        <div class="grid-charts">
          <div class="panel">
            <div class="panel-head"><h2>Top 10 Players</h2></div>
            <?php if (empty($top10)): ?><p class="text-muted mb-0">No performance data available yet.</p><?php else: ?>
              <div class="chart-wrap"><canvas id="chartTop10"></canvas></div>
            <?php endif; ?>
          </div>
          <div class="panel">
            <div class="panel-head"><h2>Performance Distribution</h2></div>
            <div class="chart-wrap"><canvas id="chartDistribution"></canvas></div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Top 10 Players <span class="panel-sub">(<?php echo safeOut($sportFilter ? ($sports[array_search($sportFilter, array_column($sports, 'sport_id'))]['sport_name'] ?? '') : 'All Sports'); ?>)</span></h2></div>
          <?php if (empty($top10)): ?>
            <p class="text-muted mb-0">No performance data available yet.</p>
          <?php else: ?>
            <?php foreach ($top10 as $i => $p): ?>
              <a href="<?php echo BASE_URL; ?>/admin/player_performance.php?id=<?php echo $p['player_id']; ?>" class="perf-rank-row">
                <span class="perf-rank-num">#<?php echo $i + 1; ?></span>
                <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
                <span class="flex-fill"><?php echo safeOut($p['full_name']); ?> <small class="text-muted">(<?php echo safeOut($p['sport_name']); ?> · <?php echo safeOut($p['team_name'] ?? '—'); ?>)</small></span>
                <span class="perf-score-pill"><?php echo round($p['avg_rating'], 2); ?>/5</span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <script>
          window.perfSimpleCharts = {
            top10: <?php echo json_encode(array_map(fn($p) => ['name' => $p['full_name'], 'rating' => round((float) $p['avg_rating'], 2)], $top10)); ?>,
            distribution: <?php echo json_encode($distribution); ?>
          };
        </script>
      <?php endif; ?>

    </div>
  <?php
  $extraFooterScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="' . BASE_URL . '/assets/js/performance.js"></script><script src="' . BASE_URL . '/assets/js/admin_player_performance.js"></script>';
  require_once __DIR__ . '/../includes/admin_footer.php';
  ?>
