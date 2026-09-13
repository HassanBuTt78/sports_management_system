<?php
/**
 * ============================================================
 * player/matches/index.php
 * ------------------------------------------------------------
 * "View upcoming matches. View own match schedule. View
 * results." Scoped to the player's own sport.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('player');

$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$myTeamId = (int) ($_SESSION['team_id'] ?? 0);
$matches = [];

if ($conn && $mySportId) {
    $stmt = $conn->prepare(
        'SELECT m.*, ta.team_name AS team_a_name, tb.team_name AS team_b_name
         FROM matches m LEFT JOIN teams ta ON m.team_one = ta.team_id LEFT JOIN teams tb ON m.team_two = tb.team_id
         WHERE m.sport_id = ? ORDER BY m.match_date DESC'
    );
    $stmt->bind_param('i', $mySportId);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$upcoming = array_filter($matches, fn($m) => $m['status'] === 'Upcoming');
$past = array_filter($matches, fn($m) => in_array($m['status'], ['Completed', 'Cancelled'], true));

$pageTitle = 'Matches';
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
  <?php require_once __DIR__ . '/../../includes/player_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/player_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-trophy me-2"></i>Matches</h1><p class="text-muted">Your sport's schedule and results.</p></div>
    <a href="<?php echo BASE_URL; ?>/player/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
  </div>

  <h2 class="h5 mb-3">Upcoming</h2>
  <?php if (empty($upcoming)): ?>
    <div class="panel text-center py-4 mb-4"><p class="text-muted mb-0">No upcoming matches.</p></div>
  <?php else: ?>
    <div class="row g-3 mb-4">
      <?php foreach ($upcoming as $m):
        $isMine = $myTeamId && ((int) $m['team_one'] === $myTeamId || (int) $m['team_two'] === $myTeamId);
      ?>
        <div class="col-md-6 col-lg-4">
          <a href="<?php echo BASE_URL; ?>/player/matches/view.php?id=<?php echo $m['match_id']; ?>" class="event-mini-card d-block">
            <span class="event-mini-badge badge-<?php echo $m['status']; ?>">Scheduled</span>
            <?php if ($isMine): ?><span class="badge bg-primary ms-1">Your Match</span><?php endif; ?>
            <h3 style="font-size:1rem; margin:.5em 0 .3em;"><?php echo safeOut($m['team_a_name'] ?? 'TBD') . ($m['team_b_name'] ? ' vs ' . safeOut($m['team_b_name']) : ''); ?></h3>
            <ul class="event-mini-meta">
              <li><i class="fa-solid fa-location-dot"></i><?php echo safeOut($m['venue'] ?? 'TBA'); ?></li>
              <li><i class="fa-solid fa-calendar-day"></i><?php echo date('M j, Y', strtotime($m['match_date'])); ?></li>
            </ul>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 class="h5 mb-3">Past Results</h2>
  <?php if (empty($past)): ?>
    <div class="panel text-center py-4"><p class="text-muted mb-0">No past matches yet.</p></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($past as $m): ?>
        <div class="col-md-6 col-lg-4">
          <a href="<?php echo BASE_URL; ?>/player/matches/view.php?id=<?php echo $m['match_id']; ?>" class="event-mini-card d-block">
            <span class="event-mini-badge badge-<?php echo $m['status']; ?>"><?php echo safeOut($m['status']); ?></span>
            <h3 style="font-size:1rem; margin:.5em 0 .3em;"><?php echo safeOut($m['team_a_name'] ?? 'TBD') . ($m['team_b_name'] ? ' vs ' . safeOut($m['team_b_name']) : ''); ?></h3>
            <ul class="event-mini-meta">
              <li><i class="fa-solid fa-calendar-day"></i><?php echo date('M j, Y', strtotime($m['match_date'])); ?></li>
              <?php if ($m['result_summary']): ?><li><i class="fa-solid fa-medal"></i><?php echo safeOut($m['result_summary']); ?></li><?php endif; ?>
            </ul>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
