<?php
/**
 * ============================================================
 * performance/sport.php
 * ------------------------------------------------------------ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach');

$sportId = (int) ($_GET['id'] ?? 0);
$sport = null;
$stats = [];

if ($conn && $sportId > 0) {
    $stmt = $conn->prepare('SELECT * FROM sports WHERE sport_id = ? LIMIT 1');
    $stmt->bind_param('i', $sportId);
    $stmt->execute();
    $sport = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$sport) {
    redirectTo(BASE_URL . '/performance/index.php');
}

$stats['players'] = (int) $conn->query('SELECT COUNT(*) c FROM players WHERE sport_id = ' . $sportId)->fetch_assoc()['c'];
$stats['teams'] = (int) $conn->query('SELECT COUNT(*) c FROM teams WHERE sport_id = ' . $sportId)->fetch_assoc()['c'];
$stats['matches'] = (int) $conn->query("SELECT COUNT(*) c FROM matches WHERE sport_id = {$sportId} AND status = 'Completed'")->fetch_assoc()['c'];
$stats['events'] = (int) $conn->query('SELECT COUNT(*) c FROM events WHERE sport_id = ' . $sportId)->fetch_assoc()['c'];
$stats['avg_performance'] = $conn->query('SELECT AVG(performance_score) a FROM performance_analysis WHERE sport_id = ' . $sportId)->fetch_assoc()['a'];
$stats['avg_rating'] = $conn->query('SELECT AVG(average_rating) a FROM performance_analysis WHERE sport_id = ' . $sportId)->fetch_assoc()['a'];
$stats['participation'] = (int) $conn->query('SELECT COUNT(*) c FROM performance_analysis WHERE sport_id = ' . $sportId . ' AND performance_score IS NOT NULL')->fetch_assoc()['c'];
$stats['wins'] = (int) $conn->query('SELECT COALESCE(SUM(matches_won),0) c FROM performance_analysis WHERE sport_id = ' . $sportId)->fetch_assoc()['c'];

$topPlayers = $conn->query(
    "SELECT pa.*, p.full_name, p.profile_image FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id
     WHERE pa.sport_id = {$sportId} AND pa.performance_score IS NOT NULL ORDER BY pa.performance_score DESC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = $sport['sport_name'] . ' Performance';
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
    <div><h1><?php echo safeOut($sport['sport_name']); ?> Performance</h1></div>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-users"></i></div><div class="stat-card-value"><?php echo $stats['players']; ?></div><div class="stat-card-label">Players</div></div>
    <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-people-group"></i></div><div class="stat-card-value"><?php echo $stats['teams']; ?></div><div class="stat-card-label">Teams</div></div>
    <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div><div class="stat-card-value"><?php echo $stats['matches']; ?></div><div class="stat-card-label">Completed Matches</div></div>
    <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-calendar-days"></i></div><div class="stat-card-value"><?php echo $stats['events']; ?></div><div class="stat-card-label">Events</div></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="health-item"><div class="health-value"><?php echo $stats['avg_performance'] ? round($stats['avg_performance'], 1) : '—'; ?></div><div class="health-label">Avg Performance</div></div></div>
    <div class="col-md-3"><div class="health-item"><div class="health-value"><?php echo $stats['avg_rating'] ? round($stats['avg_rating'], 2) : '—'; ?></div><div class="health-label">Avg Rating</div></div></div>
    <div class="col-md-3"><div class="health-item"><div class="health-value"><?php echo $stats['participation']; ?></div><div class="health-label">Participation</div></div></div>
    <div class="col-md-3"><div class="health-item"><div class="health-value"><?php echo $stats['wins']; ?></div><div class="health-label">Total Wins</div></div></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Top Players</h2></div>
    <?php if (empty($topPlayers)): ?><p class="text-muted mb-0">No performance data available yet.</p>
    <?php else: ?>
      <?php foreach ($topPlayers as $i => $p): ?>
        <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $p['player_id']; ?>" class="perf-rank-row">
          <span class="perf-rank-num">#<?php echo $i + 1; ?></span>
          <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>">
          <span class="flex-fill"><?php echo safeOut($p['full_name']); ?></span>
          <span class="perf-score-pill"><?php echo round($p['performance_score'], 1); ?></span>
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
