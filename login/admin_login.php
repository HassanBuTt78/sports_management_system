<?php
/**
 * ============================================================
 * admin_login.php
 * ------------------------------------------------------------
 * Administrator login. All real validation/security logic lives
 * in includes/auth.php (attemptLogin) — this file only collects
 * input, calls it, and renders the result.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$role = 'admin';
$cfg  = roleConfig($role);

// Already logged in as this role? Skip the form entirely.
if (isLoggedIn() && currentRole() === $role) {
    redirectTo($cfg['dashboard']);
}

// Silent "Remember Me" auto-login (only possible once the DB is connected).
if ($conn) {
    tryAutoLoginFromCookie($conn);
    if (isLoggedIn() && currentRole() === $role) {
        redirectTo($cfg['dashboard']);
    }
}

$errorMessage = null;
$emailValue   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$conn) {
        $errorMessage = $db_connection_error
            ?: 'Database not connected. Please import database/schema.sql and database/schema_module3.sql first.';
    } else {
        $emailValue = cleanInput($_POST['email'] ?? '');
        $password   = (string) ($_POST['password'] ?? '');
        $remember   = isset($_POST['remember_me']);

        $result = attemptLogin($conn, $role, $emailValue, $password, $remember);
        if ($result['success']) {
            redirectTo($result['redirect']);
        }
        $errorMessage = $result['message'];
    }
}

$pageTitle = 'Administrator Login';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<div class="auth-role-switch">
  <a href="admin_login.php" class="active"><i class="fa-solid fa-user-shield me-1"></i> Admin</a>
  <a href="coach_login.php"><i class="fa-solid fa-whistle me-1"></i> Coach</a>
  <a href="player_login.php"><i class="fa-solid fa-person-running me-1"></i> Player</a>
</div>

<span class="auth-role-tag"><i class="fa-solid fa-user-shield me-1"></i>Administrator</span>
<h2 class="auth-heading">Welcome Back</h2>
<p class="auth-subheading">Sign in to manage players, coaches, teams, and events.</p>

<?php if ($errorMessage): ?>
  <div class="alert alert-danger auth-alert" role="alert"><?php echo safeOut($errorMessage); ?></div>
<?php endif; ?>

<form method="POST" action="admin_login.php" class="needs-validation" novalidate>

  <div class="mb-3">
    <label for="email" class="form-label">Email Address</label>
    <input type="email" class="form-control" id="email" name="email"
           value="<?php echo safeOut($emailValue); ?>" required autofocus>
    <div class="invalid-feedback">Please enter a valid email address.</div>
  </div>

  <div class="mb-3">
    <label for="password" class="form-label">Password</label>
    <div class="password-field">
      <input type="password" class="form-control" id="password" name="password" required>
      <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
        <i class="fa-solid fa-eye"></i>
      </button>
    </div>
    <div class="invalid-feedback">Please enter your password.</div>
  </div>

  <div class="auth-options">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
      <label class="form-check-label" for="remember_me">Remember Me</label>
    </div>
    <a href="forgot_password.php?role=admin">Forgot Password?</a>
  </div>

  <button type="submit" class="btn btn-auth-submit">
    <i class="fa-solid fa-right-to-bracket me-2"></i>Login
  </button>
</form>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
