<?php
/**
 * ============================================================
 * coach/team/index.php
 * ------------------------------------------------------------
 * Coach's own teams. "Coach can manage ONLY the team(s) of their
 * assigned sport" — enforced with WHERE coach_id = ? (the
 * session's own coach_id), not a client-side filter. A coach can
 * never see another coach's team here, even by guessing a URL.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$teams = [];

if ($conn) {
    $stmt = $conn->prepare(
        'SELECT t.*, s.sport_name,
                (SELECT COUNT(*) FROM players p WHERE p.team_id = t.team_id) AS player_count
         FROM teams t LEFT JOIN sports s ON t.sport_id = s.sport_id
         WHERE t.coach_id = ? ORDER BY t.team_name'
    );
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'My Teams';
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
  <?php require_once __DIR__ . '/../../includes/coach_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/coach_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div>
      <h1><i class="fa-solid fa-people-group me-2"></i>My Teams</h1>
      <p class="text-muted">Teams you coach. Only your own sport's teams appear here.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/coach/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard</a>
  </div>

  <?php if (empty($teams)): ?>
    <div class="panel text-center py-5">
      <p class="text-muted mb-0">You haven't been assigned to a team yet. Contact the Administrator.</p>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($teams as $t): ?>
        <div class="col-md-6 col-lg-4">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
              <img src="<?php echo $t['team_logo'] ? UPLOADS_URL . '/' . $t['team_logo'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="" style="width:52px; height:52px; border-radius:12px; object-fit:cover;">
              <div>
                <h3 style="font-size:1.05rem; margin:0;"><?php echo safeOut($t['team_name']); ?></h3>
                <span class="text-muted" style="font-size:.82rem;"><?php echo safeOut($t['sport_name']); ?></span>
              </div>
            </div>
            <p class="mb-2"><span class="status-pill status-<?php echo $t['status']; ?>"><?php echo safeOut($t['status']); ?></span> &middot; <?php echo (int) $t['player_count']; ?> players</p>
            <a href="<?php echo BASE_URL; ?>/coach/team/view.php?id=<?php echo $t['team_id']; ?>" class="btn btn-outline-secondary btn-sm w-100">
              <i class="fa-solid fa-eye me-1"></i>View Team
            </a>
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
