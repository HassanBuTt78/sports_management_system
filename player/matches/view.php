<?php
/**
 * ============================================================
 * player/matches/view.php
 * ------------------------------------------------------------
 * "View personal statistics. View ratings. View coach remarks."
 * Shows the match generally, plus this specific player's own
 * score row and rating (never anyone else's).
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('player');

$playerId = (int) $_SESSION['user_id'];
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$matchId = (int) ($_GET['id'] ?? 0);

$match = null;
if ($conn && $matchId > 0) {
    $stmt = $conn->prepare(
        'SELECT m.*, s.sport_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name,
                c.full_name AS coach_name, tw.team_name AS winner_name
         FROM matches m LEFT JOIN sports s ON m.sport_id = s.sport_id
         LEFT JOIN teams ta ON m.team_one = ta.team_id LEFT JOIN teams tb ON m.team_two = tb.team_id
         LEFT JOIN coaches c ON m.coach_id = c.coach_id LEFT JOIN teams tw ON m.winner_team = tw.team_id
         WHERE m.match_id = ? AND m.sport_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $matchId, $mySportId);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$match) {
    redirectTo(BASE_URL . '/player/matches/index.php');
}

$myScore = null;
$myRating = null;
$remarks = [];
if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM player_scores WHERE match_id = ? AND player_id = ? LIMIT 1');
    $stmt->bind_param('ii', $matchId, $playerId);
    $stmt->execute();
    $myScore = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('SELECT rating, review FROM player_ratings WHERE match_id = ? AND player_id = ? LIMIT 1');
    $stmt->bind_param('ii', $matchId, $playerId);
    $stmt->execute();
    $myRating = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('SELECT r.remark, r.created_at, c.full_name FROM match_remarks r JOIN coaches c ON r.coach_id = c.coach_id WHERE r.match_id = ? ORDER BY r.created_at DESC');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $remarks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$sportName = strtolower($match['sport_name'] ?? '');
$fieldSets = [
    'cricket'  => ['runs' => 'Runs', 'balls' => 'Balls', 'wickets' => 'Wickets', 'overs' => 'Overs', 'catches' => 'Catches', 'run_outs' => 'Run Outs', 'strike_rate' => 'Strike Rate', 'economy' => 'Economy'],
    'football' => ['goals' => 'Goals', 'assists' => 'Assists', 'yellow_cards' => 'Yellow Cards', 'red_cards' => 'Red Cards', 'saves' => 'Saves'],
    'hockey'   => ['goals' => 'Goals', 'assists' => 'Assists', 'yellow_cards' => 'Yellow/Green Cards', 'red_cards' => 'Red Cards', 'saves' => 'Saves'],
];
$fields = $fieldSets[$sportName] ?? ['points' => 'Points', 'custom_score' => 'Score'];

$pageTitle = 'Match Details';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/matches.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/player_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/player_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1>Match Details</h1></div>
    <a href="<?php echo BASE_URL; ?>/player/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
    <a href="<?php echo BASE_URL; ?>/chat/match_chat.php?match_id=<?php echo $matchId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-comments me-1"></i>Discussion</a>
  </div>

  <div class="panel">
    <span class="event-mini-badge badge-<?php echo $match['status']; ?>"><?php echo $match['status'] === 'Upcoming' ? 'Scheduled' : safeOut($match['status']); ?></span>
    <h2 style="margin:.5em 0;"><?php echo safeOut($match['team_a_name'] ?? 'TBD') . ($match['team_b_name'] ? ' vs ' . safeOut($match['team_b_name']) : ''); ?></h2>
    <table class="table table-borderless mb-0">
      <tbody>
        <tr><th style="width:160px;">Sport</th><td><?php echo safeOut($match['sport_name'] ?? '—'); ?></td></tr>
        <tr><th>Coach</th><td><?php echo safeOut($match['coach_name'] ?? 'TBA'); ?></td></tr>
        <tr><th>Venue</th><td><?php echo safeOut($match['venue'] ?? '—'); ?></td></tr>
        <tr><th>Date</th><td><?php echo date('M j, Y', strtotime($match['match_date'])); ?><?php echo $match['match_time'] ? ' at ' . date('g:i A', strtotime($match['match_time'])) : ''; ?></td></tr>
        <?php if ($match['status'] === 'Completed'): ?>
          <tr><th>Winner</th><td><?php echo safeOut($match['winner_name'] ?? '—'); ?></td></tr>
          <tr><th>Summary</th><td><?php echo safeOut($match['result_summary'] ?? '—'); ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>My Statistics</h2></div>
    <?php if (!$myScore): ?>
      <p class="text-muted mb-0">No stats recorded for you in this match yet.</p>
    <?php else: ?>
      <div class="row g-3 text-center">
        <?php foreach ($fields as $col => $label): ?>
          <div class="col-4 col-md-3"><div class="health-item"><div class="health-value"><?php echo $myScore[$col] !== null ? safeOut((string) $myScore[$col]) : '—'; ?></div><div class="health-label"><?php echo $label; ?></div></div></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>My Rating</h2></div>
    <?php if (!$myRating): ?>
      <p class="text-muted mb-0">Not rated for this match yet.</p>
    <?php else: ?>
      <div class="mb-2">
        <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-solid fa-star <?php echo $i <= (int) $myRating['rating'] ? '' : 'text-muted'; ?>" style="color:<?php echo $i <= (int) $myRating['rating'] ? '#FFC107' : '#ddd'; ?>;"></i><?php endfor; ?>
      </div>
      <?php if ($myRating['review']): ?><p class="mb-0"><?php echo nl2br(safeOut($myRating['review'])); ?></p><?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (!empty($remarks)): ?>
  <div class="panel">
    <div class="panel-head"><h2>Coach Remarks</h2></div>
    <?php foreach ($remarks as $r): ?>
      <div class="mb-2 pb-2 border-bottom"><strong><?php echo safeOut($r['full_name']); ?></strong> <span class="text-muted small">— <?php echo timeAgo($r['created_at']); ?></span><p class="mb-0"><?php echo nl2br(safeOut($r['remark'])); ?></p></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
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
