<?php
/**
 * ============================================================
 * chat/group.php
 * ------------------------------------------------------------
 * Group settings: rename, delete, member list. Only members
 * flagged is_admin=1 (set for the creator at group creation —
 * see create_group.php) can rename/delete/add/remove — players
 * are never given that flag, matching the brief's "Player:
 * participate, cannot add unauthorized users."
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$conversationId = (int) ($_GET['id'] ?? 0);

if (!$conn || !isConversationMember($conn, $conversationId, $myRole, $myId)) {
    redirectTo(BASE_URL . '/chat/groups.php');
}

$stmt = $conn->prepare("SELECT * FROM conversations WHERE conversation_id = ? AND conversation_type = 'group' LIMIT 1");
$stmt->bind_param('i', $conversationId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$group) {
    redirectTo(BASE_URL . '/chat/groups.php');
}

$stmt = $conn->prepare('SELECT is_admin FROM conversation_members WHERE conversation_id = ? AND user_role = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('isi', $conversationId, $myRole, $myId);
$stmt->execute();
$isGroupAdmin = (bool) ($stmt->get_result()->fetch_assoc()['is_admin'] ?? 0);
$stmt->close();

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isGroupAdmin) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired.';
    } else {
        $action = cleanInput($_POST['form_action'] ?? '');
        if ($action === 'rename') {
            $newName = cleanInput($_POST['group_name'] ?? '');
            $newDesc = cleanInput($_POST['description'] ?? '');
            if ($newName === '') {
                $errors[] = 'Group name is required.';
            } else {
                $descVal = $newDesc !== '' ? $newDesc : null;
                $stmt = $conn->prepare('UPDATE conversations SET title = ?, description = ? WHERE conversation_id = ?');
                $stmt->bind_param('ssi', $newName, $descVal, $conversationId);
                $stmt->execute();
                $stmt->close();
                $group['title'] = $newName;
                $group['description'] = $descVal;
                $saved = true;
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare('DELETE FROM conversations WHERE conversation_id = ?');
            $stmt->bind_param('i', $conversationId);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, $myRole, $myId, "Deleted group: {$group['title']}");
            redirectTo(BASE_URL . '/chat/groups.php');
        }
    }
}

$stmt = $conn->prepare('SELECT * FROM conversation_members WHERE conversation_id = ?');
$stmt->bind_param('i', $conversationId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($members as &$m) {
    $rt = roleTable($m['user_role']);
    $table = $rt['table'];
    $idCol = $rt['id_col'];
    $stmt = $conn->prepare("SELECT full_name, profile_image FROM {$table} WHERE {$idCol} = ? LIMIT 1");
    $stmt->bind_param('i', $m['user_id']);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $m['full_name'] = $p['full_name'] ?? 'Unknown';
    $m['profile_image'] = $p['profile_image'] ?? null;
}
unset($m);

$candidates = $isGroupAdmin ? getChatContacts($conn, $myRole, $myId) : [];
$memberKeys = array_map(fn($m) => $m['user_role'] . ':' . $m['user_id'], $members);
$candidates = array_values(array_filter($candidates, fn($c) => !in_array($c['role'] . ':' . $c['id'], $memberKeys, true)));

$pageTitle = 'Group Settings';
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
    <div><h1><?php echo safeOut($group['title']); ?></h1><p class="text-muted">Group Settings</p></div>
    <a href="<?php echo BASE_URL; ?>/chat/index.php?id=<?php echo $conversationId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Chat</a>
  </div>

  <?php if ($saved): ?><div class="alert alert-success">Group updated.</div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <?php if ($isGroupAdmin): ?>
    <div class="panel">
      <div class="panel-head"><h2>Rename Group</h2></div>
      <form method="POST" class="row g-3">
        <?php echo csrfField(); ?>
        <input type="hidden" name="form_action" value="rename">
        <div class="col-md-6"><input type="text" name="group_name" class="form-control" value="<?php echo safeOut($group['title']); ?>" required></div>
        <div class="col-md-6"><input type="text" name="description" class="form-control" placeholder="Description" value="<?php echo safeOut((string) $group['description']); ?>"></div>
        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
      </form>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2>Members (<?php echo count($members); ?>)</h2></div>
    <?php foreach ($members as $m): ?>
      <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <img src="<?php echo $m['profile_image'] ? UPLOADS_URL . '/' . $m['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
          <span><?php echo safeOut($m['full_name']); ?> <small class="text-muted">(<?php echo safeOut(ucfirst($m['user_role'])); ?>)</small></span>
          <?php if ($m['is_admin']): ?><span class="badge bg-primary">Admin</span><?php endif; ?>
        </div>
        <?php if ($isGroupAdmin && !($m['user_role'] === $myRole && (int) $m['user_id'] === $myId)): ?>
          <button type="button" class="btn btn-sm btn-outline-danger remove-member-btn" data-role="<?php echo $m['user_role']; ?>" data-id="<?php echo $m['user_id']; ?>" data-name="<?php echo safeOut($m['full_name']); ?>">Remove</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($isGroupAdmin && !empty($candidates)): ?>
    <div class="panel">
      <div class="panel-head"><h2>Add Members</h2></div>
      <form method="POST" id="addMembersForm" class="chat-member-picker mb-3">
        <?php foreach ($candidates as $c): ?>
          <label class="chat-member-option">
            <input type="checkbox" name="add_members[]" value="<?php echo safeOut($c['role'] . ':' . $c['id']); ?>">
            <img src="<?php echo $c['image'] ? UPLOADS_URL . '/' . $c['image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="">
            <span><?php echo safeOut($c['name']); ?> <small class="text-muted">(<?php echo safeOut(ucfirst($c['role'])); ?>)</small></span>
          </label>
        <?php endforeach; ?>
      </form>
      <button type="button" class="btn btn-sm btn-primary" id="addMembersBtn">Add Selected</button>
    </div>
  <?php endif; ?>

  <?php if ($isGroupAdmin): ?>
    <div class="panel">
      <div class="panel-head"><h2 class="text-danger">Danger Zone</h2></div>
      <form method="POST" id="deleteGroupForm">
        <?php echo csrfField(); ?>
        <input type="hidden" name="form_action" value="delete">
        <button type="button" class="btn btn-outline-danger btn-sm" id="deleteGroupBtn">Delete Group</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<script>
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
  window.SMS_CSRF_TOKEN = <?php echo json_encode(csrfToken()); ?>;
  window.SMS_CONVERSATION_ID = <?php echo $conversationId; ?>;
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
