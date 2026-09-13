<?php
/**
 * ============================================================
 * chat/create_group.php
 * ------------------------------------------------------------
 * Member choices are pre-filtered through getChatContacts() —
 * a coach physically cannot select a player outside their sport,
 * because that player never appears in the list to begin with.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$contacts = $conn ? getChatContacts($conn, $myRole, $myId) : [];

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }
    $groupName = cleanInput($_POST['group_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $memberKeys = $_POST['members'] ?? [];

    if ($groupName === '') $errors[] = 'Group name is required.';
    if (empty($memberKeys)) $errors[] = 'Select at least one member.';

    // Every selected member must actually be in this user's allowed contact list.
    $validMembers = [];
    foreach ($memberKeys as $key) {
        [$role, $id] = array_pad(explode(':', (string) $key, 2), 2, null);
        if (!$role || !$id) continue;
        foreach ($contacts as $c) {
            if ($c['role'] === $role && $c['id'] === (int) $id) { $validMembers[] = [$role, (int) $id]; break; }
        }
    }
    if (count($validMembers) !== count($memberKeys)) {
        $errors[] = 'One or more selected members are not people you are authorized to add.';
    }

    if (empty($errors)) {
        $descVal = $description !== '' ? $description : null;
        $stmt = $conn->prepare("INSERT INTO conversations (conversation_type, title, description, created_by_role, created_by_id) VALUES ('group', ?, ?, ?, ?)");
        $stmt->bind_param('sssi', $groupName, $descVal, $myRole, $myId);
        $stmt->execute();
        $conversationId = $stmt->insert_id;
        $stmt->close();

        addConversationMemberIfMissing($conn, $conversationId, $myRole, $myId, true);
        foreach ($validMembers as [$role, $id]) {
            addConversationMemberIfMissing($conn, $conversationId, $role, $id, false);
            notifyUser($conn, $role, $id, 'Added to Group', "You've been added to \"{$groupName}\".");
        }

        logActivity($conn, $myRole, $myId, "Created group: {$groupName}");
        $success = $conversationId;
    }
}

$pageTitle = 'Create Group';
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
    <div><h1>Create Group</h1></div>
    <a href="<?php echo BASE_URL; ?>/chat/groups.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">Group created. <a href="<?php echo BASE_URL; ?>/chat/index.php?id=<?php echo $success; ?>">Open it &rarr;</a></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="panel">
    <form method="POST">
      <?php echo csrfField(); ?>
      <div class="mb-3">
        <label class="form-label fw-semibold">Group Name *</label>
        <input type="text" name="group_name" class="form-control" placeholder="e.g. Football Team" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Members *</label>
        <?php if (empty($contacts)): ?>
          <p class="text-muted">No eligible contacts found.</p>
        <?php else: ?>
          <div class="chat-member-picker">
            <?php foreach ($contacts as $c): ?>
              <label class="chat-member-option">
                <input type="checkbox" name="members[]" value="<?php echo safeOut($c['role'] . ':' . $c['id']); ?>">
                <img src="<?php echo $c['image'] ? UPLOADS_URL . '/' . $c['image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="">
                <span><?php echo safeOut($c['name']); ?> <small class="text-muted">(<?php echo safeOut(ucfirst($c['role'])); ?>)</small></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-people-group me-1"></i>Create Group</button>
    </form>
  </div>
</div>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
