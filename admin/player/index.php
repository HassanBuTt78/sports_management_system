<?php
/**
 * ============================================================
 * admin/player/index.php
 * ------------------------------------------------------------
 * Player Management listing. Server-side filters (sport/coach/
 * team/status/gender via GET) narrow the SQL query; DataTables
 * then handles client-side pagination/search/sort/export on top
 * of that filtered result set.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

// ---------- Filters ----------
$filterSport  = (int) ($_GET['sport_id'] ?? 0);
$filterCoach  = (int) ($_GET['coach_id'] ?? 0);
$filterTeam   = (int) ($_GET['team_id'] ?? 0);
$filterStatus = cleanInput($_GET['status'] ?? '');
$filterGender = cleanInput($_GET['gender'] ?? '');

$sports = [];
$coaches = [];
$teams = [];
$players = [];
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'new_this_month' => 0];

if ($conn) {
    $sports  = $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC);
    $coaches = $conn->query('SELECT coach_id, full_name FROM coaches ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
    $teams   = $conn->query('SELECT team_id, team_name FROM teams ORDER BY team_name')->fetch_all(MYSQLI_ASSOC);

    // ---------- Stat cards ----------
    $stats['total']    = (int) $conn->query('SELECT COUNT(*) c FROM players')->fetch_assoc()['c'];
    $stats['active']   = (int) $conn->query("SELECT COUNT(*) c FROM players WHERE status='active'")->fetch_assoc()['c'];
    $stats['inactive'] = (int) $conn->query("SELECT COUNT(*) c FROM players WHERE status='inactive'")->fetch_assoc()['c'];
    $stats['new_this_month'] = (int) $conn->query(
        "SELECT COUNT(*) c FROM players WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())"
    )->fetch_assoc()['c'];

    // ---------- Filtered player list ----------
    $where = [];
    $types = '';
    $params = [];
    if ($filterSport > 0)          { $where[] = 'p.sport_id = ?';   $types .= 'i'; $params[] = $filterSport; }
    if ($filterCoach > 0)          { $where[] = 'p.coach_id = ?';   $types .= 'i'; $params[] = $filterCoach; }
    if ($filterTeam > 0)           { $where[] = 'p.team_id = ?';    $types .= 'i'; $params[] = $filterTeam; }
    if (in_array($filterStatus, ['active', 'inactive'], true)) { $where[] = 'p.status = ?'; $types .= 's'; $params[] = $filterStatus; }
    if (in_array($filterGender, ['male', 'female', 'other'], true)) { $where[] = 'p.gender = ?'; $types .= 's'; $params[] = $filterGender; }

    $sql = 'SELECT p.player_id, p.full_name, p.roll_no, p.email, p.phone, p.status, p.profile_image,
                   s.sport_name, t.team_name, c.full_name AS coach_name, pa.average_rating
            FROM players p
            LEFT JOIN sports s ON p.sport_id = s.sport_id
            LEFT JOIN teams t ON p.team_id = t.team_id
            LEFT JOIN coaches c ON p.coach_id = c.coach_id
            LEFT JOIN performance_analysis pa ON pa.player_id = p.player_id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.created_at DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Player Management';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/player.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Player Management</h1><p>Add, update, and manage every player across all four sports.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/player/create.php" class="btn btn-auth-submit" style="width:auto;">
          <i class="fa-solid fa-user-plus me-2"></i>Add New Player
        </a>
      </div>

      <?php if (!$conn): ?>
        <div class="alert alert-warning">Database not connected — import the schema files first.</div>
      <?php endif; ?>

      <!-- ============ STAT CARDS ============ -->
      <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-person-running"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total']; ?>">0</div>
          <div class="stat-card-label">Total Players</div></div>
        <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['active']; ?>">0</div>
          <div class="stat-card-label">Active Players</div></div>
        <div class="stat-card" style="background:linear-gradient(135deg,#c1443c,#8a2d27);"><div class="stat-card-icon"><i class="fa-solid fa-circle-xmark"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['inactive']; ?>">0</div>
          <div class="stat-card-label">Inactive Players</div></div>
        <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-user-plus"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['new_this_month']; ?>">0</div>
          <div class="stat-card-label">New This Month</div></div>
      </div>

      <!-- ============ FILTERS ============ -->
      <div class="panel">
        <div class="panel-head"><h2>Filters</h2></div>
        <form method="GET" class="row g-3">
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Sport</label>
            <select name="sport_id" class="form-select form-select-sm">
              <option value="0">All Sports</option>
              <?php foreach ($sports as $s): ?>
                <option value="<?php echo $s['sport_id']; ?>" <?php echo $filterSport === (int) $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Coach</label>
            <select name="coach_id" class="form-select form-select-sm">
              <option value="0">All Coaches</option>
              <?php foreach ($coaches as $c): ?>
                <option value="<?php echo $c['coach_id']; ?>" <?php echo $filterCoach === (int) $c['coach_id'] ? 'selected' : ''; ?>><?php echo safeOut($c['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Team</label>
            <select name="team_id" class="form-select form-select-sm">
              <option value="0">All Teams</option>
              <?php foreach ($teams as $t): ?>
                <option value="<?php echo $t['team_id']; ?>" <?php echo $filterTeam === (int) $t['team_id'] ? 'selected' : ''; ?>><?php echo safeOut($t['team_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Gender</label>
            <select name="gender" class="form-select form-select-sm">
              <option value="">All Genders</option>
              <option value="male" <?php echo $filterGender === 'male' ? 'selected' : ''; ?>>Male</option>
              <option value="female" <?php echo $filterGender === 'female' ? 'selected' : ''; ?>>Female</option>
              <option value="other" <?php echo $filterGender === 'other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Apply</button>
            <a href="<?php echo BASE_URL; ?>/admin/player/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>

      <!-- ============ PLAYERS TABLE ============ -->
      <div class="panel">
        <div class="panel-head"><h2>All Players <span class="panel-sub">(<?php echo count($players); ?> shown)</span></h2></div>
        <div class="table-responsive">
          <table class="table align-middle player-table" id="playersTable">
            <thead>
              <tr>
                <th>Photo</th><th>Player ID</th><th>Name</th><th>Roll No</th><th>Sport</th>
                <th>Team</th><th>Coach</th><th>Email</th><th>Phone</th><th>Status</th><th>Rating</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($players as $p): ?>
                <tr>
                  <td><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:34px; height:34px; border-radius:50%; object-fit:cover;"></td>
                  <td>#<?php echo $p['player_id']; ?></td>
                  <td><?php echo safeOut($p['full_name']); ?></td>
                  <td><?php echo safeOut($p['roll_no']); ?></td>
                  <td><?php echo safeOut($p['sport_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($p['team_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($p['coach_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($p['email']); ?></td>
                  <td><?php echo safeOut($p['phone'] ?? '—'); ?></td>
                  <td><span class="status-pill status-<?php echo $p['status']; ?>"><?php echo safeOut($p['status']); ?></span></td>
                  <td data-order="<?php echo $p['average_rating'] !== null ? (float) $p['average_rating'] : -1; ?>"><?php echo $p['average_rating'] ? round((float) $p['average_rating'], 1) . ' ★' : '—'; ?></td>
                  <td class="text-nowrap">
                    <button type="button" class="table-action-btn quick-view-btn" data-id="<?php echo $p['player_id']; ?>" title="Quick View"><i class="fa-solid fa-eye"></i></button>
                    <a href="<?php echo BASE_URL; ?>/admin/player/view.php?id=<?php echo $p['player_id']; ?>" class="table-action-btn" title="Full Profile"><i class="fa-solid fa-id-card"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/player/edit.php?id=<?php echo $p['player_id']; ?>" class="table-action-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <button type="button" class="table-action-btn reset-password-btn" data-id="<?php echo $p['player_id']; ?>" title="Reset Password"><i class="fa-solid fa-key"></i></button>
                    <button type="button" class="table-action-btn delete-player-btn" data-id="<?php echo $p['player_id']; ?>" data-name="<?php echo safeOut($p['full_name']); ?>" title="Deactivate"><i class="fa-solid fa-trash"></i></button>
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
    <script src="' . BASE_URL . '/assets/js/player.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
