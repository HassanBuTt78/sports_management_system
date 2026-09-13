<?php
/**
 * ============================================================
 * admin/dashboard.php
 * ------------------------------------------------------------
 * Module 4 — Admin Dashboard. RBAC via requireRole('admin')
 * from Module 3 (untouched). Every number and chart on this
 * page is a real, read-only query against the Module 2 schema —
 * nothing here is mocked data. Player/Coach/Team Management
 * links throughout are placeholders for later modules, as
 * instructed — this file only ever SELECTs.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin');

$dbReady = (bool) $conn;

// ================================================================
// DATA GATHERING (all read-only)
// ================================================================
$stats = [
    'total_players'      => 0, 'total_coaches'      => 0, 'total_teams'   => 0,
    'total_sports'       => 0, 'total_events'       => 0, 'upcoming_events' => 0,
    'completed_matches'  => 0, 'pending_messages'   => 0,
];
$chartPlayersPerSport = ['labels' => [], 'values' => []];
$chartPerformanceStats = ['labels' => [], 'values' => []];
$chartMonthlyEvents = ['labels' => [], 'values' => []];
$chartCoachPerformance = ['labels' => [], 'values' => []];
$chartPlayerGrowth = ['labels' => [], 'values' => []];
$recentActivity = [];
$latestPlayers = [];
$latestCoaches = [];
$upcomingEvents = [];
$activeAccounts = 0;
$storagePercent = 0;

if ($dbReady) {
    $adminId = (int) $_SESSION['user_id'];

    // ---------- Stat cards ----------
    $stats['total_players']     = (int) $conn->query('SELECT COUNT(*) c FROM players')->fetch_assoc()['c'];
    $stats['total_coaches']     = (int) $conn->query('SELECT COUNT(*) c FROM coaches')->fetch_assoc()['c'];
    $stats['total_teams']       = (int) $conn->query('SELECT COUNT(*) c FROM teams')->fetch_assoc()['c'];
    $stats['total_sports']      = (int) $conn->query('SELECT COUNT(*) c FROM sports')->fetch_assoc()['c'];
    $stats['total_events']      = (int) $conn->query('SELECT COUNT(*) c FROM events')->fetch_assoc()['c'];
    $stats['upcoming_events']   = (int) $conn->query("SELECT COUNT(*) c FROM events WHERE status='Upcoming'")->fetch_assoc()['c'];
    $stats['completed_matches'] = (int) $conn->query("SELECT COUNT(*) c FROM matches WHERE status='Completed'")->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM messages WHERE receiver_role='admin' AND receiver_id=? AND seen_status='sent'");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stats['pending_messages'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // ---------- Chart: Players Per Sport ----------
    $res = $conn->query(
        'SELECT s.sport_name, COUNT(p.player_id) AS c FROM sports s
         LEFT JOIN players p ON p.sport_id = s.sport_id
         GROUP BY s.sport_id ORDER BY s.sport_name'
    );
    while ($row = $res->fetch_assoc()) {
        $chartPlayersPerSport['labels'][] = $row['sport_name'];
        $chartPlayersPerSport['values'][] = (int) $row['c'];
    }

    // ---------- Chart: Performance Statistics (avg rating per sport) ----------
    $res = $conn->query(
        'SELECT s.sport_name, AVG(pa.average_rating) AS avg_r FROM performance_analysis pa
         JOIN players p ON pa.player_id = p.player_id
         JOIN sports s ON p.sport_id = s.sport_id
         GROUP BY s.sport_id ORDER BY s.sport_name'
    );
    while ($row = $res->fetch_assoc()) {
        $chartPerformanceStats['labels'][] = $row['sport_name'];
        $chartPerformanceStats['values'][] = round((float) $row['avg_r'], 2);
    }

    // ---------- Chart: Monthly Events (Jan-Dec, all years combined) ----------
    $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $monthCounts = array_fill(1, 12, 0);
    $res = $conn->query('SELECT MONTH(event_date) AS m, COUNT(*) AS c FROM events GROUP BY MONTH(event_date)');
    while ($row = $res->fetch_assoc()) {
        $monthCounts[(int) $row['m']] = (int) $row['c'];
    }
    $chartMonthlyEvents['labels'] = $monthNames;
    $chartMonthlyEvents['values'] = array_values($monthCounts);

    // ---------- Chart: Coach Performance (avg player rating per coach) ----------
    $res = $conn->query(
        'SELECT c.full_name, AVG(pr.rating) AS avg_r FROM player_ratings pr
         JOIN coaches c ON pr.coach_id = c.coach_id
         GROUP BY c.coach_id ORDER BY avg_r DESC LIMIT 8'
    );
    while ($row = $res->fetch_assoc()) {
        $chartCoachPerformance['labels'][] = $row['full_name'];
        $chartCoachPerformance['values'][] = round((float) $row['avg_r'], 2);
    }

    // ---------- Chart: Player Growth (players added per month) ----------
    $res = $conn->query(
        "SELECT DATE_FORMAT(created_at, '%b %Y') AS ym, COUNT(*) AS c FROM players
         GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY DATE_FORMAT(created_at, '%Y-%m')"
    );
    while ($row = $res->fetch_assoc()) {
        $chartPlayerGrowth['labels'][] = $row['ym'];
        $chartPlayerGrowth['values'][] = (int) $row['c'];
    }

    // ---------- Recent Activity feed (merged: logins, new players/coaches, events, messages) ----------
    $feed = [];

    $res = $conn->query("SELECT user_role, user_id, activity, created_at FROM activity_logs WHERE activity LIKE 'Login%' ORDER BY created_at DESC LIMIT 5");
    while ($row = $res->fetch_assoc()) {
        $feed[] = [
            'icon' => 'fa-right-to-bracket',
            'text' => resolveUserName($conn, $row['user_role'], (int) $row['user_id']) . ' logged in (' . ucfirst($row['user_role']) . ')',
            'time' => $row['created_at'],
        ];
    }
    $res = $conn->query('SELECT full_name, created_at FROM players ORDER BY created_at DESC LIMIT 3');
    while ($row = $res->fetch_assoc()) {
        $feed[] = ['icon' => 'fa-person-running', 'text' => 'New player registered: ' . $row['full_name'], 'time' => $row['created_at']];
    }
    $res = $conn->query('SELECT full_name, created_at FROM coaches ORDER BY created_at DESC LIMIT 3');
    while ($row = $res->fetch_assoc()) {
        $feed[] = ['icon' => 'fa-whistle', 'text' => 'New coach added: ' . $row['full_name'], 'time' => $row['created_at']];
    }
    $res = $conn->query('SELECT title, created_at FROM events ORDER BY created_at DESC LIMIT 3');
    while ($row = $res->fetch_assoc()) {
        $feed[] = ['icon' => 'fa-calendar-days', 'text' => 'Event created: ' . $row['title'], 'time' => $row['created_at']];
    }
    $res = $conn->query('SELECT sender_role, sender_id, sent_at FROM messages ORDER BY sent_at DESC LIMIT 3');
    while ($row = $res->fetch_assoc()) {
        $feed[] = [
            'icon' => 'fa-comment-dots',
            'text' => 'Message from ' . resolveUserName($conn, $row['sender_role'], (int) $row['sender_id']),
            'time' => $row['sent_at'],
        ];
    }

    usort($feed, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
    $recentActivity = array_slice($feed, 0, 8);

    // ---------- Player summary table ----------
    $res = $conn->query(
        'SELECT p.player_id, p.full_name, p.profile_image, p.status, s.sport_name, c.full_name AS coach_name, t.team_name
         FROM players p
         LEFT JOIN sports s ON p.sport_id = s.sport_id
         LEFT JOIN coaches c ON p.coach_id = c.coach_id
         LEFT JOIN teams t ON p.team_id = t.team_id
         ORDER BY p.created_at DESC LIMIT 10'
    );
    $latestPlayers = $res->fetch_all(MYSQLI_ASSOC);

    // ---------- Coach summary table ----------
    $res = $conn->query(
        'SELECT c.coach_id, c.full_name, c.profile_image, c.experience, c.status, s.sport_name,
                COUNT(p.player_id) AS assigned_players
         FROM coaches c
         LEFT JOIN sports s ON c.sport_id = s.sport_id
         LEFT JOIN players p ON p.coach_id = c.coach_id
         GROUP BY c.coach_id ORDER BY c.created_at DESC LIMIT 10'
    );
    $latestCoaches = $res->fetch_all(MYSQLI_ASSOC);

    // ---------- Upcoming events ----------
    $res = $conn->query(
        "SELECT e.event_id, e.title, e.venue, e.event_date, e.start_time, e.status, c.full_name AS coach_name
         FROM events e LEFT JOIN coaches c ON e.coach_id = c.coach_id
         WHERE e.status = 'Upcoming' ORDER BY e.event_date ASC LIMIT 6"
    );
    $upcomingEvents = $res->fetch_all(MYSQLI_ASSOC);

    // ---------- System health: active accounts across all 3 role tables ----------
    $activeAccounts =
        (int) $conn->query("SELECT COUNT(*) c FROM admins WHERE status='active'")->fetch_assoc()['c'] +
        (int) $conn->query("SELECT COUNT(*) c FROM coaches WHERE status='active'")->fetch_assoc()['c'] +
        (int) $conn->query("SELECT COUNT(*) c FROM players WHERE status='active'")->fetch_assoc()['c'];

    // ---------- Storage usage (real disk stats of the project directory) ----------
    $total = @disk_total_space(__DIR__);
    $free  = @disk_free_space(__DIR__);
    if ($total && $free) {
        $storagePercent = round((($total - $free) / $total) * 100, 1);
    }
}

$pageTitle = 'Dashboard';
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css">
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
          <h1>Welcome back, <?php echo safeOut(explode(' ', $_SESSION['full_name'])[0]); ?> 👋</h1>
          <p>Here's what's happening across all four sports programs today.</p>
        </div>
      </div>

      <?php if (!$dbReady): ?>
        <div class="alert alert-warning">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database not connected. Import <code>database/schema.sql</code>, <code>schema_module3.sql</code>
          in phpMyAdmin, then refresh this page.
        </div>
      <?php endif; ?>

      <!-- ============ STAT CARDS ============ -->
      <div class="stat-grid">
        <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-person-running"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total_players']; ?>">0</div>
          <div class="stat-card-label">Total Players</div></div>

        <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-whistle"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total_coaches']; ?>">0</div>
          <div class="stat-card-label">Total Coaches</div></div>

        <div class="stat-card stat-3"><div class="stat-card-icon"><i class="fa-solid fa-people-group"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total_teams']; ?>">0</div>
          <div class="stat-card-label">Total Teams</div></div>

        <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-futbol"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total_sports']; ?>">0</div>
          <div class="stat-card-label">Total Sports</div></div>

        <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total_events']; ?>">0</div>
          <div class="stat-card-label">Total Events</div></div>

        <div class="stat-card stat-6"><div class="stat-card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['upcoming_events']; ?>">0</div>
          <div class="stat-card-label">Upcoming Events</div></div>

        <div class="stat-card stat-7"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['completed_matches']; ?>">0</div>
          <div class="stat-card-label">Completed Matches</div></div>

        <div class="stat-card stat-8"><div class="stat-card-icon"><i class="fa-solid fa-envelope"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['pending_messages']; ?>">0</div>
          <div class="stat-card-label">Pending Messages</div></div>
      </div>

      <!-- ============ QUICK ACTIONS ============ -->
      <div class="panel">
        <div class="panel-head"><h2>Quick Actions</h2></div>
        <div class="quick-actions">
          <a href="<?php echo BASE_URL; ?>/admin/player/create.php" class="quick-action-btn"><i class="fa-solid fa-user-plus"></i>Add Player</a>
          <a href="<?php echo BASE_URL; ?>/admin/coach/create.php" class="quick-action-btn"><i class="fa-solid fa-whistle"></i>Add Coach</a>
          <a href="<?php echo BASE_URL; ?>/admin/events/create.php" class="quick-action-btn"><i class="fa-solid fa-calendar-plus"></i>Create Event</a>
          <a href="<?php echo BASE_URL; ?>/admin/matches/create.php" class="quick-action-btn"><i class="fa-solid fa-stopwatch"></i>Schedule Match</a>
          <a href="<?php echo BASE_URL; ?>/admin/team/create.php" class="quick-action-btn"><i class="fa-solid fa-people-group"></i>Create Team</a>
          <a href="<?php echo BASE_URL; ?>/admin/reports/index.php" class="quick-action-btn"><i class="fa-solid fa-file-invoice"></i>View Reports</a>
        </div>
      </div>

      <!-- ============ CHARTS ============ -->
      <div class="grid-charts">
        <div class="panel">
          <div class="panel-head"><h2>Players Per Sport</h2></div>
          <div class="chart-wrap"><canvas id="chartPlayersPerSport"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Performance Statistics <span class="panel-sub">(avg. rating by sport)</span></h2></div>
          <div class="chart-wrap"><canvas id="chartPerformanceStats"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Monthly Events</h2></div>
          <div class="chart-wrap"><canvas id="chartMonthlyEvents"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Coach Performance <span class="panel-sub">(avg. player rating)</span></h2></div>
          <div class="chart-wrap"><canvas id="chartCoachPerformance"></canvas></div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Player Growth</h2></div>
        <div class="chart-wrap"><canvas id="chartPlayerGrowth"></canvas></div>
      </div>

      <!-- ============ RECENT ACTIVITY + SYSTEM HEALTH ============ -->
      <div class="grid-2col">
        <div class="panel">
          <div class="panel-head"><h2>Recent Activity</h2></div>
          <?php if (empty($recentActivity)): ?>
            <p class="text-muted mb-0">No recent activity yet.</p>
          <?php else: ?>
            <ul class="activity-list">
              <?php foreach ($recentActivity as $a): ?>
                <li class="activity-item">
                  <div class="activity-icon"><i class="fa-solid <?php echo $a['icon']; ?>"></i></div>
                  <div><p><?php echo safeOut($a['text']); ?></p><span><?php echo timeAgo($a['time']); ?></span></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>System Health</h2></div>
          <div class="health-grid">
            <div class="health-item">
              <span class="health-dot <?php echo $dbReady ? 'health-ok' : 'health-warn'; ?>"></span>
              <div class="health-value"><?php echo $dbReady ? 'Online' : 'Offline'; ?></div>
              <div class="health-label">Database</div>
            </div>
            <div class="health-item">
              <span class="health-dot health-ok"></span>
              <div class="health-value"><?php echo safeOut(PHP_VERSION); ?></div>
              <div class="health-label">PHP Version</div>
            </div>
            <div class="health-item">
              <span class="health-dot health-ok"></span>
              <div class="health-value"><?php echo $activeAccounts; ?></div>
              <div class="health-label">Active Accounts</div>
            </div>
            <div class="health-item">
              <span class="health-dot <?php echo $storagePercent > 90 ? 'health-warn' : 'health-ok'; ?>"></span>
              <div class="health-value"><?php echo $storagePercent; ?>%</div>
              <div class="health-label">Storage Used</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ PLAYER SUMMARY ============ -->
      <div class="panel">
        <div class="panel-head">
          <h2>Latest Players</h2>
          <a href="<?php echo BASE_URL; ?>/admin/player/index.php" class="panel-sub">View All &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table data-table align-middle">
            <thead><tr><th>Player</th><th>Sport</th><th>Coach</th><th>Team</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($latestPlayers as $p): ?>
                <tr>
                  <td class="mini-profile">
                    <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="">
                    <?php echo safeOut($p['full_name']); ?>
                  </td>
                  <td><?php echo safeOut($p['sport_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($p['coach_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($p['team_name'] ?? '—'); ?></td>
                  <td><span class="status-pill status-<?php echo $p['status']; ?>"><?php echo safeOut($p['status']); ?></span></td>
                  <td>
                    <a href="<?php echo BASE_URL; ?>/admin/player/view.php?id=<?php echo $p['player_id']; ?>" class="table-action-btn"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/player/edit.php?id=<?php echo $p['player_id']; ?>" class="table-action-btn"><i class="fa-solid fa-pen"></i></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ============ COACH SUMMARY ============ -->
      <div class="panel">
        <div class="panel-head">
          <h2>Latest Coaches</h2>
          <a href="<?php echo BASE_URL; ?>/admin/coach/index.php" class="panel-sub">View All &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table data-table align-middle">
            <thead><tr><th>Coach</th><th>Sport</th><th>Experience</th><th>Assigned Players</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($latestCoaches as $c): ?>
                <tr>
                  <td class="mini-profile">
                    <img src="<?php echo $c['profile_image'] ? UPLOADS_URL . '/' . $c['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="">
                    <?php echo safeOut($c['full_name']); ?>
                  </td>
                  <td><?php echo safeOut($c['sport_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($c['experience'] ?? '—'); ?></td>
                  <td><?php echo (int) $c['assigned_players']; ?></td>
                  <td><span class="status-pill status-<?php echo $c['status']; ?>"><?php echo safeOut($c['status']); ?></span></td>
                  <td>
                    <a href="<?php echo BASE_URL; ?>/admin/coach/view.php?id=<?php echo $c['coach_id']; ?>" class="table-action-btn"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/coach/edit.php?id=<?php echo $c['coach_id']; ?>" class="table-action-btn"><i class="fa-solid fa-pen"></i></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ============ UPCOMING EVENTS ============ -->
      <div class="panel">
        <div class="panel-head">
          <h2>Upcoming Events</h2>
          <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="panel-sub">View All &rarr;</a>
        </div>
        <?php if (empty($upcomingEvents)): ?>
          <p class="text-muted mb-0">No upcoming events scheduled.</p>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($upcomingEvents as $e): ?>
              <div class="col-lg-4 col-md-6">
                <a href="<?php echo BASE_URL; ?>/admin/events/view.php?id=<?php echo $e['event_id']; ?>" class="event-mini-card" style="display:block;">
                  <span class="event-mini-badge badge-<?php echo str_replace(' ', '', $e['status']); ?>"><?php echo safeOut($e['status']); ?></span>
                  <h5 style="font-size:.98rem; margin:0;"><?php echo safeOut($e['title']); ?></h5>
                  <ul class="event-mini-meta">
                    <li><i class="fa-solid fa-location-dot"></i><?php echo safeOut($e['venue'] ?? 'TBA'); ?></li>
                    <li><i class="fa-solid fa-calendar-day"></i><?php echo date('M j, Y', strtotime($e['event_date'])); ?></li>
                    <?php if ($e['start_time']): ?><li><i class="fa-solid fa-clock"></i><?php echo date('g:i A', strtotime($e['start_time'])); ?></li><?php endif; ?>
                    <li><i class="fa-solid fa-whistle"></i><?php echo safeOut($e['coach_name'] ?? 'Unassigned'); ?></li>
                  </ul>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /.admin-content -->
  <?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

<script>
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
  window.dashboardCharts = {
    playersPerSport:  <?php echo json_encode($chartPlayersPerSport); ?>,
    performanceStats: <?php echo json_encode($chartPerformanceStats); ?>,
    monthlyEvents:    <?php echo json_encode($chartMonthlyEvents); ?>,
    coachPerformance: <?php echo json_encode($chartCoachPerformance); ?>,
    playerGrowth:     <?php echo json_encode($chartPlayerGrowth); ?>
  };
</script>
