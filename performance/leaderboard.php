<?php
/**
 * ============================================================
 * performance/leaderboard.php
 * ------------------------------------------------------------
 * §9 Top 10 (overall + per sport) with §10's rank arrows.
 * "Do not rank players with no performance data above players
 * who have valid performance data" — enforced simply by only
 * ever selecting from performance_analysis WHERE
 * performance_score IS NOT NULL, which is the only place ranks
 * are assigned in the first place (see recalculateAllPerformance()).
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');

$myRole = currentRole();
$sportFilter = cleanInput($_GET['sport'] ?? 'all');

$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];

$rows = [];
if ($conn) {
    $sql = "SELECT pa.*, p.full_name, p.profile_image, s.sport_name, t.team_name FROM performance_analysis pa
            JOIN players p ON pa.player_id = p.player_id JOIN sports s ON pa.sport_id = s.sport_id
            LEFT JOIN teams t ON pa.team_id = t.team_id
            WHERE pa.performance_score IS NOT NULL";
    if ($sportFilter !== 'all' && ctype_digit($sportFilter)) {
        $sql .= ' AND pa.sport_id = ' . (int) $sportFilter;
    }
    $sql .= ' ORDER BY pa.performance_score DESC LIMIT 10';
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Leaderboard';
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
  <?php require_once __DIR__ . '/../includes/role_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/role_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-ranking-star me-2"></i>Leaderboard</h1><p class="text-muted">Top 10 by performance score.</p></div>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="panel mb-3">
    <div class="d-flex gap-2 flex-wrap">
      <a href="?sport=all" class="btn btn-sm <?php echo $sportFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Overall</a>
      <?php foreach ($sports as $s): ?>
        <a href="?sport=<?php echo $s['sport_id']; ?>" class="btn btn-sm <?php echo $sportFilter == $s['sport_id'] ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo safeOut($s['sport_name']); ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <?php if (empty($rows)): ?>
      <p class="text-muted mb-0">No performance data available yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Rank</th><th>Player</th><th>Sport</th><th>Team</th><th>Matches</th><th>Avg Rating</th><th>Performance Score</th><th>Trend</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $i => $r): ?>
              <tr>
                <td><span class="perf-rank-num">#<?php echo $i + 1; ?></span></td>
                <td>
                  <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $r['player_id']; ?>" class="mini-profile text-decoration-none">
                    <img src="<?php echo $r['profile_image'] ? UPLOADS_URL . '/' . $r['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                    <?php echo safeOut($r['full_name']); ?>
                  </a>
                </td>
                <td><?php echo safeOut($r['sport_name']); ?></td>
                <td><?php echo safeOut($r['team_name'] ?? '—'); ?></td>
                <td><?php echo (int) $r['matches_played']; ?></td>
                <td><?php echo $r['average_rating'] ? round($r['average_rating'], 2) : '—'; ?></td>
                <td><span class="perf-score-pill"><?php echo round($r['performance_score'], 1); ?></span></td>
                <td>
                  <?php if ($r['rank_change'] === null && $r['previous_rank'] === null): ?><span class="badge bg-info">NEW</span>
                  <?php elseif ($r['rank_change'] > 0): ?><span class="text-success">↑ <?php echo $r['rank_change']; ?></span>
                  <?php elseif ($r['rank_change'] < 0): ?><span class="text-danger">↓ <?php echo abs($r['rank_change']); ?></span>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
