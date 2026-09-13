<?php
/**
 * ============================================================
 * admin/coach/index.php
 * ------------------------------------------------------------
 * Coach Management listing. Stat cards + a DataTable with
 * Excel/PDF/Print export, mirroring Module 5's Player listing.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$coaches = [];
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$perSport = [];

if ($conn) {
    $stats['total']    = (int) $conn->query('SELECT COUNT(*) c FROM coaches')->fetch_assoc()['c'];
    $stats['active']   = (int) $conn->query("SELECT COUNT(*) c FROM coaches WHERE status='active'")->fetch_assoc()['c'];
    $stats['inactive'] = (int) $conn->query("SELECT COUNT(*) c FROM coaches WHERE status='inactive'")->fetch_assoc()['c'];

    $res = $conn->query(
        'SELECT s.sport_name, COUNT(c.coach_id) AS c FROM sports s
         LEFT JOIN coaches c ON c.sport_id = s.sport_id GROUP BY s.sport_id ORDER BY s.sport_name'
    );
    $perSport = $res->fetch_all(MYSQLI_ASSOC);

    $res = $conn->query(
        'SELECT c.coach_id, c.full_name, c.email, c.phone, c.qualification, c.experience, c.status, c.profile_image,
                s.sport_name, COUNT(p.player_id) AS assigned_players
         FROM coaches c
         LEFT JOIN sports s ON c.sport_id = s.sport_id
         LEFT JOIN players p ON p.coach_id = c.coach_id
         GROUP BY c.coach_id ORDER BY c.created_at DESC'
    );
    $coaches = $res->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Coach Management';
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
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.3/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/coach.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Coach Management</h1><p>Add, update, and manage coaches across all four sports.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/coach/create.php" class="btn btn-auth-submit" style="width:auto;">
          <i class="fa-solid fa-user-plus me-2"></i>Add New Coach
        </a>
      </div>

      <?php if (!$conn): ?>
        <div class="alert alert-warning">Database not connected — import the schema files first (including <code>schema_module6.sql</code>).</div>
      <?php endif; ?>

      <!-- ============ STAT CARDS ============ -->
      <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
        <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-whistle"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total']; ?>">0</div>
          <div class="stat-card-label">Total Coaches</div></div>
        <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['active']; ?>">0</div>
          <div class="stat-card-label">Active Coaches</div></div>
        <div class="stat-card" style="background:linear-gradient(135deg,#c1443c,#8a2d27);"><div class="stat-card-icon"><i class="fa-solid fa-circle-xmark"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['inactive']; ?>">0</div>
          <div class="stat-card-label">Inactive Coaches</div></div>
      </div>

      <!-- ============ COACHES PER SPORT ============ -->
      <div class="panel">
        <div class="panel-head"><h2>Coaches Per Sport</h2></div>
        <div class="row g-3">
          <?php foreach ($perSport as $s): ?>
            <div class="col-md-3 col-6">
              <div class="health-item">
                <div class="health-value"><?php echo (int) $s['c']; ?></div>
                <div class="health-label"><?php echo safeOut($s['sport_name']); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ============ COACHES TABLE ============ -->
      <div class="panel">
        <div class="panel-head"><h2>All Coaches <span class="panel-sub">(<?php echo count($coaches); ?> total)</span></h2></div>
        <div class="table-responsive">
          <table class="table align-middle coach-table" id="coachesTable">
            <thead>
              <tr>
                <th>Photo</th><th>Coach ID</th><th>Name</th><th>Sport</th><th>Qualification</th>
                <th>Experience</th><th>Players Assigned</th><th>Status</th><th>Email</th><th>Phone</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($coaches as $c): ?>
                <tr>
                  <td><img src="<?php echo $c['profile_image'] ? UPLOADS_URL . '/' . $c['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:34px; height:34px; border-radius:50%; object-fit:cover;"></td>
                  <td><?php echo safeOut(formatEmployeeId('COA', (int) $c['coach_id'])); ?></td>
                  <td><?php echo safeOut($c['full_name']); ?></td>
                  <td><?php echo safeOut($c['sport_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($c['qualification'] ?? '—'); ?></td>
                  <td><?php echo safeOut($c['experience'] ?? '—'); ?></td>
                  <td><?php echo (int) $c['assigned_players']; ?></td>
                  <td><span class="status-pill status-<?php echo $c['status']; ?>"><?php echo safeOut($c['status']); ?></span></td>
                  <td><?php echo safeOut($c['email']); ?></td>
                  <td><?php echo safeOut($c['phone'] ?? '—'); ?></td>
                  <td class="text-nowrap">
                    <a href="<?php echo BASE_URL; ?>/admin/coach/view.php?id=<?php echo $c['coach_id']; ?>" class="table-action-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/coach/profile.php?id=<?php echo $c['coach_id']; ?>" class="table-action-btn" title="Full Profile"><i class="fa-solid fa-id-card"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/coach/edit.php?id=<?php echo $c['coach_id']; ?>" class="table-action-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/coach/assign_players.php?coach_id=<?php echo $c['coach_id']; ?>" class="table-action-btn" title="Assign Players"><i class="fa-solid fa-people-arrows"></i></a>
                    <button type="button" class="table-action-btn reset-password-btn" data-coach-id="<?php echo $c['coach_id']; ?>" title="Reset Password"><i class="fa-solid fa-key"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  <?php
  $extraFooterScripts = '
    <script src="https://cdn.datatables.net/buttons/2.4.3/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.72/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.72/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.print.min.js"></script>
    <script src="' . BASE_URL . '/assets/js/coach.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
