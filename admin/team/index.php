<?php
/**
 * ============================================================
 * admin/team/index.php
 * ------------------------------------------------------------
 * Team Management listing + dashboard cards. Admin sees and can
 * manage every team.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$filterSport  = (int) ($_GET['sport_id'] ?? 0);
$filterCoach  = (int) ($_GET['coach_id'] ?? 0);
$filterStatus = cleanInput($_GET['status'] ?? '');
$searchName   = cleanInput($_GET['q'] ?? '');

$sports = [];
$coaches = [];
$teams = [];
$dash = ['total_teams' => 0, 'active_teams' => 0, 'total_players' => 0, 'matches_played' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0];

if ($conn) {
    $sports  = $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC);
    $coaches = $conn->query('SELECT coach_id, full_name FROM coaches ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);

    // ---------- Dashboard cards ----------
    $dash['total_teams']    = (int) $conn->query('SELECT COUNT(*) c FROM teams')->fetch_assoc()['c'];
    $dash['active_teams']   = (int) $conn->query("SELECT COUNT(*) c FROM teams WHERE status='active'")->fetch_assoc()['c'];
    $dash['total_players']  = (int) $conn->query('SELECT COUNT(*) c FROM players WHERE team_id IS NOT NULL')->fetch_assoc()['c'];
    $dash['matches_played'] = (int) $conn->query("SELECT COUNT(*) c FROM matches WHERE status='Completed'")->fetch_assoc()['c'];
    $dash['wins']           = (int) $conn->query('SELECT COUNT(*) c FROM matches WHERE winner_team IS NOT NULL')->fetch_assoc()['c'];
    $dash['draws']          = (int) $conn->query("SELECT COUNT(*) c FROM matches WHERE status='Completed' AND winner_team IS NULL AND team_two IS NOT NULL")->fetch_assoc()['c'];
    $dash['losses']         = $dash['wins']; // every completed win has exactly one corresponding loss on the other side

    // ---------- Filtered team list ----------
    $where = [];
    $types = '';
    $params = [];
    if ($filterSport > 0)  { $where[] = 't.sport_id = ?'; $types .= 'i'; $params[] = $filterSport; }
    if ($filterCoach > 0)  { $where[] = 't.coach_id = ?'; $types .= 'i'; $params[] = $filterCoach; }
    if (in_array($filterStatus, ['active', 'inactive'], true)) { $where[] = 't.status = ?'; $types .= 's'; $params[] = $filterStatus; }
    if ($searchName !== '') { $where[] = 't.team_name LIKE ?'; $types .= 's'; $params[] = '%' . $searchName . '%'; }

    $sql = 'SELECT t.team_id, t.team_name, t.team_logo, t.status, t.created_at,
                   s.sport_name, c.full_name AS coach_name,
                   (SELECT COUNT(*) FROM players p WHERE p.team_id = t.team_id) AS player_count
            FROM teams t
            LEFT JOIN sports s ON t.sport_id = s.sport_id
            LEFT JOIN coaches c ON t.coach_id = c.coach_id';
    if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY t.created_at DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Team Management';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/team.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Team Management</h1><p>Create and manage teams across all four sports.</p></div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/admin/team/statistics.php" class="btn btn-outline-secondary" style="width:auto;"><i class="fa-solid fa-chart-column me-2"></i>Statistics</a>
          <a href="<?php echo BASE_URL; ?>/admin/team/create.php" class="btn btn-auth-submit" style="width:auto;"><i class="fa-solid fa-plus me-2"></i>Create Team</a>
        </div>
      </div>

      <?php if (!$conn): ?>
        <div class="alert alert-warning">Database not connected — import the schema files first (including <code>schema_module7.sql</code>).</div>
      <?php endif; ?>

      <!-- ============ TEAM DASHBOARD CARDS ============ -->
      <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-people-group"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $dash['total_teams']; ?>">0</div><div class="stat-card-label">Total Teams</div></div>
        <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $dash['active_teams']; ?>">0</div><div class="stat-card-label">Active Teams</div></div>
        <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-person-running"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $dash['total_players']; ?>">0</div><div class="stat-card-label">Total Players</div></div>
        <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $dash['matches_played']; ?>">0</div><div class="stat-card-label">Matches Played</div></div>
        <div class="stat-card stat-7"><div class="stat-card-icon"><i class="fa-solid fa-medal"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $dash['wins']; ?>">0</div><div class="stat-card-label">Wins</div></div>
        <div class="stat-card" style="background:linear-gradient(135deg,#c1443c,#8a2d27);"><div class="stat-card-icon"><i class="fa-solid fa-xmark"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $dash['losses']; ?>">0</div><div class="stat-card-label">Losses</div></div>
        <div class="stat-card stat-3"><div class="stat-card-icon"><i class="fa-solid fa-handshake"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $dash['draws']; ?>">0</div><div class="stat-card-label">Draws</div></div>
      </div>

      <!-- ============ SEARCH & FILTER ============ -->
      <div class="panel">
        <div class="panel-head"><h2>Search &amp; Filter</h2></div>
        <form method="GET" class="row g-3">
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Team Name</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?php echo safeOut($searchName); ?>" placeholder="Search by name...">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Sport</label>
            <select name="sport_id" class="form-select form-select-sm">
              <option value="0">All Sports</option>
              <?php foreach ($sports as $s): ?>
                <option value="<?php echo $s['sport_id']; ?>" <?php echo $filterSport === (int) $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Coach</label>
            <select name="coach_id" class="form-select form-select-sm">
              <option value="0">All Coaches</option>
              <?php foreach ($coaches as $c): ?>
                <option value="<?php echo $c['coach_id']; ?>" <?php echo $filterCoach === (int) $c['coach_id'] ? 'selected' : ''; ?>><?php echo safeOut($c['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-filter"></i></button>
          </div>
        </form>
      </div>

      <!-- ============ TEAMS TABLE ============ -->
      <div class="panel">
        <div class="panel-head"><h2>All Teams <span class="panel-sub">(<?php echo count($teams); ?> shown)</span></h2></div>
        <div class="table-responsive">
          <table class="table align-middle team-table" id="teamsTable">
            <thead><tr><th>Logo</th><th>Team ID</th><th>Team Name</th><th>Sport</th><th>Coach</th><th>Players</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($teams as $t): ?>
                <tr>
                  <td><img src="<?php echo $t['team_logo'] ? UPLOADS_URL . '/' . $t['team_logo'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="" style="width:34px; height:34px; border-radius:8px; object-fit:cover;"></td>
                  <td><?php echo safeOut(formatEmployeeId('TEAM', (int) $t['team_id'])); ?></td>
                  <td><?php echo safeOut($t['team_name']); ?></td>
                  <td><?php echo safeOut($t['sport_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($t['coach_name'] ?? 'Unassigned'); ?></td>
                  <td><?php echo (int) $t['player_count']; ?></td>
                  <td><span class="status-pill status-<?php echo $t['status']; ?>"><?php echo safeOut($t['status']); ?></span></td>
                  <td><?php echo date('M j, Y', strtotime($t['created_at'])); ?></td>
                  <td class="text-nowrap">
                    <a href="<?php echo BASE_URL; ?>/admin/team/view.php?id=<?php echo $t['team_id']; ?>" class="table-action-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/team/edit.php?id=<?php echo $t['team_id']; ?>" class="table-action-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/team/assign_players.php?team_id=<?php echo $t['team_id']; ?>" class="table-action-btn" title="Manage Players"><i class="fa-solid fa-people-arrows"></i></a>
                    <button type="button" class="table-action-btn delete-team-btn" data-id="<?php echo $t['team_id']; ?>" data-name="<?php echo safeOut($t['team_name']); ?>" title="Deactivate"><i class="fa-solid fa-trash"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/team.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
