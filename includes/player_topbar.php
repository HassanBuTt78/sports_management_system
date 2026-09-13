<?php
/**
 * ============================================================
 * player_topbar.php
 * ------------------------------------------------------------
 * Top bar for player/*.php pages. Same markup/CSS as
 * admin_topbar.php, but the messages badge counts unread
 * conversation messages (Module 9/10 chat schema) instead of
 * the legacy 1-1 `messages` table, since that's what player
 * pages actually use. Notification read/delete actions POST to
 * notifications/mark_read.php (the role-agnostic endpoint), not
 * the admin-only /admin/notifications_action.php.
 * Expects $conn and an active player session (requireRole('player')
 * already called by the including page).
 * ============================================================
 */

$playerId = (int) $_SESSION['user_id'];

$notifications = [];
$unreadNotifCount = 0;
$unreadMsgCount = 0;

if ($conn) {
    $stmt = $conn->prepare(
        "SELECT notification_id, title, message, status, created_at
         FROM notifications
         WHERE receiver_role IN ('player','all') AND (receiver_id = ? OR receiver_id IS NULL)
         ORDER BY created_at DESC LIMIT 6"
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM notifications
         WHERE receiver_role IN ('player','all') AND (receiver_id = ? OR receiver_id IS NULL) AND status = 'unread'"
    );
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $unreadNotifCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM messages m
         JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_role = 'player' AND cm.user_id = ?
         WHERE m.is_deleted = 0 AND NOT (m.sender_role = 'player' AND m.sender_id = ?)
           AND m.sent_at > COALESCE(cm.last_read_at, '1970-01-01')"
    );
    $stmt->bind_param('ii', $playerId, $playerId);
    $stmt->execute();
    $unreadMsgCount = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}
?>
<header class="admin-topbar">

  <button class="topbar-icon-btn d-lg-none" id="sidebarToggle" aria-label="Open menu">
    <i class="fa-solid fa-bars"></i>
  </button>

  <div style="flex:1;"></div>

  <div class="topbar-actions">

    <button class="topbar-icon-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark/light mode">
      <i class="fa-solid fa-moon"></i>
    </button>

    <a href="<?php echo BASE_URL; ?>/chat/index.php" class="topbar-icon-btn position-relative" aria-label="Messages" title="Messages">
      <i class="fa-solid fa-envelope"></i>
      <?php if ($unreadMsgCount > 0): ?>
        <span class="topbar-badge"><?php echo $unreadMsgCount; ?></span>
      <?php endif; ?>
    </a>

    <div class="dropdown">
      <button class="topbar-icon-btn position-relative" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
        <i class="fa-solid fa-bell"></i>
        <?php if ($unreadNotifCount > 0): ?>
          <span class="topbar-badge"><?php echo $unreadNotifCount; ?></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end topbar-dropdown">
        <div class="topbar-dropdown-head">Notifications <span class="text-muted">(<?php echo $unreadNotifCount; ?> unread)</span></div>
        <?php if (empty($notifications)): ?>
          <div class="topbar-dropdown-empty">You're all caught up.</div>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <div class="topbar-dropdown-item notif-item <?php echo $n['status'] === 'unread' ? 'is-unread' : ''; ?>" data-notif-id="<?php echo (int) $n['notification_id']; ?>">
              <i class="fa-solid fa-circle-info"></i>
              <div>
                <strong><?php echo safeOut($n['title']); ?></strong>
                <p><?php echo safeOut(mb_strimwidth((string) $n['message'], 0, 60, '...')); ?></p>
                <span><?php echo timeAgo($n['created_at']); ?></span>
              </div>
              <div class="notif-actions">
                <?php if ($n['status'] === 'unread'): ?>
                  <button type="button" class="notif-action-btn" data-action="read" title="Mark as read"><i class="fa-solid fa-check"></i></button>
                <?php endif; ?>
                <button type="button" class="notif-action-btn" data-action="delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/notifications/index.php" class="topbar-dropdown-footer">View All Notifications</a>
      </div>
    </div>

    <div class="dropdown">
      <button class="topbar-profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
        <img src="<?php echo currentProfileImage(); ?>" alt="Profile">
        <span class="d-none d-md-inline"><?php echo safeOut($_SESSION['full_name']); ?></span>
        <i class="fa-solid fa-chevron-down d-none d-md-inline"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-end topbar-dropdown profile-dropdown">
        <a href="<?php echo BASE_URL; ?>/player/profile.php" class="dropdown-item-custom"><i class="fa-solid fa-user"></i> My Profile</a>
        <div class="topbar-dropdown-divider"></div>
        <a href="<?php echo BASE_URL; ?>/login/logout.php" class="dropdown-item-custom text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>

  </div>
</header>
<script>
/* Notification read/delete for the player topbar bell — posts to the
   role-agnostic notifications/mark_read.php endpoint (ownership is
   verified server-side against the logged-in player's session). */
(function () {
  var CSRF = <?php echo json_encode(csrfToken()); ?>;
  var BASE = <?php echo json_encode(BASE_URL); ?>;
  document.querySelectorAll('.topbar-dropdown .notif-action-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.notif-item');
      var id = item.getAttribute('data-notif-id');
      var action = btn.getAttribute('data-action');
      fetch(BASE + '/notifications/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ notification_id: id, action: action, csrf_token: CSRF })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) return;
        if (action === 'delete') {
          item.remove();
        } else {
          item.classList.remove('is-unread');
          var readBtn = item.querySelector('[data-action="read"]');
          if (readBtn) readBtn.remove();
        }
      });
    });
  });
})();
</script>
