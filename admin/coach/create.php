<?php
/**
 * ============================================================
 * admin/coach/create.php
 * ------------------------------------------------------------
 * Add New Coach. Employee ID (COA-0001 style) is a DISPLAY
 * FORMAT of the real coach_id — see formatEmployeeId() in
 * functions.php — no extra column needed. Admin sets the
 * password directly (no auto-generated password is shown on
 * screen) and a security question/answer for later recovery —
 * both stored only as hashes, never in plain text.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$success = null;
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'sport_id' => '', 'qualification' => '',
        'experience' => '', 'gender' => '', 'address' => '', 'status' => 'active',
        'security_question' => '', 'security_answer' => ''];

$sports = $conn ? $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC) : [];
$securityQuestions = securityQuestionOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    foreach ($old as $key => $default) {
        $old[$key] = cleanInput((string) ($_POST[$key] ?? $default));
    }

    if ($old['full_name'] === '') $errors[] = 'Full name is required.';
    if ($old['email'] === '' || !isValidEmail($old['email'])) $errors[] = 'A valid email address is required.';
    if ($old['phone'] === '')     $errors[] = 'Phone number is required.';
    if ($old['sport_id'] === '' || !ctype_digit($old['sport_id'])) $errors[] = 'Please select a sport.';
    if (!in_array($old['gender'], ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';
    if (!in_array($old['status'], ['active', 'inactive'], true)) $old['status'] = 'active';
    if (!in_array($old['security_question'], $securityQuestions, true)) $errors[] = 'Please select a security question.';
    if ($old['security_answer'] === '') $errors[] = 'Please provide a security answer.';

    if ($old['email'] !== '' && !isFieldUnique($conn, 'coaches', 'email', $old['email'], null, 'coach_id')) {
        $errors[] = 'This email is already registered to another coach.';
    }
    if ($old['phone'] !== '' && !isFieldUnique($conn, 'coaches', 'phone', $old['phone'], null, 'coach_id')) {
        $errors[] = 'This phone number is already registered to another coach.';
    }

    $imageCheck = validateUploadedImage($_FILES['profile_image'] ?? []);
    if (!$imageCheck['valid']) {
        $errors[] = $imageCheck['error'];
    }

    $experienceYears = null;
    if ($old['experience'] !== '') {
        if (!ctype_digit($old['experience']) || (int) $old['experience'] > 60) {
            $errors[] = 'Experience must be a whole number of years.';
        } else {
            $experienceYears = (int) $old['experience'];
        }
    }

    if (empty($errors)) {
        // Admin never types a coach's password — a new strong one is
        // generated here, shown once on the success panel below, and
        // stored as both a bcrypt hash (for login) and an admin-
        // recoverable encrypted copy (for the "Show Password" action).
        $plainPassword = generateSecurePassword();
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $encryptedPassword = encryptCredential($plainPassword);
        $hashedAnswer = hashSecurityAnswer($old['security_answer']);

        $imageResult = storeUploadedImage($_FILES['profile_image'], 'coaches');
        $profileImagePath = $imageResult['path'];

        $sportId = (int) $old['sport_id'];
        $experienceStr = $experienceYears !== null ? $experienceYears . ' years' : null;
        $address = $old['address'] !== '' ? $old['address'] : null;

        $stmt = $conn->prepare(
            'INSERT INTO coaches (sport_id, full_name, email, password, encrypted_password, phone, experience, gender, address, qualification, profile_image, status, security_question, security_answer_hash)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'isssssssssssss',
            $sportId, $old['full_name'], $old['email'], $hashedPassword, $encryptedPassword, $old['phone'],
            $experienceStr, $old['gender'], $address, $old['qualification'], $profileImagePath, $old['status'],
            $old['security_question'], $hashedAnswer
        );

        if ($stmt->execute()) {
            $newCoachId = $stmt->insert_id;
            $stmt->close();
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Added new coach: {$old['full_name']}");

            $success = [
                'coach_id' => $newCoachId,
                'employee_id' => formatEmployeeId('COA', $newCoachId),
                'email' => $old['email'],
                'name' => $old['full_name'],
                'password' => $plainPassword,
            ];
            $old = array_fill_keys(array_keys($old), '');
            $old['status'] = 'active';
        } else {
            $stmt->close();
            $errors[] = 'Could not save the coach. Please try again.';
        }
    }
}

$pageTitle = 'Add Coach';
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
        <div><h1>Add New Coach</h1><p>Create a coach account and assign them to a sport.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/coach/index.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i>Back to Coaches
        </a>
      </div>

      <?php if ($success): ?>
        <div class="panel credential-panel" id="credentialPanel"
             data-coach-id="<?php echo $success['coach_id']; ?>"
             data-employee-id="<?php echo safeOut($success['employee_id']); ?>"
             data-name="<?php echo safeOut($success['name']); ?>"
             data-email="<?php echo safeOut($success['email']); ?>"
             data-password="<?php echo safeOut($success['password']); ?>">
          <div class="panel-head"><h2><i class="fa-solid fa-circle-check text-success me-2"></i>Coach Created Successfully</h2></div>
          <div class="row g-3">
            <div class="col-md-4"><div class="credential-box"><span>Coach ID</span><strong><?php echo safeOut($success['employee_id']); ?></strong></div></div>
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
            This password is shown <strong>once</strong> — copy or print it now. It was auto-generated (never typed by Admin) and the coach can log in with it immediately.
            It can also be viewed again later from this coach's <em>Account Information</em> panel (via "Show Password").
          </div>
          <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-auth-submit" style="width:auto;" id="printCredentialsBtn">
              <i class="fa-solid fa-print me-2"></i>Print Credentials
            </button>
            <button type="button" class="btn btn-outline-secondary" id="downloadPdfBtn">
              <i class="fa-solid fa-file-pdf me-2"></i>Download PDF
            </button>
          </div>
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
              <label class="form-label fw-semibold d-block">Profile Picture</label>
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
                  <label class="form-label fw-semibold">Employee ID</label>
                  <input type="text" class="form-control" value="Auto-generated after saving (e.g. COA-0001)" disabled>
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
                  <input type="number" name="experience" min="0" max="60" class="form-control" value="<?php echo safeOut($old['experience']); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Gender *</label>
                  <select name="gender" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label): ?>
                      <option value="<?php echo $val; ?>" <?php echo $old['gender'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-8">
                  <label class="form-label fw-semibold">Qualification</label>
                  <input type="text" name="qualification" class="form-control" placeholder="e.g. BS Sports Science" value="<?php echo safeOut($old['qualification']); ?>">
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
            The Employee ID and password are both generated automatically after saving. Share them with the coach directly — the password is only displayed once, right after creation.
          </div>

          <button type="submit" class="btn btn-auth-submit" style="width:auto; padding-left:28px; padding-right:28px;">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Coach
          </button>
        </form>
      </div>

    </div>
  <?php
  $extraFooterScripts = '
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="' . BASE_URL . '/assets/js/coach.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
