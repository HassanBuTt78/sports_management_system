<?php
/**
 * ============================================================
 * player/team/view.php
 * ------------------------------------------------------------
 * Player: "View own team only, cannot edit." No team_id in the
 * URL at all — it's always pulled from the player's own session,
 * so there's no way to view a different team by changing a
 * query parameter (there isn't one to change).
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('player');

$playerId = (int) $_SESSION['user_id'];
$teamId = $_SESSION['team_id'] ?? null;

$team = null;
$members = [];
$stats = ['player_count' => 0, 'matches_played' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'avg_rating' => 0, 'avg_score' => 0];

if ($conn && $teamId) {
    $stmt = $conn->prepare(
        'SELECT t.*, s.sport_name, c.full_name AS coach_name, cap.full_name AS captain_name, vc.full_name AS vice_captain_name
         FROM teams t
         LEFT JOIN sports s ON t.sport_id = s.sport_id
         LEFT JOIN coaches c ON t.coach_id = c.coach_id
         LEFT JOIN players cap ON t.captain_id = cap.player_id
         LEFT JOIN players vc ON t.vice_captain_id = vc.player_id
         WHERE t.team_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($team) {
        $stmt = $conn->prepare(
            'SELECT p.player_id, p.full_name, p.profile_image, p.position, p.status, pa.average_rating
             FROM players p LEFT JOIN performance_analysis pa ON pa.player_id = p.player_id
             WHERE p.team_id = ? ORDER BY p.full_name'
        );
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stats = getTeamStats($conn, (int) $teamId);
    }
}

$pageTitle = 'My Team';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/team.css">
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
    <div><h1><i class="fa-solid fa-people-group me-2"></i>My Team</h1><p class="text-muted">Read-only — team changes are managed by your coach or the Administrator.</p></div>
    <a href="<?php echo BASE_URL; ?>/player/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard</a>
  </div>

  <?php if (!$team): ?>
    <div class="panel text-center py-5">
      <p class="text-muted mb-0">You haven't been assigned to a team yet. Contact your Coach or the Administrator.</p>
    </div>
  <?php else: ?>

    <div class="grid-2col">
      <div class="panel text-center">
        <img src="<?php echo $team['team_logo'] ? UPLOADS_URL . '/' . $team['team_logo'] : ASSETS_URL . '/images/logo.svg'; ?>"
             alt="Logo" style="width:110px; height:110px; border-radius:20px; object-fit:cover; margin-bottom:14px; background:var(--bg);">
        <h3 class="mb-0"><?php echo safeOut($team['team_name']); ?></h3>
        <p class="text-muted mb-2"><?php echo safeOut($team['sport_name'] ?? '—'); ?></p>
        <span class="status-pill status-<?php echo $team['status']; ?>"><?php echo safeOut($team['status']); ?></span>
        <table class="table table-borderless text-start mt-3">
          <tbody>
            <tr><th style="width:150px;">Coach</th><td><?php echo safeOut($team['coach_name'] ?? '—'); ?></td></tr>
            <tr><th>Captain</th><td><?php echo safeOut($team['captain_name'] ?? '—'); ?><?php echo (int) $team['captain_id'] === $playerId ? ' <span class="status-pill status-active">You</span>' : ''; ?></td></tr>
            <tr><th>Vice-Captain</th><td><?php echo safeOut($team['vice_captain_name'] ?? '—'); ?><?php echo (int) $team['vice_captain_id'] === $playerId ? ' <span class="status-pill status-active">You</span>' : ''; ?></td></tr>
            <tr><th>Players</th><td><?php echo $stats['player_count']; ?></td></tr>
          </tbody>
        </table>
        <?php if ($team['description']): ?>
          <p class="text-muted text-start mt-2" style="font-size:.88rem;"><?php echo nl2br(safeOut($team['description'])); ?></p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-chart-line me-2"></i>Team Statistics</h2></div>
        <div class="row g-3 text-center">
          <div class="col-4"><div class="health-item"><div class="health-value"><?php echo $stats['matches_played']; ?></div><div class="health-label">Matches</div></div></div>
          <div class="col-4"><div class="health-item"><div class="health-value" style="color:var(--secondary);"><?php echo $stats['wins']; ?></div><div class="health-label">Wins</div></div></div>
          <div class="col-4"><div class="health-item"><div class="health-value" style="color:#c1443c;"><?php echo $stats['losses']; ?></div><div class="health-label">Losses</div></div></div>
          <div class="col-6"><div class="health-item"><div class="health-value"><?php echo $stats['avg_rating'] ?: '—'; ?></div><div class="health-label">Avg Rating</div></div></div>
          <div class="col-6"><div class="health-item"><div class="health-value"><?php echo $stats['avg_score'] ?: '—'; ?></div><div class="health-label">Avg Score</div></div></div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><i class="fa-solid fa-people-group me-2"></i>Team Members</h2></div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Photo</th><th>Name</th><th>Position</th><th>Rating</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <tr <?php echo (int) $m['player_id'] === $playerId ? 'style="background:rgba(11,94,215,.05);"' : ''; ?>>
                <td><img src="<?php echo $m['profile_image'] ? UPLOADS_URL . '/' . $m['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px; height:32px; border-radius:50%; object-fit:cover;"></td>
                <td><?php echo safeOut($m['full_name']); ?><?php echo (int) $m['player_id'] === $playerId ? ' <span class="text-muted">(you)</span>' : ''; ?></td>
                <td><?php echo safeOut($m['position'] ?? '—'); ?></td>
                <td><?php echo $m['average_rating'] ? round((float) $m['average_rating'], 1) . ' ★' : '—'; ?></td>
                <td><span class="status-pill status-<?php echo $m['status']; ?>"><?php echo safeOut($m['status']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
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
