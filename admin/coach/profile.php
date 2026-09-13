<?php
/**
 * ============================================================
 * admin/coach/profile.php
 * ------------------------------------------------------------
 * Feature 6: Coach Profile. The richer companion to view.php —
 * photo, coach info, sport, the full assigned-players roster,
 * events this coach has created, and aggregate performance
 * statistics across their players.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$coachId = (int) ($_GET['id'] ?? 0);
$coach = null;
$assignedPlayers = [];
$eventsCreated = [];
$performanceStats = ['avg_rating' => 0, 'total_matches' => 0, 'top_player' => null];

if ($conn && $coachId > 0) {
    $stmt = $conn->prepare(
        'SELECT c.*, s.sport_name FROM coaches c LEFT JOIN sports s ON c.sport_id = s.sport_id WHERE c.coach_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $coach = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($coach) {
        // ---------- Assigned players ----------
        $stmt = $conn->prepare(
            'SELECT p.player_id, p.full_name, p.roll_no, p.profile_image, p.status, pa.average_rating
             FROM players p LEFT JOIN performance_analysis pa ON pa.player_id = p.player_id
             WHERE p.coach_id = ? ORDER BY p.full_name'
        );
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $assignedPlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ---------- Events created by this coach ----------
        $stmt = $conn->prepare(
            'SELECT event_id, title, venue, event_date, status FROM events WHERE coach_id = ? ORDER BY event_date DESC LIMIT 10'
        );
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $eventsCreated = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ---------- Performance statistics: aggregate across this coach's ratings/scores ----------
        $stmt = $conn->prepare('SELECT AVG(rating) AS avg_r FROM player_ratings WHERE coach_id = ?');
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $performanceStats['avg_rating'] = round((float) ($stmt->get_result()->fetch_assoc()['avg_r'] ?? 0), 2);
        $stmt->close();

        $stmt = $conn->prepare('SELECT COUNT(DISTINCT match_id) c FROM player_scores WHERE coach_id = ?');
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $performanceStats['total_matches'] = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        $stmt = $conn->prepare(
            'SELECT p.full_name, pa.average_rating FROM performance_analysis pa
             JOIN players p ON pa.player_id = p.player_id
             WHERE p.coach_id = ? ORDER BY pa.average_rating DESC LIMIT 1'
        );
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $performanceStats['top_player'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
if (!$coach) {
    redirectTo(BASE_URL . '/admin/coach/index.php');
}

$pageTitle = 'Coach Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo safeOut($coach['full_name']); ?> — Profile | <?php echo SITE_NAME; ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/coach.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Coach Profile</h1><p>Complete record for <?php echo safeOut($coach['full_name']); ?>.</p></div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/admin/coach/edit.php?id=<?php echo $coachId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i>Edit</a>
          <a href="<?php echo BASE_URL; ?>/admin/coach/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
      </div>

      <div class="grid-2col">
        <div class="panel text-center">
          <img src="<?php echo $coach['profile_image'] ? UPLOADS_URL . '/' . $coach['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>"
               alt="Profile" style="width:120px; height:120px; border-radius:50%; object-fit:cover; margin-bottom:14px;">
          <h3 class="mb-0"><?php echo safeOut($coach['full_name']); ?></h3>
          <p class="text-muted mb-1"><?php echo safeOut(formatEmployeeId('COA', $coachId)); ?></p>
          <span class="status-pill status-<?php echo $coach['status']; ?>"><?php echo safeOut($coach['status']); ?></span>
          <table class="table table-borderless text-start mt-3">
            <tbody>
              <tr><th>Sport</th><td><?php echo safeOut($coach['sport_name'] ?? '—'); ?></td></tr>
              <tr><th>Qualification</th><td><?php echo safeOut($coach['qualification'] ?? '—'); ?></td></tr>
              <tr><th>Experience</th><td><?php echo safeOut($coach['experience'] ?? '—'); ?></td></tr>
              <tr><th>Email</th><td><?php echo safeOut($coach['email']); ?></td></tr>
              <tr><th>Phone</th><td><?php echo safeOut($coach['phone'] ?? '—'); ?></td></tr>
            </tbody>
          </table>
        </div>

        <div class="panel">
          <div class="panel-head"><h2><i class="fa-solid fa-chart-line me-2"></i>Performance Statistics</h2></div>
          <div class="row g-3 text-center">
            <div class="col-4"><div class="health-item"><div class="health-value"><?php echo count($assignedPlayers); ?></div><div class="health-label">Assigned Players</div></div></div>
            <div class="col-4"><div class="health-item"><div class="health-value"><?php echo $performanceStats['avg_rating'] ?: '—'; ?></div><div class="health-label">Avg Player Rating</div></div></div>
            <div class="col-4"><div class="health-item"><div class="health-value"><?php echo $performanceStats['total_matches']; ?></div><div class="health-label">Matches Scored</div></div></div>
          </div>
          <?php if ($performanceStats['top_player']): ?>
            <p class="text-muted mt-3 mb-0">
              Top performer: <strong><?php echo safeOut($performanceStats['top_player']['full_name']); ?></strong>
              (<?php echo round((float) $performanceStats['top_player']['average_rating'], 1); ?> avg rating)
            </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- ============ ASSIGNED PLAYERS ============ -->
      <div class="panel">
        <div class="panel-head">
          <h2><i class="fa-solid fa-person-running me-2"></i>Assigned Players</h2>
          <a href="<?php echo BASE_URL; ?>/admin/coach/assign_players.php?coach_id=<?php echo $coachId; ?>" class="panel-sub">Manage Assignments &rarr;</a>
        </div>
        <?php if (empty($assignedPlayers)): ?>
          <p class="text-muted mb-0">No players assigned to this coach yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Photo</th><th>Name</th><th>Roll No</th><th>Status</th><th>Rating</th></tr></thead>
              <tbody>
                <?php foreach ($assignedPlayers as $p): ?>
                  <tr>
                    <td><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px; height:32px; border-radius:50%; object-fit:cover;"></td>
                    <td><a href="<?php echo BASE_URL; ?>/admin/player/view.php?id=<?php echo $p['player_id']; ?>"><?php echo safeOut($p['full_name']); ?></a></td>
                    <td><?php echo safeOut($p['roll_no']); ?></td>
                    <td><span class="status-pill status-<?php echo $p['status']; ?>"><?php echo safeOut($p['status']); ?></span></td>
                    <td><?php echo $p['average_rating'] ? round((float) $p['average_rating'], 1) . ' ★' : '—'; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- ============ EVENTS CREATED ============ -->
      <div class="panel">
        <div class="panel-head">
          <h2><i class="fa-solid fa-calendar-days me-2"></i>Events Created</h2>
          <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="panel-sub">Events Module &rarr;</a>
        </div>
        <?php if (empty($eventsCreated)): ?>
          <p class="text-muted mb-0">This coach hasn't created any events yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Title</th><th>Venue</th><th>Date</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($eventsCreated as $e): ?>
                  <tr>
                    <td><?php echo safeOut($e['title']); ?></td>
                    <td><?php echo safeOut($e['venue'] ?? '—'); ?></td>
                    <td><?php echo date('M j, Y', strtotime($e['event_date'])); ?></td>
                    <td><span class="event-mini-badge badge-<?php echo str_replace(' ', '', $e['status']); ?>"><?php echo safeOut($e['status']); ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/coach.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
