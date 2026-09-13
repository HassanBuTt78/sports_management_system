<?php
/**
 * ============================================================
 * admin/profile.php
 * ------------------------------------------------------------
 * Full self-service profile: edit own details (name, phone,
 * email, photo) and change own password directly — mirrors the
 * coach/player self-service profile pattern. Role/status stay
 * fixed (not admin-editable from here, there's only one admin).
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin');

$adminId = (int) $_SESSION['user_id'];
$errors = [];
$success = null;
$passwordChanged = false;
$passwordErrors = [];

$stmt = $conn->prepare('SELECT * FROM admins WHERE admin_id = ? LIMIT 1');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$old = $admin;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $formType = cleanInput($_POST['form_type'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Please try again.';
    }

    // ---------- Edit My Details ----------
    elseif ($formType === 'details') {
        foreach (['full_name', 'phone', 'email'] as $key) {
            $old[$key] = cleanInput((string) ($_POST[$key] ?? ''));
        }

        if ($old['full_name'] === '') $errors[] = 'Full name is required.';
        if ($old['email'] === '' || !isValidEmail($old['email'])) $errors[] = 'A valid email address is required.';

        if ($old['email'] !== '' && !isFieldUnique($conn, 'admins', 'email', $old['email'], $adminId, 'admin_id')) {
            $errors[] = 'This email is already registered to another account.';
        }

        $imageCheck = validateUploadedImage($_FILES['profile_image'] ?? []);
        if (!empty($_FILES['profile_image']['name']) && !$imageCheck['valid']) {
            $errors[] = $imageCheck['error'];
        }

        if (empty($errors)) {
            $profileImagePath = $admin['profile_image'];
            if (!empty($_FILES['profile_image']['name'])) {
                $imageResult = storeUploadedImage($_FILES['profile_image'], 'admins');
                if ($imageResult['success'] && $imageResult['path']) {
                    deleteUploadedFile($admin['profile_image']);
                    $profileImagePath = $imageResult['path'];
                }
            }

            $phoneVal = $old['phone'] !== '' ? $old['phone'] : null;

            $stmt = $conn->prepare('UPDATE admins SET full_name=?, phone=?, email=?, profile_image=? WHERE admin_id = ?');
            $stmt->bind_param('ssssi', $old['full_name'], $phoneVal, $old['email'], $profileImagePath, $adminId);
            $stmt->execute();
            $stmt->close();

            // Session display name may be used elsewhere in the topbar.
            $_SESSION['full_name'] = $old['full_name'];

            logActivity($conn, 'admin', $adminId, 'Updated own profile');
            $success = 'Profile updated successfully.';
            $admin = array_merge($admin, $old, ['profile_image' => $profileImagePath]);
        }
    }

    // ---------- Change Password ----------
    elseif ($formType === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPassword, $admin['password'])) {
            $passwordErrors[] = 'Current password is incorrect.';
        }
        $pwError = validatePasswordStrength($newPassword);
        if ($pwError) $passwordErrors[] = $pwError;
        if ($newPassword !== $confirmPassword) $passwordErrors[] = 'New passwords do not match.';

        if (empty($passwordErrors)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE admins SET password = ? WHERE admin_id = ?');
            $stmt->bind_param('si', $hash, $adminId);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, 'admin', $adminId, 'Changed own password');
            $passwordChanged = true;
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
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>My Profile</h1><p>Your administrator account details.</p></div>
      </div>

      <?php if (!$admin): ?>
        <div class="alert alert-warning">Could not load profile — database not connected.</div>
      <?php else: ?>

        <?php if ($success): ?><div class="alert alert-success"><?php echo safeOut($success); ?></div><?php endif; ?>
        <?php if ($passwordChanged): ?><div class="alert alert-success">Password changed successfully.</div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if (!empty($passwordErrors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($passwordErrors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <div class="grid-2col">

          <div class="panel text-center">
            <img src="<?php echo currentProfileImage(); ?>" alt="Profile"
                 style="width:110px; height:110px; border-radius:50%; object-fit:cover; margin-bottom:14px;">
            <h3 style="margin-bottom:2px;"><?php echo safeOut($admin['full_name']); ?></h3>
            <p class="text-muted mb-2"><?php echo safeOut(ucfirst(str_replace('_', ' ', $admin['role']))); ?></p>
            <span class="status-pill status-<?php echo $admin['status']; ?>"><?php echo safeOut($admin['status']); ?></span>
          </div>

          <div class="panel">
            <div class="panel-head"><h2>Account Details</h2></div>
            <table class="table">
              <tbody>
                <tr><th style="width:180px;">Full Name</th><td><?php echo safeOut($admin['full_name']); ?></td></tr>
                <tr><th>Email</th><td><?php echo safeOut($admin['email']); ?></td></tr>
                <tr><th>Phone</th><td><?php echo safeOut($admin['phone'] ?? '—'); ?></td></tr>
                <tr><th>Role</th><td><?php echo safeOut(ucfirst(str_replace('_', ' ', $admin['role']))); ?></td></tr>
                <tr><th>Status</th><td><span class="status-pill status-<?php echo $admin['status']; ?>"><?php echo safeOut($admin['status']); ?></span></td></tr>
                <tr><th>Account Created</th><td><?php echo date('F j, Y \a\t g:i A', strtotime($admin['created_at'])); ?></td></tr>
                <tr><th>Last Updated</th><td><?php echo date('F j, Y \a\t g:i A', strtotime($admin['updated_at'])); ?></td></tr>
                <tr><th>Current Session Started</th><td><?php echo safeOut($_SESSION['login_time'] ?? '—'); ?></td></tr>
              </tbody>
            </table>
          </div>

          <div class="panel">
            <div class="panel-head"><h2><i class="fa-solid fa-pen me-2"></i>Edit Profile</h2></div>
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
              <input type="hidden" name="form_type" value="details">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo safeOut($old['full_name']); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo safeOut($old['email']); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo safeOut($old['phone'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Profile Photo</label>
                <input type="file" name="profile_image" class="form-control" accept=".jpg,.jpeg,.png">
              </div>
              <div class="col-12"><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
          </div>

          <div class="panel">
            <div class="panel-head"><h2><i class="fa-solid fa-key me-2"></i>Change Password</h2></div>
            <form method="POST" class="row g-3">
              <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
              <input type="hidden" name="form_type" value="password">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">New Password</label>
                <input type="password" name="new_password" class="form-control" minlength="8" required>
                <div class="form-text">At least 8 characters, with uppercase, lowercase, a digit, and a symbol.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" minlength="8" required>
              </div>
              <div class="col-12"><button type="submit" class="btn btn-primary">Change Password</button></div>
            </form>
          </div>

        </div>
      <?php endif; ?>
    </div>
  <?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
