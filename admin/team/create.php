<?php
/**
 * ============================================================
 * admin/team/create.php
 * ------------------------------------------------------------
 * Create Team. Admin-only (coaches don't create teams, per the
 * brief's permissions). Team ID (TEAM-0001 style) is a DISPLAY
 * FORMAT of the real team_id (formatEmployeeId()) — no extra
 * column. Captain/Vice-Captain are intentionally NOT set here:
 * a brand-new team has no members yet, and the brief requires
 * "Captain must belong to that team" — so captain/vice-captain
 * are chosen on edit.php, once players have been assigned.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$success = null;
$old = ['team_name' => '', 'sport_id' => '', 'coach_id' => '', 'description' => '', 'status' => 'active'];

$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    foreach ($old as $key => $default) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? $default));
    }

    if ($old['team_name'] === '') $errors[] = 'Team name is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if (!in_array($old['status'], ['active', 'inactive'], true)) $old['status'] = 'active';

    // ---------- Team Name must be unique ----------
    if ($old['team_name'] !== '' && !isFieldUnique($conn, 'teams', 'team_name', $old['team_name'], null, 'team_id')) {
        $errors[] = 'A team with this name already exists.';
    }

    // ---------- Coach must belong to the selected sport ----------
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

    $logoCheck = validateUploadedImage($_FILES['team_logo'] ?? []);
    if (!$logoCheck['valid']) {
        $errors[] = $logoCheck['error'];
    }

    if (empty($errors)) {
        $logoResult = storeUploadedImage($_FILES['team_logo'], 'teams');
        $sportId = (int) $old['sport_id'];
        $description = $old['description'] !== '' ? $old['description'] : null;

        $stmt = $conn->prepare(
            'INSERT INTO teams (sport_id, coach_id, team_name, team_logo, description, status)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->bind_param('iissss', $sportId, $coachId, $old['team_name'], $logoResult['path'], $description, $old['status']);

        if ($stmt->execute()) {
            $newTeamId = $stmt->insert_id;
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Created team: {$old['team_name']}");

            $success = ['team_id' => $newTeamId, 'employee_id' => formatEmployeeId('TEAM', $newTeamId), 'team_name' => $old['team_name']];
            $old = array_fill_keys(array_keys($old), '');
            $old['status'] = 'active';
        } else {
            $stmt->close();
            $errors[] = 'Could not save the team. Please try again.';
        }
    }
}

$pageTitle = 'Create Team';
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
        <div><h1>Create Team</h1><p>Set up a new team and assign a sport and coach.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/team/index.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Teams
        </a>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check me-1"></i>
          Team <strong><?php echo safeOut($success['team_name']); ?></strong> created as
          <strong><?php echo safeOut($success['employee_id']); ?></strong>.
          <a href="<?php echo BASE_URL; ?>/admin/team/edit.php?id=<?php echo $success['team_id']; ?>">Assign a Captain / Vice-Captain &rarr;</a>
          once you've added players, or
          <a href="<?php echo BASE_URL; ?>/admin/team/assign_players.php?team_id=<?php echo $success['team_id']; ?>">assign players now &rarr;</a>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="panel">
        <form method="POST" action="create.php" enctype="multipart/form-data" class="needs-validation" novalidate>
          <div class="row g-4">

            <div class="col-lg-3 text-center">
              <label class="form-label fw-semibold d-block">Team Logo</label>
              <div class="image-upload-preview team-logo-preview" id="imagePreviewWrap">
                <img id="imagePreview" src="<?php echo ASSETS_URL; ?>/images/logo.svg" alt="Preview">
              </div>
              <input type="file" class="form-control mt-2" name="team_logo" id="profile_image" accept=".jpg,.jpeg,.png">
              <div class="form-text">JPG, JPEG, or PNG. Max 2MB.</div>
            </div>

            <div class="col-lg-9">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Team Name *</label>
                  <input type="text" name="team_name" class="form-control" value="<?php echo safeOut($old['team_name']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Team ID</label>
                  <input type="text" class="form-control" value="Auto-generated after saving (e.g. TEAM-0001)" disabled>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Sport *</label>
                  <select name="sport_id" id="sport_id" class="form-select" required>
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $s): ?>
                      <option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>>
                        <?php echo safeOut($s['sport_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Coach</label>
                  <select name="coach_id" id="coach_id" class="form-select">
                    <option value="">Select Sport First</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Status</label>
                  <select name="status" class="form-select">
                    <option value="active" <?php echo $old['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $old['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Team Description</label>
                  <textarea name="description" class="form-control" rows="3"><?php echo safeOut($old['description']); ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="alert alert-info mt-4 mb-3">
            <i class="fa-solid fa-circle-info me-1"></i>
            Captain and Vice-Captain are chosen after players are assigned — a team can't captain someone who isn't on it yet.
          </div>

          <button type="submit" class="btn btn-auth-submit" style="width:auto; padding-left:28px; padding-right:28px;">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Team
          </button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/team.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
