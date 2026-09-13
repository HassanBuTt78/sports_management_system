<?php
/**
 * ============================================================
 * admin/coach/assign_players.php
 * ------------------------------------------------------------
 * Feature 5: a coach can only manage players from their own
 * sport. This page lists every player in the coach's sport
 * (regardless of current assignment) and lets the admin check/
 * uncheck who reports to this coach — a Football coach only
 * ever sees Football players here, enforced by the WHERE
 * sport_id = ? clause, not by client-side filtering.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$coachId = (int) ($_GET['coach_id'] ?? 0);
if ($coachId <= 0) {
    redirectTo(BASE_URL . '/admin/coach/index.php');
}

$coach = null;
if ($conn) {
    $stmt = $conn->prepare(
        'SELECT c.*, s.sport_name FROM coaches c LEFT JOIN sports s ON c.sport_id = s.sport_id WHERE c.coach_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $coach = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$coach) {
    redirectTo(BASE_URL . '/admin/coach/index.php');
}

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $selectedIds = array_map('intval', $_POST['player_ids'] ?? []);

    // Every player of this sport currently assigned to this coach.
    $stmt = $conn->prepare('SELECT player_id FROM players WHERE sport_id = ? AND coach_id = ?');
    $stmt->bind_param('ii', $coach['sport_id'], $coachId);
    $stmt->execute();
    $currentlyAssigned = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'player_id');
    $stmt->close();

    $toAssign   = array_diff($selectedIds, $currentlyAssigned);
    $toUnassign = array_diff($currentlyAssigned, $selectedIds);

    foreach ($toAssign as $pid) {
        $stmt = $conn->prepare('UPDATE players SET coach_id = ? WHERE player_id = ? AND sport_id = ?');
        $stmt->bind_param('iii', $coachId, $pid, $coach['sport_id']);
        $stmt->execute();
        $stmt->close();
    }
    foreach ($toUnassign as $pid) {
        $stmt = $conn->prepare('UPDATE players SET coach_id = NULL WHERE player_id = ? AND coach_id = ?');
        $stmt->bind_param('ii', $pid, $coachId);
        $stmt->execute();
        $stmt->close();
    }

    logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Updated player assignments for coach #{$coachId}");
    $saved = true;
}

// ---------- All players in this coach's sport ----------
$players = [];
if ($conn) {
    $stmt = $conn->prepare(
        'SELECT p.player_id, p.full_name, p.roll_no, p.profile_image, p.status, p.coach_id
         FROM players p WHERE p.sport_id = ? ORDER BY p.full_name'
    );
    $stmt->bind_param('i', $coach['sport_id']);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Assign Players';
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
        <div>
          <h1>Assign Players</h1>
          <p>Choose which <?php echo safeOut($coach['sport_name']); ?> players report to <?php echo safeOut($coach['full_name']); ?>.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/coach/view.php?id=<?php echo $coachId; ?>" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Coach
        </a>
      </div>

      <?php if ($saved): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Player assignments updated.</div>
      <?php endif; ?>

      <div class="alert alert-info">
        <i class="fa-solid fa-circle-info me-1"></i>
        Only <strong><?php echo safeOut($coach['sport_name']); ?></strong> players appear here — a coach can only be assigned players from their own sport.
      </div>

      <div class="panel">
        <?php if (empty($players)): ?>
          <p class="text-muted mb-0">No players registered for <?php echo safeOut($coach['sport_name']); ?> yet.</p>
        <?php else: ?>
          <form method="POST" action="assign_players.php?coach_id=<?php echo $coachId; ?>">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th style="width:50px;"></th><th>Photo</th><th>Name</th><th>Roll No</th><th>Status</th><th>Currently Assigned To</th></tr></thead>
                <tbody>
                  <?php foreach ($players as $p): ?>
                    <tr>
                      <td><input type="checkbox" class="form-check-input" name="player_ids[]" value="<?php echo $p['player_id']; ?>"
                                 <?php echo (int) $p['coach_id'] === $coachId ? 'checked' : ''; ?>></td>
                      <td><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px; height:32px; border-radius:50%; object-fit:cover;"></td>
                      <td><?php echo safeOut($p['full_name']); ?></td>
                      <td><?php echo safeOut($p['roll_no']); ?></td>
                      <td><span class="status-pill status-<?php echo $p['status']; ?>"><?php echo safeOut($p['status']); ?></span></td>
                      <td>
                        <?php if ($p['coach_id'] === null): ?>
                          <span class="text-muted">Unassigned</span>
                        <?php elseif ((int) $p['coach_id'] === $coachId): ?>
                          <span class="status-pill status-active">This coach</span>
                        <?php else: ?>
                          <span class="text-muted">Another coach</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <button type="submit" class="btn btn-auth-submit mt-2" style="width:auto;">
              <i class="fa-solid fa-check me-2"></i>Save Assignments
            </button>
          </form>
        <?php endif; ?>
      </div>

    </div>
  <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
