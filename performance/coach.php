<?php
/**
 * ============================================================
 * performance/coach.php
 * ------------------------------------------------------------
 * §19: "Do not punish a coach simply because they manage fewer
 * players" — every metric here is an AVERAGE, never a raw sum,
 * so a coach with 3 strong players scores the same as one with
 * 15 on avg_player_performance. Shows "Insufficient Data" per
 * metric individually rather than hiding the whole page.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$coachId = (int) ($_GET['id'] ?? 0);

// A coach may only ever view their own performance page.
if ($myRole === 'coach') {
    $coachId = $myId;
}

$coach = null;
if ($conn && $coachId > 0) {
    $stmt = $conn->prepare(
        'SELECT c.*, s.sport_name, cp.* FROM coaches c
         JOIN sports s ON c.sport_id = s.sport_id LEFT JOIN coach_performance cp ON c.coach_id = cp.coach_id
         WHERE c.coach_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $coach = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$coach) {
    redirectTo(BASE_URL . '/performance/index.php');
}

$hasData = $coach['players_managed'] !== null && (int) $coach['players_managed'] > 0;

$pageTitle = $coach['full_name'] . ' — Coach Performance';
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
    <div><h1><?php echo safeOut($coach['full_name']); ?></h1><p class="text-muted"><?php echo safeOut($coach['sport_name']); ?> Coach</p></div>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if (!$hasData): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No performance data available yet.</p></div>
  <?php else: ?>
    <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
      <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-users"></i></div><div class="stat-card-value"><?php echo $coach['players_managed']; ?></div><div class="stat-card-label">Players Managed</div></div>
      <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-chart-line"></i></div><div class="stat-card-value"><?php echo $coach['avg_player_performance'] !== null ? round($coach['avg_player_performance'], 1) : 'Insufficient Data'; ?></div><div class="stat-card-label">Avg Player Performance</div></div>
      <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-arrow-trend-up"></i></div><div class="stat-card-value"><?php echo $coach['player_improvement'] !== null ? round($coach['player_improvement'], 1) : 'Insufficient Data'; ?></div><div class="stat-card-label">Player Improvement</div></div>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $coach['team_win_percentage'] !== null ? round($coach['team_win_percentage'], 1) . '%' : 'Insufficient Data'; ?></div><div class="health-label">Team Win Percentage</div></div></div>
      <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $coach['avg_coach_rating'] !== null ? round($coach['avg_coach_rating'], 2) : 'Insufficient Data'; ?></div><div class="health-label">Avg Rating Given to Players</div></div></div>
      <div class="col-md-4"><div class="health-item"><div class="health-value"><?php echo $coach['completed_matches']; ?></div><div class="health-label">Completed Matches</div></div></div>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2>Managed Players</h2></div>
    <?php
    $stmt = $conn->prepare("SELECT pa.*, p.full_name, p.profile_image FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id WHERE p.coach_id = ? ORDER BY pa.performance_score DESC");
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    ?>
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
