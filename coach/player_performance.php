<?php
/**
 * ============================================================
 * coach/player_performance.php
 * ------------------------------------------------------------
 * Coach's player list, scoped to their own sport only (never
 * another coach's sport — enforced in the WHERE clause itself,
 * not just hidden in the UI). Search + Sport/Team/Rating/
 * Performance filters. "Rate Player" links into rate_player.php.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
$stmt->bind_param('i', $coachId);
$stmt->execute();
$mySportId = (int) ($stmt->get_result()->fetch_assoc()['sport_id'] ?? 0);
$stmt->close();

$search = cleanInput($_GET['search'] ?? '');
$teamFilter = (int) ($_GET['team_id'] ?? 0);
$ratingFilter = cleanInput($_GET['rating'] ?? '');
$levelFilter = cleanInput($_GET['level'] ?? '');

$teams = [];
$players = [];

if ($conn && $mySportId) {
    $stmt = $conn->prepare('SELECT team_id, team_name FROM teams WHERE sport_id = ? ORDER BY team_name');
    $stmt->bind_param('i', $mySportId);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $where = ['p.sport_id = ?'];
    $types = 'i';
    $params = [$mySportId];

    if ($search !== '') { $where[] = '(p.full_name LIKE ? OR p.roll_no LIKE ?)'; $types .= 'ss'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($teamFilter > 0) { $where[] = 'p.team_id = ?'; $types .= 'i'; $params[] = $teamFilter; }

    $sql = "SELECT p.player_id, p.full_name, p.roll_no, p.profile_image, t.team_name,
                   (SELECT COUNT(*) FROM player_scores ps JOIN matches m ON ps.match_id = m.match_id WHERE ps.player_id = p.player_id AND m.status = 'Completed') AS matches_played,
                   (SELECT AVG(ps.custom_score) FROM player_scores ps JOIN matches m ON ps.match_id = m.match_id WHERE ps.player_id = p.player_id AND m.status = 'Completed') AS avg_score,
                   (SELECT AVG(pr.rating) FROM player_ratings pr WHERE pr.player_id = p.player_id) AS avg_rating
            FROM players p LEFT JOIN teams t ON p.team_id = t.team_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY p.full_name';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($players as &$p) {
        $p['level'] = classifyStarPerformanceLevel($p['avg_rating'] !== null ? (float) $p['avg_rating'] : null);
    }
    unset($p);

    if ($ratingFilter !== '' && ctype_digit($ratingFilter)) {
        $players = array_values(array_filter($players, function ($p) use ($ratingFilter) {
            return $p['avg_rating'] !== null && (int) round($p['avg_rating']) === (int) $ratingFilter;
        }));
    }
    if ($levelFilter !== '') {
        $players = array_values(array_filter($players, fn($p) => $p['level'] === $levelFilter));
    }
}

$pageTitle = 'Player Performance';
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
  <?php require_once __DIR__ . '/../includes/coach_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/coach_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-star me-2"></i>Player Performance</h1><p class="text-muted">Rate players after completed matches and track their averages.</p></div>
    <a href="<?php echo BASE_URL; ?>/coach/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
  </div>

  <div class="panel mb-3">
    <form method="GET" class="row g-3">
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Search</label>
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or roll no..." value="<?php echo safeOut($search); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Team</label>
        <select name="team_id" class="form-select form-select-sm">
          <option value="0">All Teams</option>
          <?php foreach ($teams as $t): ?><option value="<?php echo $t['team_id']; ?>" <?php echo $teamFilter === (int) $t['team_id'] ? 'selected' : ''; ?>><?php echo safeOut($t['team_name']); ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Rating</label>
        <select name="rating" class="form-select form-select-sm">
          <option value="">All</option>
          <?php for ($r = 5; $r >= 1; $r--): ?><option value="<?php echo $r; ?>" <?php echo $ratingFilter === (string) $r ? 'selected' : ''; ?>><?php echo $r; ?> Star</option><?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Performance</label>
        <select name="level" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach (['Excellent','Very Good','Good','Average','Needs Improvement','Insufficient Data'] as $lvl): ?>
            <option value="<?php echo $lvl; ?>" <?php echo $levelFilter === $lvl ? 'selected' : ''; ?>><?php echo $lvl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-sm btn-primary flex-fill">Apply</button>
        <a href="<?php echo BASE_URL; ?>/coach/player_performance.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>

  <div class="panel">
    <?php if (empty($players)): ?>
      <p class="text-muted mb-0">No players match your filters.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Player</th><th>Player ID</th><th>Team</th><th>Matches</th><th>Avg Score</th><th>Rating</th><th>Performance</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($players as $p): ?>
              <tr>
                <td class="mini-profile"><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;"><?php echo safeOut($p['full_name']); ?></td>
                <td><?php echo safeOut(formatEmployeeId('PL', $p['player_id'])); ?></td>
                <td><?php echo safeOut($p['team_name'] ?? '—'); ?></td>
                <td><?php echo (int) $p['matches_played']; ?></td>
                <td><?php echo $p['avg_score'] !== null ? round($p['avg_score'], 1) : '—'; ?></td>
                <td><?php echo $p['avg_rating'] !== null ? round($p['avg_rating'], 2) . '/5' : '—'; ?></td>
                <td><span class="perf-level-badge perf-level-<?php echo str_replace(' ', '', $p['level']); ?>"><?php echo safeOut($p['level']); ?></span></td>
                <td class="text-nowrap">
                  <a href="<?php echo BASE_URL; ?>/coach/rate_player.php?player_id=<?php echo $p['player_id']; ?>" class="btn btn-sm btn-primary">Rate Player</a>
                  <a href="<?php echo BASE_URL; ?>/coach/rate_player.php?player_id=<?php echo $p['player_id']; ?>#history" class="btn btn-sm btn-outline-secondary">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
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
