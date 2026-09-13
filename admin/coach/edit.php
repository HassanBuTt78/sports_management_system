<?php
/**
 * ============================================================
 * admin/coach/edit.php
 * ------------------------------------------------------------
 * Edit an existing coach. No password field — that's Reset
 * Password's job. Uniqueness checks exclude the coach's own row.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$coachId = (int) ($_GET['id'] ?? 0);
if ($coachId <= 0) {
    redirectTo(BASE_URL . '/admin/coach/index.php');
}

$errors = [];
$saved = false;
$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];

$coach = null;
if ($conn) {
    $stmt = $conn->prepare('SELECT * FROM coaches WHERE coach_id = ? LIMIT 1');
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $coach = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$coach) {
    redirectTo(BASE_URL . '/admin/coach/index.php');
}

// Pull just the numeric years back out of "6 years" for the number input.
$currentExperienceYears = $coach['experience'] ? (int) preg_replace('/\D/', '', $coach['experience']) : '';
$old = array_merge($coach, ['experience' => (string) $currentExperienceYears]);
$passwordChanged = false;
$passwordErrors = [];
$generatedPassword = null;

// ---------- Reset Password (separate mini-form, own submit) ----------
// Per policy: Admin never types a coach's password directly — a new
// strong one is generated server-side, shown once, and stored as both
// a bcrypt hash (for login) and an admin-recoverable encrypted copy.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && ($_POST['form_type'] ?? '') === 'password') {
    $generatedPassword = generateSecurePassword();
    $hash = password_hash($generatedPassword, PASSWORD_DEFAULT);
    $encrypted = encryptCredential($generatedPassword);

    $stmt = $conn->prepare('UPDATE coaches SET password = ?, encrypted_password = ? WHERE coach_id = ?');
    $stmt->bind_param('ssi', $hash, $encrypted, $coachId);
    $stmt->execute();
    $stmt->close();
    logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Reset password for coach: {$coach['full_name']} (#{$coachId})");
    $passwordChanged = true;
}

// ---------- Update Profile (main form) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && ($_POST['form_type'] ?? 'profile') === 'profile') {
    foreach (['full_name','email','phone','sport_id','qualification','experience','gender','address','status'] as $key) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
    }

    if ($old['full_name'] === '') $errors[] = 'Full name is required.';
    if ($old['email'] === '' || !isValidEmail($old['email'])) $errors[] = 'A valid email address is required.';
    if ($old['phone'] === '')     $errors[] = 'Phone number is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if (!in_array($old['gender'], ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';
    if (!in_array($old['status'], ['active', 'inactive'], true)) $old['status'] = 'active';

    if ($old['email'] !== '' && !isFieldUnique($conn, 'coaches', 'email', $old['email'], $coachId, 'coach_id')) {
        $errors[] = 'This email is already registered to another coach.';
    }
    if ($old['phone'] !== '' && !isFieldUnique($conn, 'coaches', 'phone', $old['phone'], $coachId, 'coach_id')) {
        $errors[] = 'This phone number is already registered to another coach.';
    }

    $imageCheck = validateUploadedImage($_FILES['profile_image'] ?? []);
    if (!$imageCheck['valid']) {
        $errors[] = $imageCheck['error'];
    }

    $experienceStr = $coach['experience'];
    if ($old['experience'] !== '') {
        if (!ctype_digit($old['experience']) || (int) $old['experience'] > 60) {
            $errors[] = 'Experience must be a whole number of years.';
        } else {
            $experienceStr = ((int) $old['experience']) . ' years';
        }
    }

    if (empty($errors)) {
        $profileImagePath = $coach['profile_image'];
        if (!empty($_FILES['profile_image']['name'])) {
            $imageResult = storeUploadedImage($_FILES['profile_image'], 'coaches');
            if ($imageResult['success'] && $imageResult['path']) {
                deleteUploadedFile($coach['profile_image']);
                $profileImagePath = $imageResult['path'];
            }
        }

        $sportId = (int) $old['sport_id'];
        $address = $old['address'] !== '' ? $old['address'] : null;

        $stmt = $conn->prepare(
            'UPDATE coaches SET sport_id=?, full_name=?, email=?, phone=?, experience=?, gender=?, address=?,
             qualification=?, profile_image=?, status=? WHERE coach_id = ?'
        );
        $stmt->bind_param(
            'isssssssssi',
            $sportId, $old['full_name'], $old['email'], $old['phone'], $experienceStr, $old['gender'],
            $address, $old['qualification'], $profileImagePath, $old['status'], $coachId
        );

        if ($stmt->execute()) {
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Updated coach: {$old['full_name']} (#{$coachId})");
            $saved = true;
            $coach = array_merge($coach, $old, ['profile_image' => $profileImagePath, 'experience' => $experienceStr]);
            $old = array_merge($coach, ['experience' => (string) ((int) preg_replace('/\D/', '', $experienceStr ?: '0'))]);
        } else {
            $stmt->close();
            $errors[] = 'Could not update the coach. Please try again.';
        }
    }
}

$pageTitle = 'Edit Coach';
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
        <div><h1>Edit Coach</h1><p>Update <?php echo safeOut($coach['full_name']); ?>'s information.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/coach/index.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Coaches
        </a>
      </div>

      <?php if ($saved): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Coach updated successfully.</div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="panel">
        <form method="POST" action="edit.php?id=<?php echo $coachId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
          <div class="row g-4">

            <div class="col-lg-3 text-center">
              <label class="form-label fw-semibold d-block">Profile Picture</label>
              <div class="image-upload-preview" id="imagePreviewWrap">
                <img id="imagePreview" src="<?php echo $coach['profile_image'] ? UPLOADS_URL . '/' . $coach['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="Preview">
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
                  <label class="form-label fw-semibold">Employee ID</label>
                  <input type="text" class="form-control" value="<?php echo safeOut(formatEmployeeId('COA', $coachId)); ?>" disabled>
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
                  <select name="sport_id" class="form-select" required>
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $s): ?>
                      <option value="<?php echo $s['sport_id']; ?>" <?php echo $old['sport_id'] == $s['sport_id'] ? 'selected' : ''; ?>>
                        <?php echo safeOut($s['sport_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Experience (Years)</label>
                  <input type="number" name="experience" min="0" max="60" class="form-control" value="<?php echo safeOut((string) $old['experience']); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Gender *</label>
                  <select name="gender" class="form-select" required>
                    <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label): ?>
                      <option value="<?php echo $val; ?>" <?php echo $old['gender'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-8">
                  <label class="form-label fw-semibold">Qualification</label>
                  <input type="text" name="qualification" class="form-control" value="<?php echo safeOut((string) $old['qualification']); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Status</label>
                  <select name="status" class="form-select">
                    <option value="active" <?php echo $old['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $old['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                  </select>
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
            <div class="mb-2"><i class="fa-solid fa-circle-check me-1"></i>Password reset. The coach can log in with this new password immediately.</div>
            <div class="credential-box d-inline-flex align-items-center gap-2">
              <span>New Password</span>
              <strong id="generatedPasswordText" style="font-family:monospace; font-size:1.05rem;"><?php echo safeOut($generatedPassword); ?></strong>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('generatedPasswordText').textContent)"><i class="fa-solid fa-copy"></i></button>
            </div>
            <div class="small text-muted mt-2">This is shown once — copy it now and share it with the coach directly.</div>
          </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?php echo $coachId; ?>"
              onsubmit="return confirm('Generate a new password for this coach? Their current password will stop working immediately.');">
          <input type="hidden" name="form_type" value="password">
          <button type="submit" class="btn btn-outline-secondary"><i class="fa-solid fa-key me-2"></i>Generate New Password</button>
        </form>
        <p class="text-muted small mt-3 mb-0">
          <i class="fa-solid fa-circle-info me-1"></i>
          Passwords are auto-generated, never chosen by Admin — this keeps every password unpredictable. The current password can also be viewed at any time from this coach's Account Information (view page).
        </p>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/coach.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
