<?php
/**
 * ============================================================
 * admin/team/assign_players.php
 * ------------------------------------------------------------
 * Team Member Management (admin): add, remove, and transfer
 * players for any team. "Players cannot belong to two teams in
 * the same sport" is enforced by construction — players.team_id
 * is a single column, so assigning a player here automatically
 * moves them off whatever other team of the same sport they were
 * previously on. That's exactly what "Transfer" means in the
 * brief — there's no separate transfer action needed.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$teamId = (int) ($_GET['team_id'] ?? 0);
if ($teamId <= 0) {
    redirectTo(BASE_URL . '/admin/team/index.php');
}

$team = null;
if ($conn) {
    $stmt = $conn->prepare(
        'SELECT t.*, s.sport_name FROM teams t LEFT JOIN sports s ON t.sport_id = s.sport_id WHERE t.team_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$team) {
    redirectTo(BASE_URL . '/admin/team/index.php');
}

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $selectedIds = array_map('intval', $_POST['player_ids'] ?? []);
    $positions   = $_POST['positions'] ?? []; // player_id => position text

    $stmt = $conn->prepare('SELECT player_id FROM players WHERE sport_id = ? AND team_id = ?');
    $stmt->bind_param('ii', $team['sport_id'], $teamId);
    $stmt->execute();
    $currentlyOnTeam = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'player_id');
    $stmt->close();

    $toAssign   = array_diff($selectedIds, $currentlyOnTeam);
    $toUnassign = array_diff($currentlyOnTeam, $selectedIds);

    // Assign (or transfer from another team of the same sport — team_id is
    // a single column, so setting it here IS the transfer).
    foreach ($toAssign as $pid) {
        $position = isset($positions[$pid]) ? cleanInput($positions[$pid]) : null;
        $stmt = $conn->prepare('UPDATE players SET team_id = ?, position = ? WHERE player_id = ? AND sport_id = ?');
        $stmt->bind_param('isii', $teamId, $position, $pid, $team['sport_id']);
        $stmt->execute();
        $stmt->close();
    }
    // Update position for players who stay on the team but had their position field edited.
    foreach ($selectedIds as $pid) {
        if (in_array($pid, $toAssign, true)) continue; // already handled above
        $position = isset($positions[$pid]) ? cleanInput($positions[$pid]) : null;
        $stmt = $conn->prepare('UPDATE players SET position = ? WHERE player_id = ? AND team_id = ?');
        $stmt->bind_param('sii', $position, $pid, $teamId);
        $stmt->execute();
        $stmt->close();
    }
    // Unassign (remove).
    foreach ($toUnassign as $pid) {
        $stmt = $conn->prepare('UPDATE players SET team_id = NULL, position = NULL WHERE player_id = ? AND team_id = ?');
        $stmt->bind_param('ii', $pid, $teamId);
        $stmt->execute();
        $stmt->close();
    }

    logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Updated roster for team #{$teamId}");
    $saved = true;
}

// ---------- All players in this team's sport, with current team shown ----------
$players = [];
if ($conn) {
    $stmt = $conn->prepare(
        'SELECT p.player_id, p.full_name, p.roll_no, p.profile_image, p.status, p.position, p.team_id, t.team_name
         FROM players p LEFT JOIN teams t ON p.team_id = t.team_id
         WHERE p.sport_id = ? ORDER BY p.full_name'
    );
    $stmt->bind_param('i', $team['sport_id']);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Manage Players';
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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div>
          <h1>Manage Players — <?php echo safeOut($team['team_name']); ?></h1>
          <p>Add, remove, or transfer <?php echo safeOut($team['sport_name']); ?> players. Checking a player who's on
          another <?php echo safeOut($team['sport_name']); ?> team transfers them here.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/team/view.php?id=<?php echo $teamId; ?>" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Team
        </a>
      </div>

      <?php if ($saved): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Roster updated.</div>
      <?php endif; ?>

      <div class="panel">
        <?php if (empty($players)): ?>
          <p class="text-muted mb-0">No players registered for <?php echo safeOut($team['sport_name']); ?> yet.</p>
        <?php else: ?>
          <form method="POST" action="assign_players.php?team_id=<?php echo $teamId; ?>">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th style="width:50px;"></th><th>Photo</th><th>Name</th><th>Roll No</th><th>Position</th><th>Status</th><th>Current Team</th></tr></thead>
                <tbody>
                  <?php foreach ($players as $p): ?>
                    <tr>
                      <td><input type="checkbox" class="form-check-input" name="player_ids[]" value="<?php echo $p['player_id']; ?>"
                                 <?php echo (int) $p['team_id'] === $teamId ? 'checked' : ''; ?>></td>
                      <td><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px; height:32px; border-radius:50%; object-fit:cover;"></td>
                      <td><?php echo safeOut($p['full_name']); ?></td>
                      <td><?php echo safeOut($p['roll_no']); ?></td>
                      <td><input type="text" name="positions[<?php echo $p['player_id']; ?>]" class="form-control form-control-sm" style="max-width:140px;" value="<?php echo safeOut($p['position'] ?? ''); ?>" placeholder="e.g. Striker"></td>
                      <td><span class="status-pill status-<?php echo $p['status']; ?>"><?php echo safeOut($p['status']); ?></span></td>
                      <td>
                        <?php if (!$p['team_id']): ?>
                          <span class="text-muted">Unassigned</span>
                        <?php elseif ((int) $p['team_id'] === $teamId): ?>
                          <span class="status-pill status-active">This team</span>
                        <?php else: ?>
                          <span class="text-muted"><?php echo safeOut($p['team_name']); ?></span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <button type="submit" class="btn btn-auth-submit mt-2" style="width:auto;">
              <i class="fa-solid fa-check me-2"></i>Save Roster
            </button>
          </form>
        <?php endif; ?>
      </div>

    </div>
  <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
