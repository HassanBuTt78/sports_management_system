<?php
/**
 * ============================================================
 * admin/matches/index.php
 * ------------------------------------------------------------
 * Match Management listing. Admin sees every match across all
 * sports. Uses the matches.status ENUM ('Upcoming','Completed',
 * 'Cancelled') — matches move straight from Scheduled to
 * Completed once a coach/admin enters the result.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$filterSport  = (int) ($_GET['sport_id'] ?? 0);
$filterCoach  = (int) ($_GET['coach_id'] ?? 0);
$filterTeam   = (int) ($_GET['team_id'] ?? 0);
$filterStatus = cleanInput($_GET['status'] ?? '');
$filterDate   = cleanInput($_GET['date'] ?? '');

$sports = [];
$coaches = [];
$teams = [];
$matches = [];
$stats = ['total' => 0, 'upcoming' => 0, 'completed' => 0, 'cancelled' => 0];

if ($conn) {
    $sports  = $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC);
    $coaches = $conn->query('SELECT coach_id, full_name FROM coaches ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
    $teams   = $conn->query('SELECT team_id, team_name FROM teams ORDER BY team_name')->fetch_all(MYSQLI_ASSOC);

    $stats['total']     = (int) $conn->query('SELECT COUNT(*) c FROM matches')->fetch_assoc()['c'];
    $stats['upcoming']  = (int) $conn->query("SELECT COUNT(*) c FROM matches WHERE status='Upcoming'")->fetch_assoc()['c'];
    $stats['completed'] = (int) $conn->query("SELECT COUNT(*) c FROM matches WHERE status='Completed'")->fetch_assoc()['c'];
    $stats['cancelled'] = (int) $conn->query("SELECT COUNT(*) c FROM matches WHERE status='Cancelled'")->fetch_assoc()['c'];

    $where = [];
    $types = '';
    $params = [];
    if ($filterSport > 0) { $where[] = 'm.sport_id = ?'; $types .= 'i'; $params[] = $filterSport; }
    if ($filterCoach > 0) { $where[] = 'm.coach_id = ?'; $types .= 'i'; $params[] = $filterCoach; }
    if ($filterTeam > 0)  { $where[] = '(m.team_one = ? OR m.team_two = ?)'; $types .= 'ii'; $params[] = $filterTeam; $params[] = $filterTeam; }
    if (in_array($filterStatus, ['Upcoming','Completed','Cancelled'], true)) { $where[] = 'm.status = ?'; $types .= 's'; $params[] = $filterStatus; }
    if ($filterDate !== '') { $where[] = 'm.match_date = ?'; $types .= 's'; $params[] = $filterDate; }

    $sql = "SELECT m.*, s.sport_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name, c.full_name AS coach_name
            FROM matches m
            LEFT JOIN sports s ON m.sport_id = s.sport_id
            LEFT JOIN teams ta ON m.team_one = ta.team_id
            LEFT JOIN teams tb ON m.team_two = tb.team_id
            LEFT JOIN coaches c ON m.coach_id = c.coach_id";
    if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY m.match_date DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Match Management';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/matches.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Match Management</h1><p>Schedule matches and record results.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/matches/create.php" class="btn btn-auth-submit" style="width:auto;"><i class="fa-solid fa-plus me-2"></i>Create Match</a>
      </div>

      <?php if (!$conn): ?>
        <div class="alert alert-warning">Database not connected — import the schema files first (including <code>schema_module9.sql</code>).</div>
      <?php endif; ?>

      <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total']; ?>">0</div><div class="stat-card-label">Total Matches</div></div>
        <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['upcoming']; ?>">0</div><div class="stat-card-label">Scheduled</div></div>
        <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['completed']; ?>">0</div><div class="stat-card-label">Completed</div></div>
        <div class="stat-card" style="background:linear-gradient(135deg,#6c757d,#495057);"><div class="stat-card-icon"><i class="fa-solid fa-ban"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['cancelled']; ?>">0</div><div class="stat-card-label">Cancelled</div></div>
      </div>

      <div class="d-flex justify-content-end mb-3">
        <a href="<?php echo BASE_URL; ?>/admin/matches/statistics.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chart-column me-2"></i>Match Statistics</a>
      </div>

      <div class="panel">
        <div class="panel-head"><h2>Search &amp; Filter</h2></div>
        <form method="GET" class="row g-3">
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Sport</label>
            <select name="sport_id" class="form-select form-select-sm">
              <option value="0">All Sports</option>
              <?php foreach ($sports as $s): ?><option value="<?php echo $s['sport_id']; ?>" <?php echo $filterSport === (int) $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Coach</label>
            <select name="coach_id" class="form-select form-select-sm">
              <option value="0">All Coaches</option>
              <?php foreach ($coaches as $c): ?><option value="<?php echo $c['coach_id']; ?>" <?php echo $filterCoach === (int) $c['coach_id'] ? 'selected' : ''; ?>><?php echo safeOut($c['full_name']); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Team</label>
            <select name="team_id" class="form-select form-select-sm">
              <option value="0">All Teams</option>
              <?php foreach ($teams as $t): ?><option value="<?php echo $t['team_id']; ?>" <?php echo $filterTeam === (int) $t['team_id'] ? 'selected' : ''; ?>><?php echo safeOut($t['team_name']); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">All</option>
              <?php foreach (['Upcoming','Completed','Cancelled'] as $st): ?><option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo $st === 'Upcoming' ? 'Scheduled' : $st; ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Date</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo safeOut($filterDate); ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">Apply</button>
            <a href="<?php echo BASE_URL; ?>/admin/matches/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>

      <div class="panel">
        <div class="panel-head"><h2>All Matches <span class="panel-sub">(<?php echo count($matches); ?> shown)</span></h2></div>
        <div class="table-responsive">
          <table class="table align-middle match-table" id="matchesTable">
            <thead><tr><th>Title</th><th>Sport</th><th>Matchup</th><th>Coach</th><th>Venue</th><th>Date</th><th>Result</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($matches as $m): ?>
                <tr>
                  <td><?php echo safeOut($m['match_title'] ?: ($m['team_a_name'] ?? 'TBD') . ' Match'); ?></td>
                  <td><?php echo safeOut($m['sport_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($m['team_a_name'] ?? 'TBD') . ($m['team_b_name'] ? ' vs ' . safeOut($m['team_b_name']) : ' (individual)'); ?></td>
                  <td><?php echo safeOut($m['coach_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($m['venue'] ?? '—'); ?></td>
                  <td><?php echo date('M j, Y', strtotime($m['match_date'])); ?></td>
                  <td><?php echo safeOut($m['result_summary'] ?? '—'); ?></td>
                  <td><span class="event-mini-badge badge-<?php echo $m['status']; ?>"><?php echo $m['status'] === 'Upcoming' ? 'Scheduled' : safeOut($m['status']); ?></span></td>
                  <td class="text-nowrap">
                    <a href="<?php echo BASE_URL; ?>/admin/matches/view.php?id=<?php echo $m['match_id']; ?>" class="table-action-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/matches/edit.php?id=<?php echo $m['match_id']; ?>" class="table-action-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/matches/results.php?id=<?php echo $m['match_id']; ?>" class="table-action-btn" title="Results"><i class="fa-solid fa-medal"></i></a>
                    <?php if (!in_array($m['status'], ['Completed','Cancelled'], true)): ?>
                      <button type="button" class="table-action-btn match-action-btn" data-action="cancel" data-id="<?php echo $m['match_id']; ?>" title="Cancel"><i class="fa-solid fa-ban text-danger"></i></button>
                    <?php endif; ?>
                    <button type="button" class="table-action-btn match-action-btn" data-action="delete" data-id="<?php echo $m['match_id']; ?>" data-name="<?php echo safeOut($m['match_title'] ?: 'this match'); ?>" title="Delete"><i class="fa-solid fa-trash"></i></button>
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
    <script>window.SMS_CSRF_TOKEN = ' . json_encode(csrfToken()) . ';</script>
    <script src="' . BASE_URL . '/assets/js/matches.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
