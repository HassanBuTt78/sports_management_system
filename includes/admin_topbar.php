<?php
/**
 * ============================================================
 * admin_topbar.php
 * ------------------------------------------------------------
 * Top navigation bar. Queries live notification/message counts
 * from the existing Module 2 tables (read-only — nothing here
 * writes to the database). Expects $conn and an active admin
 * session (requireRole('admin') already called by the page that
 * includes this file).
 * ============================================================
 */

$adminId = (int) $_SESSION['user_id'];

// ---------- Unread notifications for this admin (or broadcast to 'all admins') ----------
$notifications = [];
$unreadNotifCount = 0;
if ($conn) {
    $stmt = $conn->prepare(
        "SELECT notification_id, title, message, status, created_at
         FROM notifications
         WHERE receiver_role IN ('admin','all') AND (receiver_id = ? OR receiver_id IS NULL)
         ORDER BY created_at DESC LIMIT 6"
    );
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM notifications
         WHERE receiver_role IN ('admin','all') AND (receiver_id = ? OR receiver_id IS NULL) AND status = 'unread'"
    );
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $unreadNotifCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
}

// ---------- Pending (unseen) messages addressed to this admin ----------
$pendingMessages = [];
$pendingMessageCount = 0;
if ($conn) {
    $stmt = $conn->prepare(
        "SELECT message_id, sender_role, sender_id, message, sent_at
         FROM messages
         WHERE receiver_role = 'admin' AND receiver_id = ? AND seen_status = 'sent'
         ORDER BY sent_at DESC LIMIT 5"
    );
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $pendingMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $pendingMessageCount = count($pendingMessages);
}
?>
<header class="admin-topbar">

  <button class="topbar-icon-btn d-lg-none" id="sidebarToggle" aria-label="Open menu">
    <i class="fa-solid fa-bars"></i>
  </button>

  <form class="topbar-search" action="<?php echo BASE_URL; ?>/admin/search.php" method="GET">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="search" name="q" placeholder="Search players, coaches, teams, events..."
           value="<?php echo safeOut($_GET['q'] ?? ''); ?>">
  </form>

  <div class="topbar-actions">

    <button class="topbar-icon-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark/light mode">
      <i class="fa-solid fa-moon"></i>
    </button>

    <div class="dropdown">
      <button class="topbar-icon-btn position-relative" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Messages">
        <i class="fa-solid fa-envelope"></i>
        <?php if ($pendingMessageCount > 0): ?>
          <span class="topbar-badge"><?php echo $pendingMessageCount; ?></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end topbar-dropdown">
        <div class="topbar-dropdown-head">Messages <span class="text-muted">(<?php echo $pendingMessageCount; ?> new)</span></div>
        <?php if (empty($pendingMessages)): ?>
          <div class="topbar-dropdown-empty">No new messages.</div>
        <?php else: ?>
          <?php foreach ($pendingMessages as $m): ?>
            <div class="topbar-dropdown-item">
              <i class="fa-solid fa-circle-user"></i>
              <div>
                <strong><?php echo safeOut(resolveUserName($conn, $m['sender_role'], (int) $m['sender_id'])); ?></strong>
                <p><?php echo safeOut(mb_strimwidth((string) $m['message'], 0, 60, '...')); ?></p>
                <span><?php echo timeAgo($m['sent_at']); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/admin/messages/index.php" class="topbar-dropdown-footer">View All Messages</a>
      </div>
    </div>

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
        <a href="<?php echo BASE_URL; ?>/admin/notifications/index.php" class="topbar-dropdown-footer">View All Notifications</a>
      </div>
    </div>

    <div class="dropdown">
      <button class="topbar-profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
        <img src="<?php echo currentProfileImage(); ?>" alt="Profile">
        <span class="d-none d-md-inline"><?php echo safeOut($_SESSION['full_name']); ?></span>
        <i class="fa-solid fa-chevron-down d-none d-md-inline"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-end topbar-dropdown profile-dropdown">
        <a href="<?php echo BASE_URL; ?>/admin/profile.php" class="dropdown-item-custom"><i class="fa-solid fa-user"></i> My Profile</a>
        <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="dropdown-item-custom"><i class="fa-solid fa-gear"></i> Settings</a>
        <div class="topbar-dropdown-divider"></div>
        <a href="<?php echo BASE_URL; ?>/login/logout.php" class="dropdown-item-custom text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>

  </div>
</header>
