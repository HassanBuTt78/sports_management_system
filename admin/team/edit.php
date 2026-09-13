<?php
/**
 * ============================================================
 * admin/team/edit.php
 * ------------------------------------------------------------
 * Edit an existing team. Captain/Vice-Captain dropdowns are
 * populated ONLY from players currently on this team (WHERE
 * team_id = ?) — enforcing "Captain must belong to that team"
 * by construction rather than by a separate validation check.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$teamId = (int) ($_GET['id'] ?? 0);
if ($teamId <= 0) {
    redirectTo(BASE_URL . '/admin/team/index.php');
}

$errors = [];
$saved = false;
$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];

$team = null;
$teamMembers = [];
if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM teams WHERE team_id = ? LIMIT 1');
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($team) {
        $stmt = $conn->prepare('SELECT player_id, full_name FROM players WHERE team_id = ? ORDER BY full_name');
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $teamMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
if (!$team) {
    redirectTo(BASE_URL . '/admin/team/index.php');
}

$old = $team;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    foreach (['team_name','sport_id','coach_id','captain_id','vice_captain_id','description','status'] as $key) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
    }

    if ($old['team_name'] === '') $errors[] = 'Team name is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if (!in_array($old['status'], ['active', 'inactive'], true)) $old['status'] = 'active';

    if ($old['team_name'] !== '' && !isFieldUnique($conn, 'teams', 'team_name', $old['team_name'], $teamId, 'team_id')) {
        $errors[] = 'A team with this name already exists.';
    }

    $coachId = null;
    if ($old['coach_id'] !== '' && ctype_digit($old['coach_id'])) {
        $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
        $stmt->bind_param('i', $old['coach_id']);
        $stmt->execute();
        $coachRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$coachRow || (int) $coachRow['sport_id'] !== (int) $old['sport_id']) {
            $errors[] = 'The selected coach does not belong to the selected sport.';
        } else {
            $coachId = (int) $old['coach_id'];
        }
    }

    // Captain / Vice-Captain must both be current members of THIS team.
    $memberIds = array_column($teamMembers, 'player_id');
    $captainId = null;
    if ($old['captain_id'] !== '' && ctype_digit($old['captain_id'])) {
        if (!in_array((int) $old['captain_id'], $memberIds, true)) {
            $errors[] = 'Captain must be a current member of this team.';
        } else {
            $captainId = (int) $old['captain_id'];
        }
    }
    $viceCaptainId = null;
    if ($old['vice_captain_id'] !== '' && ctype_digit($old['vice_captain_id'])) {
        if (!in_array((int) $old['vice_captain_id'], $memberIds, true)) {
            $errors[] = 'Vice-Captain must be a current member of this team.';
        } else {
            $viceCaptainId = (int) $old['vice_captain_id'];
        }
    }
    if ($captainId !== null && $captainId === $viceCaptainId) {
        $errors[] = 'Captain and Vice-Captain cannot be the same player.';
    }

    $logoCheck = validateUploadedImage($_FILES['team_logo'] ?? []);
    if (!$logoCheck['valid']) {
        $errors[] = $logoCheck['error'];
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

        $sportId = (int) $old['sport_id'];
        $description = $old['description'] !== '' ? $old['description'] : null;

        // Verified type string (see build note in Module 7 README):
        // sportId(i) coachId(i) team_name(s) captainId(i) viceCaptainId(i) logoPath(s) description(s) status(s) teamId(i)
        $stmt = $conn->prepare(
            'UPDATE teams SET sport_id=?, coach_id=?, team_name=?, captain_id=?, vice_captain_id=?,
             team_logo=?, description=?, status=? WHERE team_id = ?'
        );
        $stmt->bind_param(
            'iisiisssi',
            $sportId, $coachId, $old['team_name'], $captainId, $viceCaptainId, $logoPath, $description, $old['status'], $teamId
        );

        if ($stmt->execute()) {
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Updated team: {$old['team_name']} (#{$teamId})");
            $saved = true;
            $team = array_merge($team, $old, ['team_logo' => $logoPath, 'captain_id' => $captainId, 'vice_captain_id' => $viceCaptainId]);
            $old = $team;
        } else {
            $stmt->close();
            $errors[] = 'Could not update the team. Please try again.';
        }
    }
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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Edit Team</h1><p>Update <?php echo safeOut($team['team_name']); ?>.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/team/index.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Teams
        </a>
      </div>

      <?php if ($saved): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Team updated successfully.</div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if (empty($teamMembers)): ?>
        <div class="alert alert-warning">
          <i class="fa-solid fa-triangle-exclamation me-1"></i>
          This team has no players yet, so Captain/Vice-Captain can't be set.
          <a href="<?php echo BASE_URL; ?>/admin/team/assign_players.php?team_id=<?php echo $teamId; ?>">Assign players first &rarr;</a>
        </div>
      <?php endif; ?>

      <div class="panel">
        <form method="POST" action="edit.php?id=<?php echo $teamId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
          <div class="row g-4">

            <div class="col-lg-3 text-center">
              <label class="form-label fw-semibold d-block">Team Logo</label>
              <div class="image-upload-preview team-logo-preview" id="imagePreviewWrap">
                <img id="imagePreview" src="<?php echo $team['team_logo'] ? UPLOADS_URL . '/' . $team['team_logo'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="Preview">
              </div>
              <input type="file" class="form-control mt-2" name="team_logo" id="profile_image" accept=".jpg,.jpeg,.png">
              <div class="form-text">Leave blank to keep the current logo. Max 2MB.</div>
            </div>

            <div class="col-lg-9">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Team Name *</label>
                  <input type="text" name="team_name" class="form-control" value="<?php echo safeOut($old['team_name']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Team ID</label>
                  <input type="text" class="form-control" value="<?php echo safeOut(formatEmployeeId('TEAM', $teamId)); ?>" disabled>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Sport *</label>
                  <select name="sport_id" id="sport_id" class="form-select" required>
                    <?php foreach ($sports as $s): ?>
                      <option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>>
                        <?php echo safeOut($s['sport_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Coach</label>
                  <select name="coach_id" id="coach_id" class="form-select" data-current="<?php echo safeOut((string) $old['coach_id']); ?>">
                    <option value="">Loading...</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Captain</label>
                  <select name="captain_id" class="form-select" <?php echo empty($teamMembers) ? 'disabled' : ''; ?>>
                    <option value="">No Captain</option>
                    <?php foreach ($teamMembers as $m): ?>
                      <option value="<?php echo $m['player_id']; ?>" <?php echo (int) $old['captain_id'] === (int) $m['player_id'] ? 'selected' : ''; ?>>
                        <?php echo safeOut($m['full_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Vice-Captain</label>
                  <select name="vice_captain_id" class="form-select" <?php echo empty($teamMembers) ? 'disabled' : ''; ?>>
                    <option value="">No Vice-Captain</option>
                    <?php foreach ($teamMembers as $m): ?>
                      <option value="<?php echo $m['player_id']; ?>" <?php echo (int) $old['vice_captain_id'] === (int) $m['player_id'] ? 'selected' : ''; ?>>
                        <?php echo safeOut($m['full_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <?php if (empty($teamMembers)): ?>
                  <div class="col-12">
                    <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
                      <span><i class="fa-solid fa-circle-info me-1"></i>No players on this team yet — Captain/Vice-Captain will unlock once you add some.</span>
                      <a href="<?php echo BASE_URL; ?>/admin/team/assign_players.php?team_id=<?php echo $teamId; ?>" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-people-arrows me-1"></i>Assign Players Now
                      </a>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Status</label>
                  <select name="status" class="form-select">
                    <option value="active" <?php echo $old['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $old['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Team Description</label>
                  <textarea name="description" class="form-control" rows="3"><?php echo safeOut((string) $old['description']); ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-auth-submit mt-4" style="width:auto; padding-left:28px; padding-right:28px;">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
          </button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/team.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
