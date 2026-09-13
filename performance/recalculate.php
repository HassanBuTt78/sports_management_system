<?php
/**
 * ============================================================
 * performance/recalculate.php
 * ------------------------------------------------------------ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/performance_engine.php';

requireRole('admin');

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $result = recalculateAllPerformance($conn);
        logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Manually recalculated performance ({$result['records_updated']} records)");
    }
}

$pageTitle = 'Recalculate Performance';
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11">
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

      <div class="page-heading">
        <div><h1>Recalculate Performance</h1><p>Manually re-run the performance engine for every player, team, and coach.</p></div>
        <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
      </div>

      <?php if ($result): ?>
        <div class="alert alert-success">
          <h5 class="mb-2"><i class="fa-solid fa-circle-check me-1"></i>Recalculation Complete</h5>
          <p class="mb-1">Players processed: <strong><?php echo $result['players_processed']; ?></strong></p>
          <p class="mb-1">Records updated: <strong><?php echo $result['records_updated']; ?></strong></p>
          <p class="mb-1">Completed at: <strong><?php echo safeOut($result['completed_at']); ?></strong></p>
          <p class="mb-0">Time taken: <strong><?php echo $result['seconds_taken']; ?>s</strong></p>
        </div>
      <?php endif; ?>

      <div class="panel">
        <p>This recalculates <strong>every</strong> player's performance score, re-ranks the whole leaderboard, and refreshes team and coach performance snapshots. This normally happens automatically whenever a match is marked Completed — use this only if you need to force a fresh pass (e.g. after changing weights in <code>config/performance_weights.php</code>).</p>
        <form method="POST" id="recalcForm">
          <?php echo csrfField(); ?>
          <button type="button" class="btn btn-primary" id="recalcBtn"><i class="fa-solid fa-rotate me-2"></i>Recalculate Performance</button>
        </form>
      </div>

    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.getElementById('recalcBtn').addEventListener('click', function () {
    Swal.fire({
      title: 'Recalculate all performance data?',
      text: 'This will re-process every player, team, and coach. It may take a moment.',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, recalculate',
    }).then(function (result) {
      if (result.isConfirmed) document.getElementById('recalcForm').submit();
    });
  });
</script>
</body>
</html>
