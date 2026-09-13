<?php
/**
 * ============================================================
 * chat/groups.php
 * ------------------------------------------------------------
 * Lists every group this user belongs to. Players can see and
 * open their groups here but the "Create Group" button is
 * hidden for them — per the brief, players "cannot add
 * unauthorized users" and don't get group-creation rights at all.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$canCreate = in_array($myRole, ['admin', 'coach'], true);

$groups = [];
if ($conn) {
    $stmt = $conn->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM conversation_members cm2 WHERE cm2.conversation_id = c.conversation_id) AS member_count
         FROM conversations c
         JOIN conversation_members cm ON cm.conversation_id = c.conversation_id AND cm.user_role = ? AND cm.user_id = ?
         WHERE c.conversation_type = 'group' ORDER BY c.updated_at DESC"
    );
    $stmt->bind_param('si', $myRole, $myId);
    $stmt->execute();
    $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Groups';
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
    <div><h1><i class="fa-solid fa-people-group me-2"></i>Groups</h1><p class="text-muted">Team and committee group chats you belong to.</p></div>
    <div class="d-flex gap-2">
      <?php if ($canCreate): ?><a href="<?php echo BASE_URL; ?>/chat/create_group.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Create Group</a><?php endif; ?>
      <a href="<?php echo BASE_URL; ?>/chat/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Messages</a>
    </div>
  </div>

  <?php if (empty($groups)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No conversations yet.</p></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($groups as $g): ?>
        <div class="col-md-6 col-lg-4">
          <a href="<?php echo BASE_URL; ?>/chat/index.php?id=<?php echo $g['conversation_id']; ?>" class="panel h-100 d-block text-decoration-none">
            <div class="d-flex align-items-center gap-3 mb-2">
              <img src="<?php echo $g['image'] ? UPLOADS_URL . '/' . $g['image'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
              <div>
                <h3 style="font-size:1rem; margin:0;"><?php echo safeOut($g['title']); ?></h3>
                <span class="text-muted small"><?php echo (int) $g['member_count']; ?> members</span>
              </div>
            </div>
            <p class="text-muted small mb-0"><?php echo safeOut($g['description'] ?: 'No description.'); ?></p>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
