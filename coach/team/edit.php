<?php
/**
 * ============================================================
 * coach/team/edit.php
 * ------------------------------------------------------------
 * Coach: Edit own team + Manage own players, combined on one
 * page (per the file list — coach/team/ has no separate
 * assign_players.php). Sport is locked (a coach can't move
 * their team to a different sport), and the player roster query
 * is scoped to `WHERE sport_id = {this coach's sport}` — a
 * Football coach physically cannot see Cricket players here.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$teamId  = (int) ($_GET['id'] ?? 0);

$team = null;
if ($conn && $teamId > 0) {
    $stmt = $conn->prepare(
        'SELECT t.*, s.sport_name FROM teams t LEFT JOIN sports s ON t.sport_id = s.sport_id
         WHERE t.team_id = ? AND t.coach_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $teamId, $coachId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$team) {
    redirectTo(BASE_URL . '/coach/team/index.php');
}

$errors = [];
$saved = false;
$rosterSaved = false;

// ---------- Team info form ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'team_info' && $conn) {
    $teamName = cleanInput($_POST['team_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $status = cleanInput($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    if ($teamName === '') $errors[] = 'Team name is required.';
    if ($teamName !== '' && !isFieldUnique($conn, 'teams', 'team_name', $teamName, $teamId, 'team_id')) {
        $errors[] = 'A team with this name already exists.';
    }

    $logoCheck = validateUploadedImage($_FILES['team_logo'] ?? []);
    if (!$logoCheck['valid']) $errors[] = $logoCheck['error'];

    // Captain / Vice-Captain must be current members of this team.
    $stmt = $conn->prepare('SELECT player_id FROM players WHERE team_id = ?');
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $memberIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'player_id');
    $stmt->close();

    $captainId = null;
    $captainInput = cleanInput($_POST['captain_id'] ?? '');
    if ($captainInput !== '' && ctype_digit($captainInput)) {
        if (!in_array((int) $captainInput, $memberIds, true)) $errors[] = 'Captain must be a current member of this team.';
        else $captainId = (int) $captainInput;
    }
    $viceCaptainId = null;
    $viceInput = cleanInput($_POST['vice_captain_id'] ?? '');
    if ($viceInput !== '' && ctype_digit($viceInput)) {
        if (!in_array((int) $viceInput, $memberIds, true)) $errors[] = 'Vice-Captain must be a current member of this team.';
        else $viceCaptainId = (int) $viceInput;
    }

    if (empty($errors)) {
        $logoPath = $team['team_logo'];
        if (!empty($_FILES['team_logo']['name'])) {
            $logoResult = storeUploadedImage($_FILES['team_logo'], 'teams');
            if ($logoResult['success'] && $logoResult['path']) {
                deleteUploadedFile($team['team_logo']);
                $logoPath = $logoResult['path'];
            }
        }
        $descriptionVal = $description !== '' ? $description : null;

        $stmt = $conn->prepare(
            'UPDATE teams SET team_name=?, captain_id=?, vice_captain_id=?, team_logo=?, description=?, status=?
             WHERE team_id = ? AND coach_id = ?'
        );
        $stmt->bind_param('siisssii', $teamName, $captainId, $viceCaptainId, $logoPath, $descriptionVal, $status, $teamId, $coachId);
        if ($stmt->execute()) {
            $stmt->close();
            logActivity($conn, 'coach', $coachId, "Updated own team: {$teamName} (#{$teamId})");
            $saved = true;
            $team = array_merge($team, ['team_name' => $teamName, 'captain_id' => $captainId, 'vice_captain_id' => $viceCaptainId, 'team_logo' => $logoPath, 'description' => $descriptionVal, 'status' => $status]);
        } else {
            $stmt->close();
            $errors[] = 'Could not update the team.';
        }
    }
}

// ---------- Roster form (own sport only) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'roster' && $conn) {
    $selectedIds = array_map('intval', $_POST['player_ids'] ?? []);
    $positions   = $_POST['positions'] ?? [];

    $stmt = $conn->prepare('SELECT player_id FROM players WHERE sport_id = ? AND team_id = ?');
    $stmt->bind_param('ii', $team['sport_id'], $teamId);
    $stmt->execute();
    $currentlyOnTeam = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'player_id');
    $stmt->close();

    $toAssign   = array_diff($selectedIds, $currentlyOnTeam);
    $toUnassign = array_diff($currentlyOnTeam, $selectedIds);

    foreach ($toAssign as $pid) {
        $position = isset($positions[$pid]) ? cleanInput($positions[$pid]) : null;
        // Scoped to sport_id — a coach can only ever pull in players from their own sport.
        $stmt = $conn->prepare('UPDATE players SET team_id = ?, position = ? WHERE player_id = ? AND sport_id = ?');
        $stmt->bind_param('isii', $teamId, $position, $pid, $team['sport_id']);
        $stmt->execute();
        $stmt->close();
    }
    foreach ($selectedIds as $pid) {
        if (in_array($pid, $toAssign, true)) continue;
        $position = isset($positions[$pid]) ? cleanInput($positions[$pid]) : null;
        $stmt = $conn->prepare('UPDATE players SET position = ? WHERE player_id = ? AND team_id = ?');
        $stmt->bind_param('sii', $position, $pid, $teamId);
        $stmt->execute();
        $stmt->close();
    }
    foreach ($toUnassign as $pid) {
        $stmt = $conn->prepare('UPDATE players SET team_id = NULL, position = NULL WHERE player_id = ? AND team_id = ?');
        $stmt->bind_param('ii', $pid, $teamId);
        $stmt->execute();
        $stmt->close();
    }

    logActivity($conn, 'coach', $coachId, "Updated roster for own team #{$teamId}");
    $rosterSaved = true;
}

// ---------- Data for the form ----------
$teamMembers = [];
$sportPlayers = [];
if ($conn) {
    $stmt = $conn->prepare('SELECT player_id, full_name FROM players WHERE team_id = ? ORDER BY full_name');
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $teamMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT p.player_id, p.full_name, p.roll_no, p.profile_image, p.status, p.position, p.team_id, t.team_name
         FROM players p LEFT JOIN teams t ON p.team_id = t.team_id
         WHERE p.sport_id = ? ORDER BY p.full_name'
    );
    $stmt->bind_param('i', $team['sport_id']);
    $stmt->execute();
    $sportPlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Edit Team';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/player.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/team.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/coach_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/coach_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1>Edit Team</h1><p><?php echo safeOut($team['team_name']); ?> &middot; <?php echo safeOut($team['sport_name']); ?> (locked — contact Admin to change sport)</p></div>
    <a href="<?php echo BASE_URL; ?>/coach/team/view.php?id=<?php echo $teamId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if ($saved): ?><div class="alert alert-success">Team info updated.</div><?php endif; ?>
  <?php if ($rosterSaved): ?><div class="alert alert-success">Roster updated.</div><?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2>Team Information</h2></div>
    <form method="POST" action="edit.php?id=<?php echo $teamId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="form_type" value="team_info">
      <div class="row g-4">
        <div class="col-lg-3 text-center">
          <div class="image-upload-preview team-logo-preview" id="imagePreviewWrap">
            <img id="imagePreview" src="<?php echo $team['team_logo'] ? UPLOADS_URL . '/' . $team['team_logo'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="Preview">
          </div>
          <input type="file" class="form-control mt-2" name="team_logo" id="profile_image" accept=".jpg,.jpeg,.png">
          <div class="form-text">Max 2MB.</div>
        </div>
        <div class="col-lg-9">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Team Name *</label>
              <input type="text" name="team_name" class="form-control" value="<?php echo safeOut($team['team_name']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status</label>
              <select name="status" class="form-select">
                <option value="active" <?php echo $team['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $team['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Captain</label>
              <select name="captain_id" class="form-select" <?php echo empty($teamMembers) ? 'disabled' : ''; ?>>
                <option value="">No Captain</option>
                <?php foreach ($teamMembers as $m): ?>
                  <option value="<?php echo $m['player_id']; ?>" <?php echo (int) $team['captain_id'] === (int) $m['player_id'] ? 'selected' : ''; ?>><?php echo safeOut($m['full_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Vice-Captain</label>
              <select name="vice_captain_id" class="form-select" <?php echo empty($teamMembers) ? 'disabled' : ''; ?>>
                <option value="">No Vice-Captain</option>
                <?php foreach ($teamMembers as $m): ?>
                  <option value="<?php echo $m['player_id']; ?>" <?php echo (int) $team['vice_captain_id'] === (int) $m['player_id'] ? 'selected' : ''; ?>><?php echo safeOut($m['full_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="2"><?php echo safeOut((string) $team['description']); ?></textarea>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-auth-submit mt-3" style="width:auto;"><i class="fa-solid fa-floppy-disk me-2"></i>Save Team Info</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Manage Players <span class="panel-sub">(<?php echo safeOut($team['sport_name']); ?> only)</span></h2></div>
    <?php if (empty($sportPlayers)): ?>
      <p class="text-muted mb-0">No players registered for <?php echo safeOut($team['sport_name']); ?> yet.</p>
    <?php else: ?>
      <form method="POST" action="edit.php?id=<?php echo $teamId; ?>">
        <input type="hidden" name="form_type" value="roster">
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th style="width:50px;"></th><th>Photo</th><th>Name</th><th>Position</th><th>Status</th><th>Current Team</th></tr></thead>
            <tbody>
              <?php foreach ($sportPlayers as $p): ?>
                <tr>
                  <td><input type="checkbox" class="form-check-input" name="player_ids[]" value="<?php echo $p['player_id']; ?>" <?php echo (int) $p['team_id'] === $teamId ? 'checked' : ''; ?>></td>
                  <td><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px; height:32px; border-radius:50%; object-fit:cover;"></td>
                  <td><?php echo safeOut($p['full_name']); ?></td>
                  <td><input type="text" name="positions[<?php echo $p['player_id']; ?>]" class="form-control form-control-sm" style="max-width:140px;" value="<?php echo safeOut($p['position'] ?? ''); ?>"></td>
                  <td><span class="status-pill status-<?php echo $p['status']; ?>"><?php echo safeOut($p['status']); ?></span></td>
                  <td>
                    <?php if (!$p['team_id']): ?><span class="text-muted">Unassigned</span>
                    <?php elseif ((int) $p['team_id'] === $teamId): ?><span class="status-pill status-active">This team</span>
                    <?php else: ?><span class="text-muted"><?php echo safeOut($p['team_name']); ?></span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn btn-auth-submit mt-2" style="width:auto;"><i class="fa-solid fa-check me-2"></i>Save Roster</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script src="<?php echo BASE_URL; ?>/assets/js/team.js"></script>
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
