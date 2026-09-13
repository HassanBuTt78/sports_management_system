<?php
/**
 * ============================================================
 * forgot_password.php
 * ------------------------------------------------------------
 * Password recovery via security question — replaces the earlier
 * OTP/email-simulation flow entirely (no email delivery exists
 * in this project, so simulating one added complexity without
 * real security benefit). Three steps, all on this one page,
 * tracked via session state so nothing sensitive ever travels
 * in the URL:
 *   1. Enter role + email -> look up that account's question.
 *   2. Answer the question -> verified against the stored hash.
 *   3. Set a new password directly.
 * Same "don't confirm/deny an email exists" principle as login
 * is kept at every step.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$role = cleanInput($_GET['role'] ?? $_POST['role'] ?? 'admin');
if (!roleConfig($role)) {
    $role = 'admin';
}

$step = 1;
$errorMessage = null;
$securityQuestion = null;

// Resume an in-progress reset from session state.
if (!empty($_SESSION['pwreset_role']) && !empty($_SESSION['pwreset_id'])) {
    $role = $_SESSION['pwreset_role'];
    $step = !empty($_SESSION['pwreset_verified']) ? 3 : 2;
    $securityQuestion = $_SESSION['pwreset_question'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formStep = cleanInput($_POST['form_step'] ?? '1');

    // ---------- Step 1: identify the account ----------
    if ($formStep === '1') {
        $role  = cleanInput($_POST['role'] ?? 'admin');
        $email = cleanInput($_POST['email'] ?? '');
        $cfg   = roleConfig($role);

        if (!$cfg) {
            $errorMessage = 'Unknown account type.';
        } elseif ($email === '' || !isValidEmail($email)) {
            $errorMessage = 'Please enter a valid email address.';
        } elseif (!$conn) {
            $errorMessage = $db_connection_error
                ?: 'Database not connected. Please import the database schema files first.';
        } else {
            $stmt = $conn->prepare(
                "SELECT {$cfg['id_column']} AS id, full_name, status, security_question FROM {$cfg['table']} WHERE email = ? LIMIT 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user || $user['status'] !== 'active') {
                $errorMessage = 'If an account exists for that email, you can continue below.';
            } elseif (empty($user['security_question'])) {
                $errorMessage = 'No security question is set up for this account yet. Ask your Administrator to use "Reset Password" on your account — that generates a new password without needing a security question. You can then set up a security question from your Profile page for next time.';
            } else {
                $_SESSION['pwreset_role'] = $role;
                $_SESSION['pwreset_id'] = (int) $user['id'];
                $_SESSION['pwreset_question'] = $user['security_question'];
                $_SESSION['pwreset_verified'] = false;
                $step = 2;
                $securityQuestion = $user['security_question'];
            }
        }
    }

    // ---------- Step 2: verify the answer ----------
    elseif ($formStep === '2' && !empty($_SESSION['pwreset_role']) && !empty($_SESSION['pwreset_id'])) {
        $answer = (string) ($_POST['security_answer'] ?? '');
        $cfg = roleConfig($_SESSION['pwreset_role']);
        $stmt = $conn->prepare("SELECT security_answer_hash FROM {$cfg['table']} WHERE {$cfg['id_column']} = ? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['pwreset_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($answer !== '' && verifySecurityAnswer($answer, $row['security_answer_hash'] ?? null)) {
            $_SESSION['pwreset_verified'] = true;
            $step = 3;
        } else {
            $errorMessage = 'That answer is not correct. Please try again.';
            $step = 2;
            $securityQuestion = $_SESSION['pwreset_question'];
        }
    }

    // ---------- Step 3: set the new password ----------
    elseif ($formStep === '3' && !empty($_SESSION['pwreset_verified'])) {
        $newPassword = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $pwError = validatePasswordStrength($newPassword);

        if ($pwError) {
            $errorMessage = $pwError;
            $step = 3;
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'Passwords do not match.';
            $step = 3;
        } else {
            $cfg = roleConfig($_SESSION['pwreset_role']);
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (in_array($_SESSION['pwreset_role'], ['coach', 'player'], true)) {
                // Keep the admin-recoverable encrypted copy in sync so
                // Admin's "Show Password" always reflects the real
                // current password, even after a self-service reset.
                $encrypted = encryptCredential($newPassword);
                $stmt = $conn->prepare("UPDATE {$cfg['table']} SET password = ?, encrypted_password = ? WHERE {$cfg['id_column']} = ?");
                $stmt->bind_param('ssi', $hash, $encrypted, $_SESSION['pwreset_id']);
            } else {
                $stmt = $conn->prepare("UPDATE {$cfg['table']} SET password = ? WHERE {$cfg['id_column']} = ?");
                $stmt->bind_param('si', $hash, $_SESSION['pwreset_id']);
            }
            $stmt->execute();
            $stmt->close();

            $doneRole = $_SESSION['pwreset_role'];
            unset($_SESSION['pwreset_role'], $_SESSION['pwreset_id'], $_SESSION['pwreset_question'], $_SESSION['pwreset_verified']);

            redirectTo(BASE_URL . '/login/index.php?role=' . $doneRole . '&reset=success');
        }
    }
}

$pageTitle = 'Forgot Password';
$cfg = roleConfig($role);
require_once __DIR__ . '/../includes/auth_header.php';
?>

<span class="auth-role-tag"><i class="fa-solid fa-key me-1"></i>Password Recovery</span>
<h2 class="auth-heading">Forgot Password</h2>
<p class="auth-subheading">
  <?php if ($step === 1): ?>Enter your account email to get started.
  <?php elseif ($step === 2): ?>Answer your security question to verify it's you.
  <?php else: ?>Choose a new password.<?php endif; ?>
</p>

<div class="auth-steps">
  <span class="auth-step <?php echo $step > 1 ? 'is-done' : 'is-current'; ?>"><?php echo $step > 1 ? '<i class="fa-solid fa-check"></i>' : '1'; ?></span>
  <span class="auth-step-line <?php echo $step > 1 ? 'is-done' : ''; ?>"></span>
  <span class="auth-step <?php echo $step > 2 ? 'is-done' : ($step === 2 ? 'is-current' : ''); ?>"><?php echo $step > 2 ? '<i class="fa-solid fa-check"></i>' : '2'; ?></span>
  <span class="auth-step-line <?php echo $step > 2 ? 'is-done' : ''; ?>"></span>
  <span class="auth-step <?php echo $step === 3 ? 'is-current' : ''; ?>">3</span>
</div>

<?php if ($errorMessage): ?>
  <div class="alert alert-danger auth-alert" role="alert"><?php echo safeOut($errorMessage); ?></div>
<?php endif; ?>

<?php if ($step === 1): ?>
  <form method="POST" action="forgot_password.php">
    <input type="hidden" name="form_step" value="1">
    <div class="mb-3">
      <label class="form-label">Account Type</label>
      <select name="role" class="form-select">
        <?php foreach (['admin', 'coach', 'player'] as $r): ?>
          <option value="<?php echo $r; ?>" <?php echo $role === $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Email Address</label>
      <input type="email" name="email" class="form-control" required autofocus>
    </div>
    <button type="submit" class="btn btn-auth-submit"><i class="fa-solid fa-arrow-right me-2"></i>Continue</button>
  </form>

<?php elseif ($step === 2): ?>
  <form method="POST" action="forgot_password.php">
    <input type="hidden" name="form_step" value="2">
    <div class="mb-3">
      <label class="form-label">Security Question</label>
      <p class="fw-semibold mb-2"><?php echo safeOut($securityQuestion); ?></p>
      <input type="text" name="security_answer" class="form-control" placeholder="Your answer" required autofocus>
    </div>
    <button type="submit" class="btn btn-auth-submit"><i class="fa-solid fa-shield-halved me-2"></i>Verify Answer</button>
  </form>

<?php else: ?>
  <form method="POST" action="forgot_password.php">
    <input type="hidden" name="form_step" value="3">
    <div class="mb-3">
      <label class="form-label">New Password</label>
      <input type="password" name="password" class="form-control" minlength="8" required autofocus>
      <div class="form-text">At least 8 characters, with uppercase, lowercase, a digit, and a symbol.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Confirm New Password</label>
      <input type="password" name="confirm_password" class="form-control" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-auth-submit"><i class="fa-solid fa-lock me-2"></i>Reset Password</button>
  </form>
<?php endif; ?>

<div class="auth-options mt-3">
  <a href="<?php echo BASE_URL; ?>/login/index.php"><i class="fa-solid fa-arrow-left me-1"></i>Back to Login</a>
</div>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
