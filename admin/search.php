<?php
/**
 * ============================================================
 * admin/search.php
 * ------------------------------------------------------------
 * Global search (topbar search bar). Read-only — searches
 * existing players/coaches/teams/events by name/title. This is
 * NOT Player/Coach Management (no add/edit/delete here), so it
 * stays in scope for Module 4 per the brief's own "Search
 * Players / Coaches / Teams / Events" requirement.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin');

$query = cleanInput($_GET['q'] ?? '');
$filter = cleanInput($_GET['type'] ?? 'all');

$results = ['players' => [], 'coaches' => [], 'teams' => [], 'events' => []];

if ($conn && $query !== '') {
    $like = '%' . $query . '%';

    if ($filter === 'all' || $filter === 'players') {
        $stmt = $conn->prepare(
            "SELECT p.player_id, p.full_name, p.status, s.sport_name FROM players p
             LEFT JOIN sports s ON p.sport_id = s.sport_id
             WHERE p.full_name LIKE ? OR p.roll_no LIKE ? OR p.email LIKE ? LIMIT 15"
        );
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $results['players'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if ($filter === 'all' || $filter === 'coaches') {
        $stmt = $conn->prepare(
            "SELECT c.coach_id, c.full_name, c.status, s.sport_name FROM coaches c
             LEFT JOIN sports s ON c.sport_id = s.sport_id
             WHERE c.full_name LIKE ? OR c.email LIKE ? LIMIT 15"
        );
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $results['coaches'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if ($filter === 'all' || $filter === 'teams') {
        $stmt = $conn->prepare(
            "SELECT t.team_id, t.team_name, s.sport_name FROM teams t
             LEFT JOIN sports s ON t.sport_id = s.sport_id
             WHERE t.team_name LIKE ? LIMIT 15"
        );
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $results['teams'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if ($filter === 'all' || $filter === 'events') {
        $stmt = $conn->prepare(
            "SELECT event_id, title, status, event_date FROM events WHERE title LIKE ? LIMIT 15"
        );
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $results['events'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$totalResults = count($results['players']) + count($results['coaches']) + count($results['teams']) + count($results['events']);
$pageTitle = 'Search Results';
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
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div>
          <h1>Search Results</h1>
          <p><?php echo $query !== '' ? safeOut($totalResults . ' result(s) for "' . $query . '"') : 'Enter a search term above.'; ?></p>
        </div>
      </div>

      <!-- Filters: By Sport / Coach / Team / Event (as record type here) -->
      <div class="panel">
        <div class="panel-head"><h2>Filter</h2></div>
        <form method="GET" class="d-flex gap-2 flex-wrap">
          <input type="hidden" name="q" value="<?php echo safeOut($query); ?>">
          <?php foreach (['all' => 'All', 'players' => 'Players', 'coaches' => 'Coaches', 'teams' => 'Teams', 'events' => 'Events'] as $key => $label): ?>
            <button type="submit" name="type" value="<?php echo $key; ?>"
                    class="btn btn-sm <?php echo $filter === $key ? 'btn-primary' : 'btn-outline-secondary'; ?>">
              <?php echo $label; ?>
            </button>
          <?php endforeach; ?>
        </form>
      </div>

      <?php if ($query === ''): ?>
        <div class="panel text-center text-muted py-5">Use the search bar in the top navigation to search players, coaches, teams, and events.</div>
      <?php else: ?>

        <?php if (!empty($results['players'])): ?>
        <div class="panel">
          <div class="panel-head"><h2><i class="fa-solid fa-person-running me-2"></i>Players</h2></div>
          <?php foreach ($results['players'] as $p): ?>
            <div class="activity-item">
              <div class="activity-icon"><i class="fa-solid fa-person-running"></i></div>
              <div><p><a href="<?php echo BASE_URL; ?>/admin/player/view.php?id=<?php echo (int) $p['player_id']; ?>"><?php echo safeOut($p['full_name']); ?></a></p><span><?php echo safeOut($p['sport_name'] ?? '—'); ?> &middot; <?php echo safeOut($p['status']); ?></span></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($results['coaches'])): ?>
        <div class="panel">
          <div class="panel-head"><h2><i class="fa-solid fa-whistle me-2"></i>Coaches</h2></div>
          <?php foreach ($results['coaches'] as $c): ?>
            <div class="activity-item">
              <div class="activity-icon"><i class="fa-solid fa-whistle"></i></div>
              <div><p><a href="<?php echo BASE_URL; ?>/admin/coach/view.php?id=<?php echo (int) $c['coach_id']; ?>"><?php echo safeOut($c['full_name']); ?></a></p><span><?php echo safeOut($c['sport_name'] ?? '—'); ?> &middot; <?php echo safeOut($c['status']); ?></span></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($results['teams'])): ?>
        <div class="panel">
          <div class="panel-head"><h2><i class="fa-solid fa-people-group me-2"></i>Teams</h2></div>
          <?php foreach ($results['teams'] as $t): ?>
            <div class="activity-item">
              <div class="activity-icon"><i class="fa-solid fa-people-group"></i></div>
              <div><p><a href="<?php echo BASE_URL; ?>/admin/team/view.php?id=<?php echo (int) $t['team_id']; ?>"><?php echo safeOut($t['team_name']); ?></a></p><span><?php echo safeOut($t['sport_name'] ?? '—'); ?></span></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($results['events'])): ?>
        <div class="panel">
          <div class="panel-head"><h2><i class="fa-solid fa-calendar-days me-2"></i>Events</h2></div>
          <?php foreach ($results['events'] as $e): ?>
            <div class="activity-item">
              <div class="activity-icon"><i class="fa-solid fa-calendar-days"></i></div>
              <div><p><a href="<?php echo BASE_URL; ?>/admin/events/view.php?id=<?php echo (int) $e['event_id']; ?>"><?php echo safeOut($e['title']); ?></a></p><span><?php echo date('M j, Y', strtotime($e['event_date'])); ?> &middot; <?php echo safeOut($e['status']); ?></span></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($totalResults === 0): ?>
          <div class="panel text-center text-muted py-5">No results found for "<?php echo safeOut($query); ?>".</div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  <?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
