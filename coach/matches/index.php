<?php
/**
 * ============================================================
 * coach/matches/index.php
 * ------------------------------------------------------------
 * "Coach can view only matches of their assigned sport" —
 * scoped in SQL via the coach's own session sport_id.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
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

$pageTitle = 'My Sport Matches';
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
  <?php require_once __DIR__ . '/../../includes/coach_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/coach_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-trophy me-2"></i>My Sport's Matches</h1><p class="text-muted">Enter scores, rate players, and declare results for matches in your sport.</p></div>
    <a href="<?php echo BASE_URL; ?>/coach/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
  </div>

  <?php if (empty($matches)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No matches scheduled for your sport yet.</p></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($matches as $m): ?>
        <div class="col-md-6 col-lg-4">
          <div class="panel h-100">
            <span class="event-mini-badge badge-<?php echo $m['status']; ?>"><?php echo $m['status'] === 'Upcoming' ? 'Scheduled' : safeOut($m['status']); ?></span>
            <h3 style="font-size:1.02rem; margin:.5em 0 .3em;"><?php echo safeOut($m['team_a_name'] ?? 'TBD') . ($m['team_b_name'] ? ' vs ' . safeOut($m['team_b_name']) : ''); ?></h3>
            <p class="text-muted mb-1" style="font-size:.85rem;"><i class="fa-solid fa-location-dot me-1"></i><?php echo safeOut($m['venue'] ?? 'TBA'); ?></p>
            <p class="text-muted mb-3" style="font-size:.85rem;"><i class="fa-solid fa-calendar-day me-1"></i><?php echo date('M j, Y', strtotime($m['match_date'])); ?></p>
            <div class="d-flex gap-2 flex-wrap">
              <a href="<?php echo BASE_URL; ?>/coach/matches/score_entry.php?id=<?php echo $m['match_id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">Scores</a>
              <a href="<?php echo BASE_URL; ?>/coach/matches/player_rating.php?id=<?php echo $m['match_id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">Ratings</a>
              <a href="<?php echo BASE_URL; ?>/coach/matches/results.php?id=<?php echo $m['match_id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">Results</a>
            </div>
          </div>
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
