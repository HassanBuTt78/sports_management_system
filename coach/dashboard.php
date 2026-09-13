<?php
/**
 * ============================================================
 * coach/dashboard.php
 * ------------------------------------------------------------
 * Full Coach Dashboard — replaces the Module 3 placeholder.
 * Pulls together data that already exists from Modules 5-11
 * (team roster, upcoming events/matches, unread messages,
 * notifications, performance snapshot) into one home screen.
 *
 * Visual refresh: now uses the same sidebar + topbar shell as
 * the admin panel (includes/coach_sidebar.php + coach_topbar.php)
 * instead of a bare top strip, so navigation, dark mode, and the
 * mobile menu all work the same way across every coach page.
 * All data queries below are unchanged from the original module.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$mySportId = $_SESSION['sport_id'] ?? null;
$mySportName = null;

$stats = ['players' => 0, 'teams' => 0, 'upcoming_matches' => 0, 'upcoming_events' => 0, 'unread_messages' => 0, 'unread_notifications' => 0];
$topPlayers = [];
$upcomingMatches = [];
$upcomingEvents = [];
$recentNotifications = [];
$avgTeamPerformance = null;

if ($conn) {
    if ($mySportId) {
        $stmt = $conn->prepare('SELECT sport_name FROM sports WHERE sport_id = ? LIMIT 1');
        $stmt->bind_param('i', $mySportId);
        $stmt->execute();
        $mySportName = $stmt->get_result()->fetch_assoc()['sport_name'] ?? null;
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT COUNT(*) c FROM players WHERE coach_id = ?');
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $stats['players'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare('SELECT COUNT(*) c FROM teams WHERE coach_id = ?');
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $stats['teams'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    if ($mySportId) {
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM matches WHERE sport_id = ? AND status = 'Upcoming'");
        $stmt->bind_param('i', $mySportId);
        $stmt->execute();
        $stats['upcoming_matches'] = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) c FROM events WHERE sport_id = ? AND status IN ('Upcoming','Registration Open','Registration Closed')");
        $stmt->bind_param('i', $mySportId);
        $stmt->execute();
        $stats['upcoming_events'] = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT m.*, ta.team_name AS team_a_name, tb.team_name AS team_b_name FROM matches m
             LEFT JOIN teams ta ON m.team_one = ta.team_id LEFT JOIN teams tb ON m.team_two = tb.team_id
             WHERE m.sport_id = ? AND m.status = 'Upcoming' ORDER BY m.match_date ASC LIMIT 5"
        );
        $stmt->bind_param('i', $mySportId);
        $stmt->execute();
        $upcomingMatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT * FROM events WHERE sport_id = ? AND status IN ('Upcoming','Registration Open','Registration Closed') ORDER BY event_date ASC LIMIT 5"
        );
        $stmt->bind_param('i', $mySportId);
        $stmt->execute();
        $upcomingEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT pa.*, p.full_name, p.profile_image FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id
             WHERE p.sport_id = ? AND pa.performance_score IS NOT NULL ORDER BY pa.performance_score DESC LIMIT 5"
        );
        $stmt->bind_param('i', $mySportId);
        $stmt->execute();
        $topPlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $avgRow = $conn->query('SELECT AVG(performance_score) a FROM performance_analysis WHERE sport_id = ' . (int) $mySportId)->fetch_assoc();
        $avgTeamPerformance = $avgRow['a'];
    }

    // Unread messages: conversations this coach belongs to with newer messages than last_read_at.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM messages m
         JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_role = 'coach' AND cm.user_id = ?
         WHERE m.is_deleted = 0 AND NOT (m.sender_role = 'coach' AND m.sender_id = ?)
           AND m.sent_at > COALESCE(cm.last_read_at, '1970-01-01')"
    );
    $stmt->bind_param('ii', $coachId, $coachId);
    $stmt->execute();
    $stats['unread_messages'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE (receiver_role = 'coach' AND (receiver_id = ? OR receiver_id IS NULL)) AND status = 'unread'");
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $stats['unread_notifications'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM notifications WHERE (receiver_role = 'coach' AND (receiver_id = ? OR receiver_id IS NULL)) ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $recentNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Coach Dashboard';
$greetingHour = (int) date('G');
$greeting = $greetingHour < 12 ? 'Good morning' : ($greetingHour < 17 ? 'Good afternoon' : 'Good evening');
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/events.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/performance.css">
<style>
  .welcome-banner{
    background:linear-gradient(120deg, var(--primary-dark), var(--primary) 120%);
    border-radius:var(--radius); padding:26px 28px; color:#fff; position:relative; overflow:hidden;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; margin-bottom:24px;
  }
  .welcome-banner::after{ content:""; position:absolute; inset:0; background:radial-gradient(circle at 90% -10%, rgba(255,255,255,.18), transparent 55%); }
  .welcome-banner-left{ display:flex; align-items:center; gap:16px; position:relative; z-index:1; }
  .welcome-banner-left img{ width:60px; height:60px; border-radius:50%; object-fit:cover; border:3px solid rgba(255,255,255,.5); }
  .welcome-banner h1{ font-size:1.35rem; margin:0; }
  .welcome-banner .banner-sub{ opacity:.9; font-size:.88rem; margin-top:2px; }
  .welcome-banner-badges{ display:flex; gap:10px; flex-wrap:wrap; position:relative; z-index:1; }
  .welcome-badge{ background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.28); border-radius:10px; padding:8px 14px; font-size:.8rem; }
  .welcome-badge strong{ display:block; font-family:var(--font-heading); font-size:1.05rem; }
  .list-row{ display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 2px; border-bottom:1px solid var(--border); }
  .list-row:last-child{ border-bottom:none; }
  .list-row-title{ font-weight:600; font-size:.92rem; }
  .list-row-meta{ color:var(--text-muted); font-size:.78rem; margin-top:2px; }
  .empty-state{ text-align:center; padding:26px 10px; color:var(--text-muted); }
  .empty-state i{ font-size:1.6rem; margin-bottom:8px; display:block; opacity:.5; }
  .qa-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
  .qa-btn{
    display:flex; align-items:center; gap:10px; padding:13px 14px; border-radius:var(--radius-sm); border:1px solid var(--border);
    background:var(--surface); font-size:.83rem; font-weight:600; color:var(--text-dark); transition:.2s ease; position:relative;
  }
  .qa-btn i{ font-size:1.05rem; color:var(--primary); width:20px; text-align:center; }
  .qa-btn:hover{ border-color:var(--primary); transform:translateY(-3px); box-shadow:var(--shadow-sm); }
  .qa-btn .qa-badge{ position:absolute; top:8px; right:8px; background:#dc3545; color:#fff; font-size:.65rem; font-weight:700; border-radius:10px; padding:1px 6px; }
  @media (max-width:480px){ .qa-grid{ grid-template-columns:1fr; } }
</style>
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/coach_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/coach_topbar.php'; ?>

    <div class="admin-content">

      <div class="welcome-banner">
        <div class="welcome-banner-left">
          <img src="<?php echo currentProfileImage(); ?>" alt="">
          <div>
            <h1><?php echo $greeting; ?>, Coach <?php echo safeOut($_SESSION['full_name']); ?> <i class="fa-solid fa-whistle"></i></h1>
            <div class="banner-sub"><?php echo safeOut($mySportName ?? 'No sport assigned'); ?> &middot; <?php echo date('l, F j, Y'); ?></div>
          </div>
        </div>
        <div class="welcome-banner-badges">
          <div class="welcome-badge"><strong><?php echo $stats['players']; ?></strong>Players</div>
          <div class="welcome-badge"><strong><?php echo $stats['teams']; ?></strong>Teams</div>
          <?php if ($avgTeamPerformance !== null): ?>
            <div class="welcome-badge"><strong><?php echo round($avgTeamPerformance, 1); ?></strong>Avg Score</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="stat-grid" style="grid-template-columns:repeat(5,1fr);">
        <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-users"></i></div><div class="stat-card-value" data-counter="<?php echo $stats['players']; ?>">0</div><div class="stat-card-label">My Players</div></div>
        <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-people-group"></i></div><div class="stat-card-value" data-counter="<?php echo $stats['teams']; ?>">0</div><div class="stat-card-label">My Teams</div></div>
        <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-trophy"></i></div><div class="stat-card-value" data-counter="<?php echo $stats['upcoming_matches']; ?>">0</div><div class="stat-card-label">Upcoming Matches</div></div>
        <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-calendar-days"></i></div><div class="stat-card-value" data-counter="<?php echo $stats['upcoming_events']; ?>">0</div><div class="stat-card-label">Upcoming Events</div></div>
        <div class="stat-card" style="background:linear-gradient(135deg,#6f42c1,#4a2a8a);"><div class="stat-card-icon"><i class="fa-solid fa-comments"></i></div><div class="stat-card-value" data-counter="<?php echo $stats['unread_messages']; ?>">0</div><div class="stat-card-label">Unread Messages</div></div>
      </div>

      <div class="row g-3 mb-1">
        <div class="col-lg-8">

          <div class="panel">
            <div class="panel-head"><h2><i class="fa-solid fa-trophy me-2"></i>Upcoming Matches</h2><a href="<?php echo BASE_URL; ?>/coach/matches/index.php" class="btn btn-sm btn-outline-secondary">View All</a></div>
            <?php if (empty($upcomingMatches)): ?>
              <div class="empty-state"><i class="fa-solid fa-trophy"></i>No upcoming matches scheduled.</div>
            <?php else: ?>
              <?php foreach ($upcomingMatches as $m): ?>
                <div class="list-row">
                  <div>
                    <div class="list-row-title"><?php echo safeOut($m['match_title'] ?: (($m['team_a_name'] ?? 'TBD') . ($m['team_b_name'] ? ' vs ' . $m['team_b_name'] : ''))); ?></div>
                    <div class="list-row-meta"><i class="fa-solid fa-location-dot me-1"></i><?php echo safeOut($m['venue'] ?? 'Venue TBA'); ?> &middot; <?php echo date('M j, Y', strtotime($m['match_date'])); ?></div>
                  </div>
                  <span class="event-mini-badge badge-<?php echo $m['status']; ?>"><?php echo safeOut($m['status']); ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="panel">
            <div class="panel-head"><h2><i class="fa-solid fa-calendar-days me-2"></i>Upcoming Events</h2><a href="<?php echo BASE_URL; ?>/coach/events/index.php" class="btn btn-sm btn-outline-secondary">View All</a></div>
            <?php if (empty($upcomingEvents)): ?>
              <div class="empty-state"><i class="fa-solid fa-calendar-days"></i>No upcoming events.</div>
            <?php else: ?>
              <?php foreach ($upcomingEvents as $e): ?>
                <div class="list-row">
                  <div>
                    <div class="list-row-title"><?php echo safeOut($e['title']); ?></div>
                    <div class="list-row-meta"><i class="fa-solid fa-location-dot me-1"></i><?php echo safeOut($e['venue'] ?? 'Venue TBA'); ?> &middot; <?php echo date('M j, Y', strtotime($e['event_date'])); ?></div>
                  </div>
                  <span class="event-mini-badge badge-<?php echo str_replace(' ', '', $e['status']); ?>"><?php echo safeOut($e['status']); ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="panel">
            <div class="panel-head"><h2><i class="fa-solid fa-star me-2"></i>Top Performing Players</h2><a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-sm btn-outline-secondary">View Performance</a></div>
            <?php if (empty($topPlayers)): ?>
              <div class="empty-state"><i class="fa-solid fa-star"></i>No performance data available yet.</div>
            <?php else: ?>
              <div class="row g-3 align-items-center">
                <div class="col-md-7">
                  <?php foreach ($topPlayers as $i => $p): ?>
                    <a href="<?php echo BASE_URL; ?>/performance/player.php?id=<?php echo $p['player_id']; ?>" class="perf-rank-row">
                      <span class="perf-rank-num">#<?php echo $i + 1; ?></span>
                      <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>">
                      <span class="flex-fill"><?php echo safeOut($p['full_name']); ?></span>
                      <span class="perf-score-pill"><?php echo round($p['performance_score'], 1); ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
                <div class="col-md-5">
                  <div class="chart-wrap" style="height:200px;"><canvas id="chartTopPlayers"></canvas></div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="panel">
            <div class="panel-head"><h2>Quick Actions</h2></div>
            <div class="qa-grid">
              <a href="<?php echo BASE_URL; ?>/coach/team/index.php" class="qa-btn"><i class="fa-solid fa-people-group"></i>My Teams</a>
              <a href="<?php echo BASE_URL; ?>/coach/events/index.php" class="qa-btn"><i class="fa-solid fa-calendar-days"></i>Events</a>
              <a href="<?php echo BASE_URL; ?>/coach/matches/index.php" class="qa-btn"><i class="fa-solid fa-trophy"></i>Matches</a>
              <a href="<?php echo BASE_URL; ?>/performance/index.php" class="qa-btn"><i class="fa-solid fa-chart-line"></i>Performance</a>
              <a href="<?php echo BASE_URL; ?>/coach/player_performance.php" class="qa-btn"><i class="fa-solid fa-star"></i>Rate Players</a>
              <a href="<?php echo BASE_URL; ?>/performance/compare.php" class="qa-btn"><i class="fa-solid fa-scale-balanced"></i>Compare</a>
              <a href="<?php echo BASE_URL; ?>/chat/index.php" class="qa-btn"><i class="fa-solid fa-comments"></i>Messages<?php if ($stats['unread_messages'] > 0): ?><span class="qa-badge"><?php echo $stats['unread_messages']; ?></span><?php endif; ?></a>
              <a href="<?php echo BASE_URL; ?>/chat/groups.php" class="qa-btn"><i class="fa-solid fa-people-roof"></i>Groups</a>
            </div>
          </div>

          <?php if ($avgTeamPerformance !== null): ?>
          <div class="panel perf-highlight-card text-center">
            <span class="perf-highlight-label">Average Sport Performance</span>
            <div class="perf-score-circle mt-2" style="width:72px;height:72px;font-size:1.3rem;"><?php echo round($avgTeamPerformance, 1); ?></div>
          </div>
          <?php endif; ?>

          <div class="panel">
            <div class="panel-head"><h2>Recent Notifications</h2></div>
            <?php if (empty($recentNotifications)): ?>
              <div class="empty-state"><i class="fa-solid fa-bell"></i>No new notifications.</div>
            <?php else: ?>
              <?php foreach ($recentNotifications as $n): ?>
                <div class="list-row">
                  <div>
                    <div class="list-row-title" style="font-size:.85rem;"><?php echo safeOut($n['title']); ?></div>
                    <div class="list-row-meta"><?php echo timeAgo($n['created_at']); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
              <a href="<?php echo BASE_URL; ?>/notifications/index.php" class="btn btn-sm btn-outline-secondary w-100 mt-2">View All</a>
            <?php endif; ?>
          </div>
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
<?php if (!empty($topPlayers)): ?>
<script>
(function () {
  var labels = <?php echo json_encode(array_map(fn($p) => $p['full_name'], $topPlayers)); ?>;
  var scores = <?php echo json_encode(array_map(fn($p) => round((float) $p['performance_score'], 1), $topPlayers)); ?>;
  var canvas = document.getElementById('chartTopPlayers');
  if (canvas && typeof Chart !== 'undefined') {
    new Chart(canvas, {
      type: 'bar',
      data: { labels: labels, datasets: [{ label: 'Score', data: scores, backgroundColor: '#0B5ED7', borderRadius: 6 }] },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        scales: { x: { beginAtZero: true, max: 10 }, y: { grid: { display: false } } },
        plugins: { legend: { display: false } }
      }
    });
  }
})();
</script>
<?php endif; ?>
</body>
</html>
