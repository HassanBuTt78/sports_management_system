<?php
/**
 * ============================================================
 * admin/coach/view.php
 * ------------------------------------------------------------
 * Coach detail page — personal/contact info, sport, status, and
 * quick actions (Edit, Reset Password, Assign Players). For the
 * richer Feature 6 profile (assigned players, events created,
 * performance stats), see profile.php.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$coachId = (int) ($_GET['id'] ?? 0);
$coach = null;
$assignedPlayersCount = 0;

if ($conn && $coachId > 0) {
    $stmt = $conn->prepare(
        'SELECT c.*, s.sport_name FROM coaches c LEFT JOIN sports s ON c.sport_id = s.sport_id WHERE c.coach_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $coachId);
    $stmt->execute();
    $coach = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($coach) {
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM players WHERE coach_id = ?');
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $assignedPlayersCount = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
    }
}
if (!$coach) {
    redirectTo(BASE_URL . '/admin/coach/index.php');
}

$pageTitle = 'Coach Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo safeOut($coach['full_name']); ?> | <?php echo SITE_NAME; ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
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
        <div><h1>Coach Details</h1><p><?php echo safeOut(formatEmployeeId('COA', $coachId)); ?></p></div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/admin/coach/profile.php?id=<?php echo $coachId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-id-card me-1"></i>Full Profile</a>
          <a href="<?php echo BASE_URL; ?>/admin/coach/edit.php?id=<?php echo $coachId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i>Edit</a>
          <a href="<?php echo BASE_URL; ?>/admin/coach/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
      </div>

      <div class="grid-2col">
        <div class="panel text-center">
          <img src="<?php echo $coach['profile_image'] ? UPLOADS_URL . '/' . $coach['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>"
               alt="Profile" style="width:120px; height:120px; border-radius:50%; object-fit:cover; margin-bottom:14px;">
          <h3 class="mb-0"><?php echo safeOut($coach['full_name']); ?></h3>
          <p class="text-muted mb-2"><?php echo safeOut($coach['sport_name'] ?? '—'); ?> Coach</p>
          <span class="status-pill status-<?php echo $coach['status']; ?>"><?php echo safeOut($coach['status']); ?></span>

          <div class="d-grid gap-2 mt-4">
            <a href="<?php echo BASE_URL; ?>/admin/coach/assign_players.php?coach_id=<?php echo $coachId; ?>" class="btn btn-outline-secondary btn-sm">
              <i class="fa-solid fa-people-arrows me-1"></i>Assign Players (<?php echo $assignedPlayersCount; ?>)
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm reset-password-btn" data-coach-id="<?php echo $coachId; ?>">
              <i class="fa-solid fa-key me-1"></i>Reset Password
            </button>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Coach Information</h2></div>
          <table class="table table-borderless">
            <tbody>
              <tr><th style="width:180px;">Employee ID</th><td><?php echo safeOut(formatEmployeeId('COA', $coachId)); ?></td></tr>
              <tr><th>Full Name</th><td><?php echo safeOut($coach['full_name']); ?></td></tr>
              <tr><th>Email</th><td><?php echo safeOut($coach['email']); ?></td></tr>
              <tr><th>Phone</th><td><?php echo safeOut($coach['phone'] ?? '—'); ?></td></tr>
              <tr><th>Sport</th><td><?php echo safeOut($coach['sport_name'] ?? '—'); ?></td></tr>
              <tr><th>Qualification</th><td><?php echo safeOut($coach['qualification'] ?? '—'); ?></td></tr>
              <tr><th>Experience</th><td><?php echo safeOut($coach['experience'] ?? '—'); ?></td></tr>
              <tr><th>Gender</th><td><?php echo safeOut(ucfirst($coach['gender'] ?? '—')); ?></td></tr>
              <tr><th>Address</th><td><?php echo safeOut($coach['address'] ?? '—'); ?></td></tr>
              <tr><th>Joined</th><td><?php echo date('M j, Y', strtotime($coach['created_at'])); ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-lock me-2"></i>Account Information</h2></div>
        <table class="table table-borderless mb-0">
          <tbody>
            <tr><th style="width:180px;">User ID</th><td><?php echo safeOut(formatEmployeeId('COA', $coachId)); ?></td></tr>
            <tr>
              <th>Password</th>
              <td>
                <span id="passwordDisplay" style="font-family:monospace;">********</span>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-2 show-password-btn" data-coach-id="<?php echo $coachId; ?>">
                  <i class="fa-solid fa-eye me-1"></i>Show Password
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p class="text-muted small mt-2 mb-0">
          <i class="fa-solid fa-shield-halved me-1"></i>
          Only Admin can reveal this. It is decrypted server-side on request and is never stored anywhere in plain text.
        </p>
      </div>

    </div>
  <?php
  $extraFooterScripts = '<script src="' . BASE_URL . '/assets/js/coach.js"></script>';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
