<?php
/**
 * ============================================================
 * admin/player/view.php
 * ------------------------------------------------------------
 * Feature 8: Player Profile. Full read-only view — profile
 * picture, complete info, assigned coach/team/sport, match
 * history, events joined, performance summary, and star rating.
 * Links out to the Team/Coach/Event/Performance modules where
 * relevant (placeholders until those modules are built).
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$playerId = (int) ($_GET['id'] ?? 0);
$player = null;
$matchHistory = [];
$eventsJoined = [];
$performance = null;
$avgStars = 0;

if ($conn && $playerId > 0) {
    $stmt = $conn->prepare(
        'SELECT p.*, s.sport_name, c.full_name AS coach_name, c.coach_id AS coach_ref,
                t.team_name, t.team_id AS team_ref
         FROM players p
         LEFT JOIN sports s ON p.sport_id = s.sport_id
         LEFT JOIN coaches c ON p.coach_id = c.coach_id
         LEFT JOIN teams t ON p.team_id = t.team_id
         WHERE p.player_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($player) {
        // ---------- Match history ----------
        $stmt = $conn->prepare(
            "SELECT m.match_id, m.match_date, m.venue, m.status, s.goals, s.runs, s.wickets, s.catches,
                    s.assists, s.points, s.race_time, ta.team_name AS team_a, tb.team_name AS team_b
             FROM player_scores s
             JOIN matches m ON s.match_id = m.match_id
             LEFT JOIN teams ta ON m.team_one = ta.team_id
             LEFT JOIN teams tb ON m.team_two = tb.team_id
             WHERE s.player_id = ? ORDER BY m.match_date DESC LIMIT 10"
        );
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $matchHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ---------- Events joined ----------
        $stmt = $conn->prepare(
            "SELECT e.title, e.event_date, e.venue, e.status, ep.participation_status
             FROM event_participants ep
             JOIN events e ON ep.event_id = e.event_id
             WHERE ep.player_id = ? ORDER BY e.event_date DESC LIMIT 10"
        );
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $eventsJoined = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ---------- Performance summary ----------
        $stmt = $conn->prepare('SELECT * FROM performance_analysis WHERE player_id = ? LIMIT 1');
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $performance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // ---------- Star rating (average of all coach ratings) ----------
        $stmt = $conn->prepare('SELECT AVG(rating) AS avg_r, COUNT(*) AS c FROM player_ratings WHERE player_id = ?');
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $ratingRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $avgStars = $ratingRow['avg_r'] ? round((float) $ratingRow['avg_r'], 1) : 0;
        $ratingCount = (int) ($ratingRow['c'] ?? 0);
    }
}

if (!$player) {
    redirectTo(BASE_URL . '/admin/player/index.php');
}

$pageTitle = 'Player Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo safeOut($player['full_name']); ?> | <?php echo SITE_NAME; ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
        <div><h1>Player Profile</h1><p>Complete record for <?php echo safeOut($player['full_name']); ?>.</p></div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/admin/player_performance.php?id=<?php echo $playerId; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-chart-line me-1"></i>Performance
          </a>
          <a href="<?php echo BASE_URL; ?>/admin/player/edit.php?id=<?php echo $playerId; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-pen me-1"></i>Edit
          </a>
          <a href="<?php echo BASE_URL; ?>/admin/player/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
          </a>
        </div>
      </div>

      <div class="grid-2col">

        <!-- ============ PROFILE CARD ============ -->
        <div class="panel text-center">
          <img src="<?php echo $player['profile_image'] ? UPLOADS_URL . '/' . $player['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>"
               alt="Profile" style="width:120px; height:120px; border-radius:50%; object-fit:cover; margin-bottom:14px;">
          <h3 class="mb-0"><?php echo safeOut($player['full_name']); ?></h3>
          <p class="text-muted mb-2">Roll No: <?php echo safeOut($player['roll_no']); ?></p>
          <span class="status-pill status-<?php echo $player['status']; ?>"><?php echo safeOut($player['status']); ?></span>

          <div class="star-rating-display my-3">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="fa-star <?php echo $i <= round($avgStars) ? 'fa-solid' : 'fa-regular'; ?>"></i>
            <?php endfor; ?>
            <span class="ms-2 text-muted"><?php echo $avgStars ?: '—'; ?> <?php if (!empty($ratingCount)): ?>(<?php echo $ratingCount; ?> ratings)<?php endif; ?></span>
          </div>

          <table class="table table-borderless text-start mt-2">
            <tbody>
              <tr><th>Sport</th><td>
                <a href="<?php echo BASE_URL; ?>/admin/sports/index.php"><?php echo safeOut($player['sport_name'] ?? '—'); ?></a>
              </td></tr>
              <tr><th>Coach</th><td>
                <?php if ($player['coach_name']): ?>
                  <a href="<?php echo BASE_URL; ?>/admin/coaches/view.php?id=<?php echo $player['coach_ref']; ?>"><?php echo safeOut($player['coach_name']); ?></a>
                <?php else: ?>—<?php endif; ?>
              </td></tr>
              <tr><th>Team</th><td>
                <?php if ($player['team_name']): ?>
                  <a href="<?php echo BASE_URL; ?>/admin/teams/view.php?id=<?php echo $player['team_ref']; ?>"><?php echo safeOut($player['team_name']); ?></a>
                <?php else: ?>—<?php endif; ?>
              </td></tr>
              <tr><th>Email</th><td><?php echo safeOut($player['email']); ?></td></tr>
              <tr><th>Phone</th><td><?php echo safeOut($player['phone'] ?? '—'); ?></td></tr>
              <tr><th>Age</th><td><?php echo safeOut((string) ($player['age'] ?? '—')); ?></td></tr>
              <tr><th>Gender</th><td><?php echo safeOut(ucfirst($player['gender'] ?? '—')); ?></td></tr>
              <tr><th>Blood Group</th><td><?php echo safeOut($player['blood_group'] ?? '—'); ?></td></tr>
              <tr><th>Height</th><td><?php echo $player['height'] ? safeOut($player['height'] . ' cm') : '—'; ?></td></tr>
              <tr><th>Weight</th><td><?php echo $player['weight'] ? safeOut($player['weight'] . ' kg') : '—'; ?></td></tr>
              <tr><th>Address</th><td><?php echo safeOut($player['address'] ?? '—'); ?></td></tr>
              <tr><th>Joined</th><td><?php echo date('M j, Y', strtotime($player['created_at'])); ?></td></tr>
            </tbody>
          </table>

          <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetPasswordBtn" data-player-id="<?php echo $playerId; ?>">
              <i class="fa-solid fa-key me-1"></i>Reset Password
            </button>
          </div>

          <table class="table table-borderless text-start mt-3 mb-0">
            <tbody>
              <tr>
                <th style="width:120px;">Password</th>
                <td>
                  <span id="passwordDisplay" style="font-family:monospace;">********</span>
                  <button type="button" class="btn btn-sm btn-outline-secondary ms-2 show-password-btn" data-player-id="<?php echo $playerId; ?>">
                    <i class="fa-solid fa-eye me-1"></i>Show
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="text-muted small mt-1 mb-0">Only Admin can reveal this — it's decrypted server-side on request.</p>
        </div>

        <!-- ============ PERFORMANCE SUMMARY ============ -->
        <div class="panel">
          <div class="panel-head"><h2><i class="fa-solid fa-chart-line me-2"></i>Performance Summary</h2>
            <a href="<?php echo BASE_URL; ?>/admin/performance/index.php?player_id=<?php echo $playerId; ?>" class="panel-sub">Full Performance Module &rarr;</a>
          </div>
          <?php if ($performance): ?>
            <div class="row g-3 text-center">
              <div class="col-4"><div class="health-item"><div class="health-value"><?php echo round((float) $performance['average_rating'], 2); ?></div><div class="health-label">Avg Rating</div></div></div>
              <div class="col-4"><div class="health-item"><div class="health-value"><?php echo round((float) $performance['total_score'], 1); ?></div><div class="health-label">Total Score</div></div></div>
              <div class="col-4"><div class="health-item"><div class="health-value"><?php echo (int) $performance['matches_played']; ?></div><div class="health-label">Matches Played</div></div></div>
            </div>
            <p class="text-muted mt-3 mb-0">
              Season rank: <strong>#<?php echo safeOut((string) ($performance['rank'] ?? '—')); ?></strong> &middot;
              Level: <strong><?php echo safeOut($performance['performance_level'] ?? '—'); ?></strong>
            </p>
          <?php else: ?>
            <p class="text-muted mb-0">No performance analysis has been generated for this player yet.</p>
          <?php endif; ?>
        </div>

      </div>

      <!-- ============ MATCH HISTORY ============ -->
      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-trophy me-2"></i>Match History</h2></div>
        <?php if (empty($matchHistory)): ?>
          <p class="text-muted mb-0">No matches recorded yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Date</th><th>Matchup</th><th>Venue</th><th>Stats</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($matchHistory as $m): ?>
                  <tr>
                    <td><?php echo date('M j, Y', strtotime($m['match_date'])); ?></td>
                    <td><?php echo safeOut($m['team_a'] ?? 'TBD') . ($m['team_b'] ? ' vs ' . safeOut($m['team_b']) : ' (individual)'); ?></td>
                    <td><?php echo safeOut($m['venue'] ?? '—'); ?></td>
                    <td class="mono" style="font-size:.78rem;">
                      <?php
                        $bits = [];
                        if ($m['goals'])   $bits[] = $m['goals'] . 'G';
                        if ($m['runs'])    $bits[] = $m['runs'] . 'R';
                        if ($m['wickets']) $bits[] = $m['wickets'] . 'W';
                        if ($m['catches']) $bits[] = $m['catches'] . 'C';
                        if ($m['assists']) $bits[] = $m['assists'] . 'A';
                        if ($m['points'])  $bits[] = $m['points'] . 'pts';
                        if ($m['race_time']) $bits[] = $m['race_time'] . 's';
                        echo safeOut(implode(' · ', $bits) ?: '—');
                      ?>
                    </td>
                    <td><span class="event-mini-badge badge-<?php echo $m['status']; ?>"><?php echo safeOut($m['status']); ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- ============ EVENTS JOINED ============ -->
      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-calendar-days me-2"></i>Events Joined</h2>
          <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="panel-sub">Events Module &rarr;</a>
        </div>
        <?php if (empty($eventsJoined)): ?>
          <p class="text-muted mb-0">This player hasn't registered for any events yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Event</th><th>Date</th><th>Venue</th><th>Event Status</th><th>Registration</th></tr></thead>
              <tbody>
                <?php foreach ($eventsJoined as $e): ?>
                  <tr>
                    <td><?php echo safeOut($e['title']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($e['event_date'])); ?></td>
                    <td><?php echo safeOut($e['venue'] ?? '—'); ?></td>
                    <td><span class="event-mini-badge badge-<?php echo str_replace(' ', '', $e['status']); ?>"><?php echo safeOut($e['status']); ?></span></td>
                    <td><?php echo safeOut($e['participation_status']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/player.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
