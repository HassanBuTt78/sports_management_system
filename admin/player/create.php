<?php
/**
 * ============================================================
 * admin/player/create.php
 * ------------------------------------------------------------
 * Add New Player. Admin sets the password directly (hashed with
 * password_hash(), never stored in plain text, never shown on
 * screen) and a security question/answer for later recovery.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$success = null; // holds ['player_id'=>, 'email'=>, 'name'=>] after a successful save
$old = ['full_name' => '', 'roll_no' => '', 'email' => '', 'phone' => '', 'sport_id' => '', 'coach_id' => '',
        'team_id' => '', 'age' => '', 'gender' => '', 'blood_group' => '', 'height' => '', 'weight' => '',
        'address' => '', 'status' => 'active', 'security_question' => '', 'security_answer' => ''];

// ---------- Reference data for the form ----------
$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];
$securityQuestions = securityQuestionOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    foreach ($old as $key => $default) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? $default));
    }
    // ---------- Required-field validation ----------
    if ($old['full_name'] === '') $errors[] = 'Full name is required.';
    if ($old['roll_no'] === '')   $errors[] = 'Roll number is required.';
    if ($old['email'] === '' || !isValidEmail($old['email'])) $errors[] = 'A valid email address is required.';
    if ($old['phone'] === '')     $errors[] = 'Phone number is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if (!in_array($old['gender'], ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';
    if (!in_array($old['status'], ['active', 'inactive'], true)) $old['status'] = 'active';
    if (!in_array($old['security_question'], $securityQuestions, true)) $errors[] = 'Please select a security question.';
    if ($old['security_answer'] === '') $errors[] = 'Please provide a security answer.';

    // ---------- Uniqueness validation ----------
    if ($old['email'] !== '' && !isFieldUnique($conn, 'players', 'email', $old['email'], null, 'player_id')) {
        $errors[] = 'This email is already registered to another player.';
    }
    if ($old['roll_no'] !== '' && !isFieldUnique($conn, 'players', 'roll_no', $old['roll_no'], null, 'player_id')) {
        $errors[] = 'This roll number is already in use.';
    }
    if ($old['phone'] !== '' && !isFieldUnique($conn, 'players', 'phone', $old['phone'], null, 'player_id')) {
        $errors[] = 'This phone number is already registered to another player.';
    }

    // ---------- Image validation ----------
    $imageCheck = validateUploadedImage($_FILES['profile_image'] ?? []);
    if (!$imageCheck['valid']) {
        $errors[] = $imageCheck['error'];
    }

    // ---------- Save ----------
    if (empty($errors)) {
        // Admin never types a player's password — a new strong one is
        // generated here, shown once on the success panel below, and
        // stored as both a bcrypt hash (for login) and an admin-
        // recoverable encrypted copy (for the "Show Password" action).
        $plainPassword = generateSecurePassword();
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $encryptedPassword = encryptCredential($plainPassword);
        $hashedAnswer = hashSecurityAnswer($old['security_answer']);

        $imageResult = storeUploadedImage($_FILES['profile_image'], 'players');
        $profileImagePath = $imageResult['path'];

        $sportId = (int) $old['sport_id'];
        $coachId = ($old['coach_id'] !== '' && ctype_digit($old['coach_id'])) ? (int) $old['coach_id'] : null;
        $teamId  = ($old['team_id'] !== '' && ctype_digit($old['team_id']))  ? (int) $old['team_id']  : null;
        $age     = ($old['age'] !== '' && ctype_digit($old['age']))          ? (int) $old['age']      : null;
        $height  = $old['height'] !== '' ? (float) $old['height'] : null;
        $weight  = $old['weight'] !== '' ? (float) $old['weight'] : null;
        $bloodGroup = $old['blood_group'] !== '' ? $old['blood_group'] : null;
        $address    = $old['address'] !== '' ? $old['address'] : null;

        $stmt = $conn->prepare(
            'INSERT INTO players
             (coach_id, sport_id, team_id, full_name, roll_no, email, password, encrypted_password, phone, age, gender,
              address, profile_image, blood_group, height, weight, status, security_question, security_answer_hash)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'iiissssssissssddsss',
            $coachId, $sportId, $teamId, $old['full_name'], $old['roll_no'], $old['email'], $hashedPassword, $encryptedPassword,
            $old['phone'], $age, $old['gender'], $address, $profileImagePath, $bloodGroup, $height, $weight, $old['status'],
            $old['security_question'], $hashedAnswer
        );

        if ($stmt->execute()) {
            $newPlayerId = $stmt->insert_id;
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Added new player: {$old['full_name']}");

            $success = ['player_id' => $newPlayerId, 'email' => $old['email'], 'name' => $old['full_name'], 'password' => $plainPassword];
            $old = array_fill_keys(array_keys($old), ''); // clear form after success
            $old['status'] = 'active';
        } else {
            $stmt->close();
            $errors[] = 'Could not save the player. Please try again.';
        }
    }
}

$pageTitle = 'Add Player';
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
        <div><h1>Add New Player</h1><p>Create a player account and assign them to a sport, coach, and team.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/player/index.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Players
        </a>
      </div>

      <?php if ($success): ?>
        <div class="panel credential-panel" id="credentialPanel"
             data-player-id="<?php echo $success['player_id']; ?>"
             data-name="<?php echo safeOut($success['name']); ?>"
             data-email="<?php echo safeOut($success['email']); ?>"
             data-password="<?php echo safeOut($success['password']); ?>">
          <div class="panel-head"><h2><i class="fa-solid fa-circle-check text-success me-2"></i>Player Created Successfully</h2></div>
          <div class="row g-3">
            <div class="col-md-4"><div class="credential-box"><span>Player ID</span><strong>#<?php echo $success['player_id']; ?></strong></div></div>
            <div class="col-md-4"><div class="credential-box"><span>Email</span><strong><?php echo safeOut($success['email']); ?></strong></div></div>
            <div class="col-md-4">
              <div class="credential-box d-flex align-items-center gap-2">
                <div><span>Password</span><strong id="generatedPasswordText" style="font-family:monospace;"><?php echo safeOut($success['password']); ?></strong></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('generatedPasswordText').textContent)"><i class="fa-solid fa-copy"></i></button>
              </div>
            </div>
          </div>
          <div class="alert alert-warning mt-3 mb-0">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            This password is shown <strong>once</strong> — copy or print it now. It was auto-generated (never typed by Admin) and the player can log in with it immediately.
            It can also be viewed again later from this player's <em>Account Information</em> panel (via "Show Password").
          </div>
          <button type="button" class="btn btn-auth-submit mt-3" id="printCredentialsBtn">
            <i class="fa-solid fa-print me-2"></i>Print Account Details
          </button>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="panel">
        <form method="POST" action="create.php" enctype="multipart/form-data" class="needs-validation" novalidate id="playerForm">

          <div class="row g-4">

            <div class="col-lg-3 text-center">
              <label class="form-label fw-semibold d-block">Profile Image</label>
              <div class="image-upload-preview" id="imagePreviewWrap">
                <img id="imagePreview" src="<?php echo ASSETS_URL; ?>/images/default-avatar.svg" alt="Preview">
              </div>
              <input type="file" class="form-control mt-2" name="profile_image" id="profile_image" accept=".jpg,.jpeg,.png">
              <div class="form-text">JPG, JPEG, or PNG. Max 2MB.</div>
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
                  <select name="sport_id" id="sport_id" class="form-select" required>
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
                  <select name="coach_id" id="coach_id" class="form-select">
                    <option value="">Select Sport First</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Team</label>
                  <select name="team_id" id="team_id" class="form-select">
                    <option value="">Select Sport First</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label fw-semibold">Age</label>
                  <input type="number" name="age" class="form-control" min="5" max="100" value="<?php echo safeOut($old['age']); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Gender *</label>
                  <select name="gender" class="form-select" required>
                    <option value="">Select</option>
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
                  <input type="number" step="0.1" name="height" class="form-control" value="<?php echo safeOut($old['height']); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Weight (kg)</label>
                  <input type="number" step="0.1" name="weight" class="form-control" value="<?php echo safeOut($old['weight']); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Address</label>
                  <textarea name="address" class="form-control" rows="2"><?php echo safeOut($old['address']); ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-4">
          <h5 class="mb-3"><i class="fa-solid fa-lock me-2"></i>Login Credentials</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password</label>
              <input type="text" class="form-control" value="Auto-generated after saving — shown once on the confirmation panel" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Security Question *</label>
              <select name="security_question" class="form-select" required>
                <option value="">Select a question</option>
                <?php foreach ($securityQuestions as $q): ?>
                  <option value="<?php echo safeOut($q); ?>" <?php echo $old['security_question'] === $q ? 'selected' : ''; ?>><?php echo safeOut($q); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Used to recover the account if the password is forgotten.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Security Answer *</label>
              <input type="text" name="security_answer" class="form-control" value="<?php echo safeOut($old['security_answer']); ?>" required>
            </div>
          </div>

          <div class="alert alert-info mt-4 mb-3">
            <i class="fa-solid fa-circle-info me-1"></i>
            The password is generated automatically after saving. Share it with the player directly — it is only displayed once, right after creation.
          </div>

          <button type="submit" class="btn btn-auth-submit" style="width:auto; padding-left:28px; padding-right:28px;">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Player
          </button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/player.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
