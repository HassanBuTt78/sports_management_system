<?php
/**
 * ============================================================
 * admin/settings.php
 * ------------------------------------------------------------
 * Settings page shell. The UI is fully built per the brief, but
 * saving is intentionally a placeholder (SweetAlert2 "coming
 * soon") — a real settings backend (site name, timezone, email/
 * SMTP config, etc.) is out of scope for "Finish ONLY Module 4"
 * and would need its own settings table design.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin');

$pageTitle = 'Settings';
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
        <div><h1>Settings</h1><p>System preferences — wired up in a future module.</p></div>
      </div>

      <div class="grid-2col">

        <div class="panel">
          <div class="panel-head"><h2>General</h2></div>
          <form onsubmit="return false;">
            <div class="mb-3">
              <label class="form-label fw-semibold">College Name</label>
              <input type="text" class="form-control" value="<?php echo safeOut(COLLEGE_NAME); ?>" disabled>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">System Name</label>
              <input type="text" class="form-control" value="<?php echo safeOut(SITE_NAME); ?>" disabled>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Contact Email</label>
              <input type="email" class="form-control" value="<?php echo safeOut(SITE_EMAIL); ?>" disabled>
            </div>
            <button type="button" class="quick-action-btn" style="flex-direction:row; justify-content:center; width:100%;"
                    data-coming-soon="Saving general settings">
              <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
            </button>
          </form>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Security</h2></div>
          <ul class="activity-list">
            <li class="activity-item">
              <div class="activity-icon"><i class="fa-solid fa-lock"></i></div>
              <div><p>Failed login lockout</p><span>5 attempts &middot; 15 minute lock (Module 3)</span></div>
            </li>
            <li class="activity-item">
              <div class="activity-icon"><i class="fa-solid fa-key"></i></div>
              <div><p>Password hashing</p><span>bcrypt via PHP <code>password_hash()</code></span></div>
            </li>
            <li class="activity-item">
              <div class="activity-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
              <div><p>Remember Me duration</p><span>30 days, selector/validator cookie</span></div>
            </li>
          </ul>
          <button type="button" class="quick-action-btn mt-2" style="flex-direction:row; justify-content:center; width:100%;"
                  data-coming-soon="Editing security policy values">
            <i class="fa-solid fa-shield-halved me-2"></i>Manage Security Policy
          </button>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Notification Preferences</h2></div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" checked disabled>
            <label class="form-check-label">Email me on new event registrations</label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" checked disabled>
            <label class="form-check-label">Email me on new coach/player accounts</label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" disabled>
            <label class="form-check-label">Weekly performance summary digest</label>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Appearance</h2></div>
          <p class="text-muted" style="font-size:.88rem;">Dark/Light mode is already live — use the moon/sun icon in the top bar. Your preference is saved in this browser.</p>
          <div class="d-flex gap-2">
            <span class="status-pill status-active"><i class="fa-solid fa-sun me-1"></i>Light</span>
            <span class="status-pill" style="background:#232c46; color:#fff;"><i class="fa-solid fa-moon me-1"></i>Dark</span>
          </div>
        </div>

      </div>
    </div>
  <?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
