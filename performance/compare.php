<?php
/**
 * ============================================================
 * performance/compare.php
 * ------------------------------------------------------------
 * §16/§17: compare up to 3 players. Coaches only see their own
 * sport's players in the picker (server-side enforced, not just
 * hidden in the UI). Admin can pick across sports — but §17 is
 * explicit that raw sport stats must never be compared directly
 * across sports, so only the normalized performance_score (plus
 * each player's own sport clearly labeled) is shown side by side;
 * sport-specific stat cards below are grouped per player, never
 * merged into one cross-sport row.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];

$eligiblePlayers = [];
if ($conn && $myRole === 'admin') {
    $eligiblePlayers = $conn->query('SELECT player_id, full_name FROM players ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
} elseif ($conn && $myRole === 'coach') {
    $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
    $stmt->bind_param('i', $myId);
    $stmt->execute();
    $mySportId = $stmt->get_result()->fetch_assoc()['sport_id'] ?? 0;
    $stmt->close();
    $stmt = $conn->prepare('SELECT player_id, full_name FROM players WHERE sport_id = ? ORDER BY full_name');
    $stmt->bind_param('i', $mySportId);
    $stmt->execute();
    $eligiblePlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$eligibleIds = array_column($eligiblePlayers, 'player_id');

$selectedIds = array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))));
$selectedIds = array_slice(array_values(array_intersect($selectedIds, $eligibleIds)), 0, 3);

$players = [];
foreach ($selectedIds as $pid) {
    $stmt = $conn->prepare('SELECT pa.*, p.full_name, p.profile_image, s.sport_name FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id JOIN sports s ON pa.sport_id = s.sport_id WHERE pa.player_id = ? LIMIT 1');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $players[] = $row;
}

$pageTitle = 'Compare Players';
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
    <div><h1><i class="fa-solid fa-scale-balanced me-2"></i>Compare Players</h1><p class="text-muted">Select up to 3 players to compare.</p></div>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="panel mb-3">
    <form method="GET" id="compareForm">
      <label class="form-label fw-semibold">Choose up to 3 players</label>
      <select name="ids_select" id="playerPicker" multiple class="form-select" size="8">
        <?php foreach ($eligiblePlayers as $p): ?>
          <option value="<?php echo $p['player_id']; ?>" <?php echo in_array((int) $p['player_id'], $selectedIds, true) ? 'selected' : ''; ?>><?php echo safeOut($p['full_name']); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="ids" id="idsInput" value="<?php echo safeOut(implode(',', $selectedIds)); ?>">
      <button type="submit" class="btn btn-primary btn-sm mt-2">Compare</button>
    </form>
  </div>

  <?php if (empty($players)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">Select players above to compare.</p></div>
  <?php else: ?>
    <div class="panel">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Metric</th><?php foreach ($players as $p): ?><th><?php echo safeOut($p['full_name']); ?> <small class="text-muted d-block"><?php echo safeOut($p['sport_name']); ?></small></th><?php endforeach; ?></tr></thead>
          <tbody>
            <tr><td>Performance Score</td><?php foreach ($players as $p): ?><td><span class="perf-score-pill"><?php echo $p['performance_score'] !== null ? round($p['performance_score'], 1) : '—'; ?></span></td><?php endforeach; ?></tr>
            <tr><td>Average Rating</td><?php foreach ($players as $p): ?><td><?php echo $p['average_rating'] !== null ? round($p['average_rating'], 2) : '—'; ?></td><?php endforeach; ?></tr>
            <tr><td>Matches Played</td><?php foreach ($players as $p): ?><td><?php echo (int) $p['matches_played']; ?></td><?php endforeach; ?></tr>
            <tr><td>Wins</td><?php foreach ($players as $p): ?><td><?php echo (int) $p['matches_won']; ?></td><?php endforeach; ?></tr>
            <tr><td>Sport Statistics Score</td><?php foreach ($players as $p): ?><td><?php echo $p['sport_stats_component'] !== null ? round($p['sport_stats_component'], 1) : '—'; ?></td><?php endforeach; ?></tr>
            <tr><td>Trend</td><?php foreach ($players as $p): ?><td><span class="perf-trend-badge perf-trend-<?php echo strtolower((string) $p['trend']); ?>"><?php echo safeOut($p['trend'] ?? '—'); ?></span></td><?php endforeach; ?></tr>
          </tbody>
        </table>
      </div>
      <p class="text-muted small mb-0 mt-2"><i class="fa-solid fa-circle-info me-1"></i>Sport statistics scores are each player's own sport normalized to 0-100 — a cricket player's runs are never compared directly against a football player's goals.</p>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Score Comparison</h2></div>
      <div class="chart-wrap"><canvas id="chartCompare"></canvas></div>
    </div>
  <?php endif; ?>
</div>
<script>
  window.compareData = {
    labels: <?php echo json_encode(array_column($players, 'full_name')); ?>,
    scores: <?php echo json_encode(array_map(fn($p) => $p['performance_score'] !== null ? round((float) $p['performance_score'], 1) : 0, $players)); ?>
  };
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
