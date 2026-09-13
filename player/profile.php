<?php
/**
 * ============================================================
 * player/profile.php
 * ------------------------------------------------------------
 * Full self-service profile: edit own details directly (name,
 * phone, email, address, physical stats, photo), change own
 * password, update security question — no admin/coach approval
 * needed. Sport, coach, team, roll number, and account status
 * stay admin-controlled (those are administrative assignments,
 * not personal info), same as the admin edit page's own scope.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('player');

$playerId = (int) $_SESSION['user_id'];
$errors = [];
$success = null;
$passwordChanged = false;
$passwordErrors = [];
$securityQuestions = securityQuestionOptions();

$stmt = $conn->prepare(
    'SELECT p.*, s.sport_name, t.team_name, c.full_name AS coach_name FROM players p
     LEFT JOIN sports s ON p.sport_id = s.sport_id LEFT JOIN teams t ON p.team_id = t.team_id
     LEFT JOIN coaches c ON p.coach_id = c.coach_id WHERE p.player_id = ? LIMIT 1'
);
$stmt->bind_param('i', $playerId);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$player) {
    // Account record missing/deleted mid-session — don't render a broken
    // page full of undefined-index errors, send them back out cleanly.
    session_destroy();
    redirectTo(BASE_URL . '/login/index.php');
}

$old = $player;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $formType = cleanInput($_POST['form_type'] ?? '');

    // ---------- Edit My Details ----------
    if ($formType === 'details') {
        foreach (['full_name', 'phone', 'email', 'age', 'gender', 'blood_group', 'height', 'weight', 'address'] as $key) {
            $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
        }

        if ($old['full_name'] === '') $errors[] = 'Full name is required.';
        if ($old['phone'] === '') $errors[] = 'Phone number is required.';
        if ($old['email'] === '' || !isValidEmail($old['email'])) $errors[] = 'A valid email address is required.';
        if (!in_array($old['gender'], ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';

        if ($old['email'] !== '' && !isFieldUnique($conn, 'players', 'email', $old['email'], $playerId, 'player_id')) {
            $errors[] = 'This email is already registered to another player.';
        }
        if ($old['phone'] !== '' && !isFieldUnique($conn, 'players', 'phone', $old['phone'], $playerId, 'player_id')) {
            $errors[] = 'This phone number is already registered to another player.';
        }

        $imageCheck = validateUploadedImage($_FILES['profile_image'] ?? []);
        if (!$imageCheck['valid']) $errors[] = $imageCheck['error'];

        if (empty($errors)) {
            $profileImagePath = $player['profile_image'];
            if (!empty($_FILES['profile_image']['name'])) {
                $imageResult = storeUploadedImage($_FILES['profile_image'], 'players');
                if ($imageResult['success'] && $imageResult['path']) {
                    deleteUploadedFile($player['profile_image']);
                    $profileImagePath = $imageResult['path'];
                }
            }

            $age = ($old['age'] !== '' && ctype_digit($old['age'])) ? (int) $old['age'] : null;
            $height = $old['height'] !== '' ? (float) $old['height'] : null;
            $weight = $old['weight'] !== '' ? (float) $old['weight'] : null;
            $bloodGroup = $old['blood_group'] !== '' ? $old['blood_group'] : null;
            $address = $old['address'] !== '' ? $old['address'] : null;

            $stmt = $conn->prepare(
                'UPDATE players SET full_name=?, phone=?, email=?, age=?, gender=?, blood_group=?, height=?, weight=?, address=?, profile_image=? WHERE player_id = ?'
            );
            $stmt->bind_param(
                'sssissddssi',
                $old['full_name'], $old['phone'], $old['email'], $age, $old['gender'], $bloodGroup, $height, $weight, $address, $profileImagePath, $playerId
            );
            $stmt->execute();
            $stmt->close();

            logActivity($conn, 'player', $playerId, 'Updated own profile');
            $success = 'Profile updated successfully.';
            $player = array_merge($player, $old, ['profile_image' => $profileImagePath]);
            $old = $player;
        }
    }

    // ---------- Change Password ----------
    elseif ($formType === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPassword, $player['password'])) {
            $passwordErrors[] = 'Current password is incorrect.';
        }
        $pwError = validatePasswordStrength($newPassword);
        if ($pwError) $passwordErrors[] = $pwError;
        if ($newPassword !== $confirmPassword) $passwordErrors[] = 'New passwords do not match.';

        if (empty($passwordErrors)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $encrypted = encryptCredential($newPassword);
            $stmt = $conn->prepare('UPDATE players SET password = ?, encrypted_password = ? WHERE player_id = ?');
            $stmt->bind_param('ssi', $hash, $encrypted, $playerId);
            $stmt->execute();
            $stmt->close();
            $passwordChanged = true;
        }
    }

    // ---------- Security Question ----------
    elseif ($formType === 'security') {
        $question = cleanInput($_POST['security_question'] ?? '');
        $answer = (string) ($_POST['security_answer'] ?? '');

        if (!in_array($question, $securityQuestions, true)) $errors[] = 'Please select a security question.';
        if ($answer === '') $errors[] = 'Please provide a security answer.';

        if (empty($errors)) {
            $hashedAnswer = hashSecurityAnswer($answer);
            $stmt = $conn->prepare('UPDATE players SET security_question = ?, security_answer_hash = ? WHERE player_id = ?');
            $stmt->bind_param('ssi', $question, $hashedAnswer, $playerId);
            $stmt->execute();
            $stmt->close();
            $player['security_question'] = $question;
            $old['security_question'] = $question;
            $success = 'Security question updated successfully.';
        }
    }
}

$pageTitle = 'My Profile';
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
  <?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/player_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5" style="max-width:820px;">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-user me-2"></i>My Profile</h1></div>
    <a href="<?php echo BASE_URL; ?>/player/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?php echo safeOut($success); ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2>My Details</h2></div>
    <form method="POST" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="form_type" value="details">

      <div class="col-12 text-center mb-2">
        <img src="<?php echo currentProfileImage(); ?>" id="imagePreview" style="width:84px;height:84px;border-radius:50%;object-fit:cover;">
        <div class="mt-2"><input type="file" name="profile_image" id="profile_image" class="form-control form-control-sm mx-auto" style="max-width:300px;" accept=".jpg,.jpeg,.png"></div>
      </div>

      <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="full_name" class="form-control" value="<?php echo safeOut($old['full_name']); ?>" required></div>
      <div class="col-md-6"><label class="form-label fw-semibold">Roll No</label><input type="text" class="form-control" value="<?php echo safeOut($old['roll_no']); ?>" disabled></div>

      <div class="col-md-6"><label class="form-label fw-semibold">Email *</label><input type="email" name="email" class="form-control" value="<?php echo safeOut($old['email']); ?>" required></div>
      <div class="col-md-6"><label class="form-label fw-semibold">Phone Number *</label><input type="text" name="phone" class="form-control" value="<?php echo safeOut($old['phone']); ?>" required></div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">Sport</label>
        <input type="text" class="form-control" value="<?php echo safeOut($player['sport_name']); ?>" disabled>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Coach</label>
        <input type="text" class="form-control" value="<?php echo safeOut($player['coach_name'] ?? 'Unassigned'); ?>" disabled>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Team</label>
        <input type="text" class="form-control" value="<?php echo safeOut($player['team_name'] ?? 'No Team'); ?>" disabled>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Status</label>
        <input type="text" class="form-control" value="<?php echo safeOut(ucfirst($player['status'])); ?>" disabled>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">Age</label>
        <input type="number" name="age" min="1" class="form-control" value="<?php echo safeOut((string) $old['age']); ?>">
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
        <label class="form-label fw-semibold">Height (cm)</label>
        <input type="number" step="0.1" name="height" class="form-control" value="<?php echo safeOut((string) $old['height']); ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Weight (kg)</label>
        <input type="number" step="0.1" name="weight" class="form-control" value="<?php echo safeOut((string) $old['weight']); ?>">
      </div>
      <div class="col-md-6"></div>

      <div class="col-12">
        <label class="form-label fw-semibold">Address</label>
        <textarea name="address" class="form-control" rows="2"><?php echo safeOut((string) $old['address']); ?></textarea>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-auth-submit" style="width:auto; padding-left:28px; padding-right:28px;"><i class="fa-solid fa-floppy-disk me-2"></i>Save My Details</button>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Change Password</h2></div>
    <?php if ($passwordChanged): ?><div class="alert alert-success">Password updated successfully.</div><?php endif; ?>
    <?php if (!empty($passwordErrors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($passwordErrors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="POST" class="row g-3">
      <input type="hidden" name="form_type" value="password">
      <div class="col-md-4">
        <label class="form-label fw-semibold">Current Password</label>
        <div class="input-group"><input type="password" name="current_password" id="currentPasswordField" class="form-control" required><button type="button" class="btn btn-outline-secondary toggle-password-visibility" data-target="currentPasswordField"><i class="fa-solid fa-eye"></i></button></div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">New Password</label>
        <div class="input-group"><input type="password" name="new_password" id="newPasswordField" class="form-control" minlength="8" required><button type="button" class="btn btn-outline-secondary toggle-password-visibility" data-target="newPasswordField"><i class="fa-solid fa-eye"></i></button></div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Confirm New Password</label>
        <div class="input-group"><input type="password" name="confirm_password" id="confirmPasswordField" class="form-control" minlength="8" required><button type="button" class="btn btn-outline-secondary toggle-password-visibility" data-target="confirmPasswordField"><i class="fa-solid fa-eye"></i></button></div>
      </div>
      <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Update Password</button></div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Security Question</h2></div>
    <p class="text-muted small">Used to recover your account if you forget your password.</p>
    <?php if (!empty($player['security_question'])): ?>
      <p class="mb-3"><strong>Current question:</strong> <?php echo safeOut($player['security_question']); ?></p>
    <?php endif; ?>
    <form method="POST" class="row g-3">
      <input type="hidden" name="form_type" value="security">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Security Question</label>
        <select name="security_question" class="form-select" required>
          <option value="">Select a question</option>
          <?php foreach ($securityQuestions as $q): ?><option value="<?php echo safeOut($q); ?>"><?php echo safeOut($q); ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6"><label class="form-label fw-semibold">Answer</label><input type="text" name="security_answer" class="form-control" required></div>
      <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Save Security Question</button></div>
    </form>
  </div>
</div>
<script>
  document.getElementById('profile_image')?.addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) { document.getElementById('imagePreview').src = e.target.result; };
    reader.readAsDataURL(file);
  });
  document.querySelectorAll('.toggle-password-visibility').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var field = document.getElementById(btn.getAttribute('data-target'));
      var icon = btn.querySelector('i');
      if (field.type === 'password') { field.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
      else { field.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
    });
  });
</script>
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
