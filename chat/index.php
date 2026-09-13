<?php
/**
 * ============================================================
 * chat/index.php
 * ------------------------------------------------------------
 * The chat home. Left pane: every conversation this user belongs
 * to (private/group/event/match, unified via `conversations` +
 * `conversation_members`). Right pane: the selected conversation
 * (?id=), or an empty state if none is selected yet.
 *
 * Sending/receiving within an open conversation uses AJAX
 * (send_message.php / load_messages.php polling) — no reload.
 * Switching between conversations is a normal navigation to keep
 * this module's scope honest and reliable rather than adding a
 * second page-fragment protocol on top of an already large module.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach', 'player');
$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];

if ($conn) touchPresence($conn, $myRole, $myId);

$activeConvId = (int) ($_GET['id'] ?? 0);

$conversations = [];
$activeConv = null;
$activeMessages = [];
$activeMembers = [];

if ($conn) {
    $stmt = $conn->prepare(
        "SELECT c.*, cm.last_read_at,
                (SELECT message FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0 ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
                (SELECT attachment_type FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0 ORDER BY m.sent_at DESC LIMIT 1) AS last_attachment_type,
                (SELECT sent_at FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0 ORDER BY m.sent_at DESC LIMIT 1) AS last_sent_at,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0
                    AND m.sent_at > COALESCE(cm.last_read_at, '1970-01-01') AND NOT (m.sender_role = ? AND m.sender_id = ?)) AS unread_count
         FROM conversations c
         JOIN conversation_members cm ON cm.conversation_id = c.conversation_id AND cm.user_role = ? AND cm.user_id = ?
         ORDER BY COALESCE(last_sent_at, c.created_at) DESC"
    );
    $stmt->bind_param('sisi', $myRole, $myId, $myRole, $myId);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Resolve a display name/avatar/role/online-status for each conversation.
    foreach ($conversations as &$c) {
        if ($c['conversation_type'] === 'private') {
            $stmt = $conn->prepare('SELECT user_role, user_id FROM conversation_members WHERE conversation_id = ? AND NOT (user_role = ? AND user_id = ?) LIMIT 1');
            $stmt->bind_param('isi', $c['conversation_id'], $myRole, $myId);
            $stmt->execute();
            $other = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($other) {
                $rt = roleTable($other['user_role']);
                $table = $rt['table'];
                $idCol = $rt['id_col'];
                $stmt = $conn->prepare("SELECT full_name, profile_image FROM {$table} WHERE {$idCol} = ? LIMIT 1");
                $stmt->bind_param('i', $other['user_id']);
                $stmt->execute();
                $person = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $c['display_name'] = $person['full_name'] ?? 'Unknown User';
                $c['display_image'] = $person['profile_image'] ?? null;
                $c['display_role'] = $other['user_role'];
                $c['other_id'] = (int) $other['user_id'];
                $c['is_online'] = isUserOnline($conn, $other['user_role'], (int) $other['user_id']);
            }
        } else {
            $c['display_name'] = $c['title'] ?: ucfirst($c['conversation_type']) . ' Chat';
            $c['display_image'] = $c['image'];
            $c['display_role'] = $c['conversation_type'];
            $c['is_online'] = false;
        }
    }
    unset($c);

    if ($activeConvId > 0 && isConversationMember($conn, $activeConvId, $myRole, $myId)) {
        $stmt = $conn->prepare('SELECT * FROM conversations WHERE conversation_id = ? LIMIT 1');
        $stmt->bind_param('i', $activeConvId);
        $stmt->execute();
        $activeConv = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT m.*, r.message AS reply_message FROM messages m
             LEFT JOIN messages r ON m.reply_to_message_id = r.message_id
             WHERE m.conversation_id = ? ORDER BY m.sent_at ASC"
        );
        $stmt->bind_param('i', $activeConvId);
        $stmt->execute();
        $activeMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM conversation_members WHERE conversation_id = ?');
        $stmt->bind_param('i', $activeConvId);
        $stmt->execute();
        $activeMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Mark this conversation read.
        $stmt = $conn->prepare('UPDATE conversation_members SET last_read_at = NOW() WHERE conversation_id = ? AND user_role = ? AND user_id = ?');
        $stmt->bind_param('isi', $activeConvId, $myRole, $myId);
        $stmt->execute();
        $stmt->close();

        // Resolve header display info for private chats.
        if ($activeConv['conversation_type'] === 'private') {
            $other = null;
            foreach ($activeMembers as $m) {
                if (!($m['user_role'] === $myRole && (int) $m['user_id'] === $myId)) { $other = $m; break; }
            }
            if ($other) {
                $rt = roleTable($other['user_role']);
                $table = $rt['table'];
                $idCol = $rt['id_col'];
                $stmt = $conn->prepare("SELECT full_name, profile_image FROM {$table} WHERE {$idCol} = ? LIMIT 1");
                $stmt->bind_param('i', $other['user_id']);
                $stmt->execute();
                $person = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $activeConv['display_name'] = $person['full_name'] ?? 'Unknown User';
                $activeConv['display_image'] = $person['profile_image'] ?? null;
                $activeConv['display_role'] = $other['user_role'];
                $activeConv['is_online'] = isUserOnline($conn, $other['user_role'], (int) $other['user_id']);
            }
        } else {
            $activeConv['display_name'] = $activeConv['title'] ?: ucfirst($activeConv['conversation_type']) . ' Chat';
            $activeConv['display_image'] = $activeConv['image'];
        }
    } else {
        $activeConvId = 0;
    }
}

$pageTitle = 'Messages';
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


<div class="chat-shell">

  <!-- ============ LEFT: CONVERSATION LIST ============ -->
  <aside class="chat-list-pane <?php echo $activeConvId ? 'chat-list-pane-hidden-mobile' : ''; ?>">
    <div class="chat-list-header">
      <h2>Messages</h2>
      <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL . '/' . $myRole; ?>/dashboard.php" class="chat-icon-btn" title="Back to Dashboard"><i class="fa-solid fa-house"></i></a>
        <button type="button" class="chat-icon-btn" id="newMessageBtn" title="New Message"><i class="fa-solid fa-square-pen"></i></button>
        <?php if (in_array($myRole, ['admin', 'coach'], true)): ?>
          <a href="<?php echo BASE_URL; ?>/chat/groups.php" class="chat-icon-btn" title="Groups"><i class="fa-solid fa-people-group"></i></a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/notifications/index.php" class="chat-icon-btn" title="Notifications"><i class="fa-solid fa-bell"></i></a>
      </div>
    </div>
    <div class="chat-search-box">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="chatSearchInput" placeholder="Search conversations, people, messages...">
    </div>
    <div class="chat-conv-list" id="chatConvList">
      <?php if (empty($conversations)): ?>
        <div class="chat-empty-state"><i class="fa-solid fa-comments"></i><p>No conversations yet.</p></div>
      <?php else: ?>
        <?php foreach ($conversations as $c): ?>
          <a href="<?php echo BASE_URL; ?>/chat/index.php?id=<?php echo $c['conversation_id']; ?>" class="chat-conv-item <?php echo (int) $c['conversation_id'] === $activeConvId ? 'active' : ''; ?>">
            <div class="chat-avatar-wrap">
              <img src="<?php echo $c['display_image'] ? UPLOADS_URL . '/' . $c['display_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="">
              <?php if ($c['conversation_type'] === 'private' && $c['is_online']): ?><span class="chat-online-dot"></span><?php endif; ?>
            </div>
            <div class="chat-conv-info">
              <div class="chat-conv-top">
                <span class="chat-conv-name"><?php echo safeOut($c['display_name']); ?></span>
                <span class="chat-conv-time"><?php echo $c['last_sent_at'] ? timeAgo($c['last_sent_at']) : ''; ?></span>
              </div>
              <div class="chat-conv-bottom">
                <span class="chat-conv-preview">
                  <?php if ($c['last_message']): ?><?php echo safeOut(mb_strimwidth($c['last_message'], 0, 40, '…')); ?>
                  <?php elseif ($c['last_attachment_type'] && $c['last_attachment_type'] !== 'none'): ?><i class="fa-solid fa-paperclip"></i> Attachment
                  <?php else: ?><span class="text-muted">No messages yet</span><?php endif; ?>
                </span>
                <?php if ($c['unread_count'] > 0): ?><span class="chat-unread-badge"><?php echo (int) $c['unread_count']; ?></span><?php endif; ?>
              </div>
              <span class="chat-conv-role"><?php echo safeOut(ucfirst($c['display_role'])); ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- ============ RIGHT: ACTIVE CONVERSATION ============ -->
  <section class="chat-window-pane <?php echo !$activeConvId ? 'chat-window-pane-hidden-mobile' : ''; ?>">
    <?php if (!$activeConv): ?>
      <div class="chat-empty-state chat-empty-state-full">
        <i class="fa-solid fa-comments"></i>
        <p>Select a conversation or start a new one.</p>
      </div>
    <?php else: ?>
      <div class="chat-window-header">
        <a href="<?php echo BASE_URL; ?>/chat/index.php" class="chat-icon-btn chat-back-btn"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="chat-avatar-wrap">
          <img src="<?php echo $activeConv['display_image'] ? UPLOADS_URL . '/' . $activeConv['display_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="">
          <?php if ($activeConv['conversation_type'] === 'private' && ($activeConv['is_online'] ?? false)): ?><span class="chat-online-dot"></span><?php endif; ?>
        </div>
        <div class="chat-window-title">
          <strong><?php echo safeOut($activeConv['display_name']); ?></strong>
          <span>
            <?php if ($activeConv['conversation_type'] === 'private'): ?>
              <?php echo safeOut(ucfirst($activeConv['display_role'])); ?> &middot; <?php echo ($activeConv['is_online'] ?? false) ? '<span class="text-success">Online</span>' : 'Offline'; ?>
            <?php else: ?>
              <?php echo count($activeMembers); ?> members
            <?php endif; ?>
          </span>
        </div>
        <?php if ($activeConv['conversation_type'] === 'group'): ?>
          <a href="<?php echo BASE_URL; ?>/chat/group.php?id=<?php echo $activeConvId; ?>" class="chat-icon-btn ms-auto" title="Group Settings"><i class="fa-solid fa-gear"></i></a>
        <?php endif; ?>
      </div>

      <div class="chat-messages" id="chatMessages" data-conversation-id="<?php echo $activeConvId; ?>">
        <?php if (empty($activeMessages)): ?>
          <div class="chat-empty-state"><i class="fa-solid fa-message"></i><p>Start the conversation.</p></div>
        <?php else: ?>
          <?php foreach ($activeMessages as $m):
            $isMine = $m['sender_role'] === $myRole && (int) $m['sender_id'] === $myId;
          ?>
            <div class="chat-bubble-row <?php echo $isMine ? 'mine' : ''; ?>" data-message-id="<?php echo $m['message_id']; ?>">
              <div class="chat-bubble">
                <?php if ($m['is_deleted']): ?>
                  <em class="text-muted">This message was deleted.</em>
                <?php else: ?>
                  <?php if ($m['reply_message']): ?><div class="chat-reply-preview"><?php echo safeOut(mb_strimwidth($m['reply_message'], 0, 60, '…')); ?></div><?php endif; ?>
                  <?php if ($m['attachment'] && $m['attachment_type'] === 'image'): ?>
                    <a href="<?php echo BASE_URL; ?>/chat/download_attachment.php?message_id=<?php echo $m['message_id']; ?>" target="_blank"><img src="<?php echo BASE_URL; ?>/chat/download_attachment.php?message_id=<?php echo $m['message_id']; ?>" class="chat-attachment-image" alt="attachment"></a>
                  <?php elseif ($m['attachment'] && $m['attachment_type'] === 'video'): ?>
                    <video controls class="chat-attachment-video"><source src="<?php echo BASE_URL; ?>/chat/download_attachment.php?message_id=<?php echo $m['message_id']; ?>"></video>
                  <?php elseif ($m['attachment']): ?>
                    <a href="<?php echo BASE_URL; ?>/chat/download_attachment.php?message_id=<?php echo $m['message_id']; ?>" class="chat-attachment-doc"><i class="fa-solid fa-file-lines"></i><?php echo safeOut(basename($m['attachment'])); ?></a>
                  <?php endif; ?>
                  <?php if ($m['message']): ?><div class="chat-bubble-text"><?php echo nl2br(safeOut($m['message'])); ?></div><?php endif; ?>
                <?php endif; ?>
                <div class="chat-bubble-meta">
                  <span><?php echo date('g:i A', strtotime($m['sent_at'])); ?></span>
                  <?php if ($m['is_edited'] && !$m['is_deleted']): ?><span>(edited)</span><?php endif; ?>
                  <?php if ($isMine && !$m['is_deleted']): ?>
                    <span class="chat-status-tick"><?php echo $m['seen_at'] ? '✓✓' : ($m['delivered_at'] ? '✓✓' : '✓'); ?></span>
                  <?php endif; ?>
                </div>
                <?php if ($isMine && !$m['is_deleted']): ?>
                  <div class="chat-bubble-actions">
                    <button type="button" class="chat-msg-action" data-action="reply" title="Reply"><i class="fa-solid fa-reply"></i></button>
                    <?php if ($m['message_type'] === 'text'): ?><button type="button" class="chat-msg-action" data-action="edit" title="Edit"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
                    <button type="button" class="chat-msg-action" data-action="delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    <button type="button" class="chat-msg-action" data-action="copy" title="Copy"><i class="fa-solid fa-copy"></i></button>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="chat-reply-bar d-none" id="chatReplyBar">
        <span id="chatReplyText"></span>
        <button type="button" id="chatReplyCancel"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <form class="chat-composer" id="chatComposerForm" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
        <input type="hidden" name="conversation_id" value="<?php echo $activeConvId; ?>">
        <input type="hidden" name="reply_to_message_id" id="chatReplyToId" value="">
        <button type="button" class="chat-icon-btn" id="chatAttachBtn" title="Attachment"><i class="fa-solid fa-paperclip"></i></button>
        <input type="file" id="chatAttachInput" name="attachment" class="d-none" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
        <button type="button" class="chat-icon-btn" id="chatEmojiBtn" title="Emoji"><i class="fa-regular fa-face-smile"></i></button>
        <input type="text" name="message" id="chatMessageInput" placeholder="Type a message..." autocomplete="off">
        <button type="submit" class="chat-send-btn" title="Send"><i class="fa-solid fa-paper-plane"></i></button>
      </form>
      <div id="chatAttachPreview" class="chat-attach-preview d-none"></div>
      <div id="chatEmojiPicker" class="chat-emoji-picker d-none"></div>
    <?php endif; ?>
  </section>

</div>

<div id="newMessageModal" class="chat-modal d-none">
  <div class="chat-modal-box">
    <div class="chat-modal-head"><h3>New Message</h3><button type="button" id="newMessageClose"><i class="fa-solid fa-xmark"></i></button></div>
    <input type="text" id="newMessageSearch" placeholder="Search people you can message...">
    <div id="newMessageResults" class="chat-contact-list"></div>
  </div>
</div>

<script>
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
  window.SMS_CSRF_TOKEN = <?php echo json_encode(csrfToken()); ?>;
  window.SMS_MY_ROLE = <?php echo json_encode($myRole); ?>;
  window.SMS_MY_ID = <?php echo (int) $myId; ?>;
  window.SMS_ACTIVE_CONVERSATION = <?php echo $activeConvId ?: 'null'; ?>;
  window.SMS_LAST_MESSAGE_ID = <?php echo !empty($activeMessages) ? end($activeMessages)['message_id'] : 0; ?>;
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
