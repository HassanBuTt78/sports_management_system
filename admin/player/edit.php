<?php
/**
 * ============================================================
 * admin/player/edit.php
 * ------------------------------------------------------------
 * Edit an existing player. No password field here — that's what
 * "Reset Password" (reset_password.php) is for. Uniqueness
 * checks exclude the player's own row so saving without changing
 * email/roll_no/phone doesn't falsely flag a conflict.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$playerId = (int) ($_GET['id'] ?? 0);
if ($playerId <= 0) {
    redirectTo(BASE_URL . '/admin/player/index.php');
}

$errors = [];
$saved = false;
$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];

// ---------- Load the player ----------
$player = null;
if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM players WHERE player_id = ? LIMIT 1');
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$player) {
    redirectTo(BASE_URL . '/admin/player/index.php');
}

$old = $player; // pre-fill the form with current values
$passwordChanged = false;
$passwordErrors = [];
$generatedPassword = null;

// ---------- Reset Password (separate mini-form, own submit) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && ($_POST['form_type'] ?? '') === 'password') {
    $generatedPassword = generateSecurePassword();
    $hash = password_hash($generatedPassword, PASSWORD_DEFAULT);
    $encrypted = encryptCredential($generatedPassword);

    $stmt = $conn->prepare('UPDATE players SET password = ?, encrypted_password = ? WHERE player_id = ?');
    $stmt->bind_param('ssi', $hash, $encrypted, $playerId);
    $stmt->execute();
    $stmt->close();
    logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Reset password for player: {$player['full_name']} (#{$playerId})");
    $passwordChanged = true;
}

// ---------- Update Profile (main form) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && ($_POST['form_type'] ?? 'profile') === 'profile') {
    foreach (['full_name','roll_no','email','phone','sport_id','coach_id','team_id','age','gender','blood_group','height','weight','address','status'] as $key) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
    }

    if ($old['full_name'] === '') $errors[] = 'Full name is required.';
    if ($old['roll_no'] === '')   $errors[] = 'Roll number is required.';
    if ($old['email'] === '' || !isValidEmail($old['email'])) $errors[] = 'A valid email address is required.';
    if ($old['phone'] === '')     $errors[] = 'Phone number is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if (!in_array($old['gender'], ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';
    if (!in_array($old['status'], ['active', 'inactive'], true)) $old['status'] = 'active';

    if ($old['email'] !== '' && !isFieldUnique($conn, 'players', 'email', $old['email'], $playerId, 'player_id')) {
        $errors[] = 'This email is already registered to another player.';
    }
    if ($old['roll_no'] !== '' && !isFieldUnique($conn, 'players', 'roll_no', $old['roll_no'], $playerId, 'player_id')) {
        $errors[] = 'This roll number is already in use.';
    }
    if ($old['phone'] !== '' && !isFieldUnique($conn, 'players', 'phone', $old['phone'], $playerId, 'player_id')) {
        $errors[] = 'This phone number is already registered to another player.';
    }

    $imageCheck = validateUploadedImage($_FILES['profile_image'] ?? []);
    if (!$imageCheck['valid']) {
        $errors[] = $imageCheck['error'];
    }

    if (empty($errors)) {
        $profileImagePath = $player['profile_image']; // keep existing unless replaced

        if (!empty($_FILES['profile_image']['name'])) {
            $imageResult = storeUploadedImage($_FILES['profile_image'], 'players');
            if ($imageResult['success'] && $imageResult['path']) {
                deleteUploadedFile($player['profile_image']); // remove the old file
                $profileImagePath = $imageResult['path'];
            }
        }

        $sportId = (int) $old['sport_id'];
        $coachId = ($old['coach_id'] !== '' && ctype_digit($old['coach_id'])) ? (int) $old['coach_id'] : null;
        $teamId  = ($old['team_id'] !== '' && ctype_digit($old['team_id']))  ? (int) $old['team_id']  : null;
        $age     = ($old['age'] !== '' && ctype_digit($old['age']))          ? (int) $old['age']      : null;
        $height  = $old['height'] !== '' ? (float) $old['height'] : null;
        $weight  = $old['weight'] !== '' ? (float) $old['weight'] : null;
        $bloodGroup = $old['blood_group'] !== '' ? $old['blood_group'] : null;
        $address    = $old['address'] !== '' ? $old['address'] : null;

        $stmt = $conn->prepare(
            'UPDATE players SET coach_id=?, sport_id=?, team_id=?, full_name=?, roll_no=?, email=?, phone=?,
             age=?, gender=?, address=?, profile_image=?, blood_group=?, height=?, weight=?, status=?
             WHERE player_id = ?'
        );
        $stmt->bind_param(
            'iiissssissssddsi',
            $coachId, $sportId, $teamId, $old['full_name'], $old['roll_no'], $old['email'], $old['phone'],
            $age, $old['gender'], $address, $profileImagePath, $bloodGroup, $height, $weight, $old['status'], $playerId
        );

        if ($stmt->execute()) {
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Updated player: {$old['full_name']} (#{$playerId})");
            $saved = true;
            $player = array_merge($player, $old, ['profile_image' => $profileImagePath]);
            $old = $player;
        } else {
            $stmt->close();
            $errors[] = 'Could not update the player. Please try again.';
        }
    }
}

$pageTitle = 'Edit Player';
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
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Edit Player</h1><p>Update <?php echo safeOut($player['full_name']); ?>'s information.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/player/index.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Players
        </a>
      </div>

      <?php if ($saved): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Player updated successfully.</div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="panel">
        <form method="POST" action="edit.php?id=<?php echo $playerId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate>

          <div class="row g-4">
            <div class="col-lg-3 text-center">
              <label class="form-label fw-semibold d-block">Profile Image</label>
              <div class="image-upload-preview" id="imagePreviewWrap">
                <img id="imagePreview" src="<?php echo $player['profile_image'] ? UPLOADS_URL . '/' . $player['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="Preview">
              </div>
              <input type="file" class="form-control mt-2" name="profile_image" id="profile_image" accept=".jpg,.jpeg,.png">
              <div class="form-text">Leave blank to keep the current photo. Max 2MB.</div>
            </div>

            <div class="col-lg-9">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Full Name *</label>
                  <input type="text" name="full_name" class="form-control" value="<?php echo safeOut($old['full_name']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Roll Number *</label>
                  <input type="text" name="roll_no" class="form-control" value="<?php echo safeOut($old['roll_no']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Email *</label>
                  <input type="email" name="email" class="form-control" value="<?php echo safeOut($old['email']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Phone Number *</label>
                  <input type="text" name="phone" class="form-control" value="<?php echo safeOut($old['phone']); ?>" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Sport *</label>
                  <select name="sport_id" id="sport_id" class="form-select" required data-current="<?php echo $old['sport_id']; ?>">
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $s): ?>
                      <option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>>
                        <?php echo safeOut($s['sport_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Coach</label>
                  <select name="coach_id" id="coach_id" class="form-select" data-current="<?php echo $old['coach_id']; ?>">
                    <option value="">Loading...</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Team</label>
                  <select name="team_id" id="team_id" class="form-select" data-current="<?php echo $old['team_id']; ?>">
                    <option value="">Loading...</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label fw-semibold">Age</label>
                  <input type="number" name="age" class="form-control" min="5" max="100" value="<?php echo safeOut((string) $old['age']); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Gender *</label>
                  <select name="gender" class="form-select" required>
                    <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label): ?>
                      <option value="<?php echo $val; ?>" <?php echo $old['gender'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Blood Group</label>
                  <select name="blood_group" class="form-select">
                    <option value="">Select</option>
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                      <option value="<?php echo $bg; ?>" <?php echo $old['blood_group'] === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Status</label>
                  <select name="status" class="form-select">
                    <option value="active" <?php echo $old['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $old['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Height (cm)</label>
                  <input type="number" step="0.1" name="height" class="form-control" value="<?php echo safeOut((string) $old['height']); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Weight (kg)</label>
                  <input type="number" step="0.1" name="weight" class="form-control" value="<?php echo safeOut((string) $old['weight']); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Address</label>
                  <textarea name="address" class="form-control" rows="2"><?php echo safeOut((string) $old['address']); ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-auth-submit mt-4" style="width:auto; padding-left:28px; padding-right:28px;">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
          </button>
        </form>
      </div>

      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-lock me-2"></i>Reset Password</h2></div>

        <?php if ($passwordChanged && $generatedPassword): ?>
          <div class="alert alert-success">
            <div class="mb-2"><i class="fa-solid fa-circle-check me-1"></i>Password reset. The player can log in with this new password immediately.</div>
            <div class="credential-box d-inline-flex align-items-center gap-2">
              <span>New Password</span>
              <strong id="generatedPasswordText" style="font-family:monospace; font-size:1.05rem;"><?php echo safeOut($generatedPassword); ?></strong>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('generatedPasswordText').textContent)"><i class="fa-solid fa-copy"></i></button>
            </div>
            <div class="small text-muted mt-2">This is shown once — copy it now and share it with the player directly.</div>
          </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?php echo $playerId; ?>"
              onsubmit="return confirm('Generate a new password for this player? Their current password will stop working immediately.');">
          <input type="hidden" name="form_type" value="password">
          <button type="submit" class="btn btn-outline-secondary"><i class="fa-solid fa-key me-2"></i>Generate New Password</button>
        </form>
        <p class="text-muted small mt-3 mb-0">
          <i class="fa-solid fa-circle-info me-1"></i>
          Passwords are auto-generated, never chosen by Admin — this keeps every password unpredictable. The current password can also be viewed at any time from this player's Account Information (view page).
        </p>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/player.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
