<?php
/**
 * ============================================================
 * notifications/index.php
 * ------------------------------------------------------------
 * Role-agnostic notification center — reuses the Module 2
 * `notifications` table as-is (no schema change). Admin already
 * has a topbar bell (Module 4); this full-page view is what
 * every role (including coach/player, who have no
 * topbar) uses to see, mark read, and delete notifications.
 * Clicking one links into its related page where possible.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];

$notifications = [];
if ($conn) {
    $stmt = $conn->prepare(
        "SELECT * FROM notifications
         WHERE (receiver_role = ? AND (receiver_id = ? OR receiver_id IS NULL)) OR receiver_role = 'all'
         ORDER BY created_at DESC LIMIT 100"
    );
    $stmt->bind_param('si', $myRole, $myId);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Notifications';
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/chat.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/role_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/role_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1><i class="fa-solid fa-bell me-2"></i>Notifications</h1></div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="markAllReadBtn"><i class="fa-solid fa-check-double me-1"></i>Mark All Read</button>
      <a href="<?php echo BASE_URL . '/' . $myRole; ?>/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <div class="panel">
    <?php if (empty($notifications)): ?>
      <div class="chat-empty-state"><i class="fa-solid fa-bell-slash"></i><p>No new notifications.</p></div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notif-row <?php echo $n['status'] === 'unread' ? 'notif-unread' : ''; ?>" data-id="<?php echo $n['notification_id']; ?>">
          <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>
          <div class="notif-body">
            <strong><?php echo safeOut($n['title']); ?></strong>
            <p><?php echo safeOut((string) $n['message']); ?></p>
            <span class="text-muted small"><?php echo timeAgo($n['created_at']); ?></span>
          </div>
          <div class="notif-actions">
            <?php if ($n['status'] === 'unread'): ?><button type="button" class="chat-icon-btn notif-read-btn" title="Mark as read"><i class="fa-solid fa-check"></i></button><?php endif; ?>
            <button type="button" class="chat-icon-btn notif-delete-btn" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<script>
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
  window.SMS_CSRF_TOKEN = <?php echo json_encode(csrfToken()); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/chat.js"></script>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
