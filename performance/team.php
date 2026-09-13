<?php
/**
 * ============================================================
 * performance/team.php
 * ------------------------------------------------------------ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach');

$teamId = (int) ($_GET['id'] ?? 0);
$team = null;

if ($conn && $teamId > 0) {
    $stmt = $conn->prepare(
        'SELECT t.*, s.sport_name, tp.* FROM teams t
         JOIN sports s ON t.sport_id = s.sport_id LEFT JOIN team_performance tp ON t.team_id = tp.team_id
         WHERE t.team_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$team) {
    redirectTo(BASE_URL . '/performance/index.php');
}

$topPlayer = null;
if ($team['top_player_id']) {
    $stmt = $conn->prepare('SELECT full_name, profile_image FROM players WHERE player_id = ? LIMIT 1');
    $stmt->bind_param('i', $team['top_player_id']);
    $stmt->execute();
    $topPlayer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$players = $conn->query(
    "SELECT pa.*, p.full_name, p.profile_image FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id
     WHERE pa.team_id = {$teamId} ORDER BY pa.performance_score DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = $team['team_name'] . ' Performance';
$hasData = $team['matches_played'] !== null;
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
    <div><h1><?php echo safeOut($team['team_name']); ?></h1><p class="text-muted"><?php echo safeOut($team['sport_name']); ?><?php echo $team['team_rank'] ? ' · Rank #' . $team['team_rank'] : ''; ?></p></div>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if (!$hasData): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No performance data available yet.</p></div>
  <?php else: ?>
    <div class="stat-grid" style="grid-template-columns:repeat(5,1fr);">
      <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div><div class="stat-card-value"><?php echo $team['matches_played']; ?></div><div class="stat-card-label">Matches Played</div></div>
      <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-check"></i></div><div class="stat-card-value"><?php echo $team['wins']; ?></div><div class="stat-card-label">Wins</div></div>
      <div class="stat-card" style="background:linear-gradient(135deg,#c1443c,#8a2d27);"><div class="stat-card-icon"><i class="fa-solid fa-xmark"></i></div><div class="stat-card-value"><?php echo $team['losses']; ?></div><div class="stat-card-label">Losses</div></div>
      <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-equals"></i></div><div class="stat-card-value"><?php echo $team['draws']; ?></div><div class="stat-card-label">Draws</div></div>
      <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-percent"></i></div><div class="stat-card-value"><?php echo $team['win_percentage'] !== null ? round($team['win_percentage'], 1) . '%' : '—'; ?></div><div class="stat-card-label">Win Percentage</div></div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $team['avg_player_rating'] !== null ? round($team['avg_player_rating'], 2) : '—'; ?></div><div class="health-label">Avg Player Rating</div></div></div>
      <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $team['avg_team_performance'] !== null ? round($team['avg_team_performance'], 1) : '—'; ?></div><div class="health-label">Avg Team Performance</div></div></div>
      <div class="col-md-4"><div class="health-item"><div class="health-value" style="font-size:1rem;"><?php echo safeOut($topPlayer['full_name'] ?? '—'); ?></div><div class="health-label">Top Player</div></div></div>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2>Roster Performance</h2></div>
    <?php if (empty($players)): ?><p class="text-muted mb-0">No performance data available yet.</p>
    <?php else: ?>
      <?php foreach ($players as $p): ?>
        <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $p['player_id']; ?>" class="perf-rank-row">
          <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>">
          <span class="flex-fill"><?php echo safeOut($p['full_name']); ?></span>
          <span class="perf-score-pill"><?php echo $p['performance_score'] !== null ? round($p['performance_score'], 1) : 'No data'; ?></span>
        </a>
      <?php endforeach; ?>
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
