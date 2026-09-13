<?php
/**
 * ============================================================
 * admin/sports/index.php
 * ------------------------------------------------------------
 * Sports Management. This system is locked to exactly 3 sports
 * (Cricket, Football, Hockey) per the project's final
 * requirements — so unlike other admin listings, there is
 * deliberately NO "Add Sport" or "Delete Sport" action here.
 * Admin can only rename a sport (e.g. fixing a typo), which
 * cascades automatically everywhere sport_name is displayed
 * since every other page reads it live from this one table.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$renamed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $sportId = (int) ($_POST['sport_id'] ?? 0);
    $newName = cleanInput($_POST['sport_name'] ?? '');

    if ($sportId <= 0 || $newName === '') {
        $errors[] = 'Please provide a valid sport name.';
    } elseif (!isFieldUnique($conn, 'sports', 'sport_name', $newName, $sportId, 'sport_id')) {
        $errors[] = 'Another sport already has that name.';
    } else {
        $stmt = $conn->prepare('UPDATE sports SET sport_name = ? WHERE sport_id = ?');
        $stmt->bind_param('si', $newName, $sportId);
        if ($stmt->execute()) {
            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Renamed sport #{$sportId} to \"{$newName}\"");
            $renamed = true;
        } else {
            $errors[] = 'Could not save the change. Please try again.';
        }
        $stmt->close();
    }
}

$sports = [];
if ($conn) {
    $sports = $conn->query(
        "SELECT s.*,
                (SELECT COUNT(*) FROM coaches  WHERE sport_id = s.sport_id) AS coach_count,
                (SELECT COUNT(*) FROM players  WHERE sport_id = s.sport_id) AS player_count,
                (SELECT COUNT(*) FROM teams    WHERE sport_id = s.sport_id) AS team_count,
                (SELECT COUNT(*) FROM matches  WHERE sport_id = s.sport_id) AS match_count,
                (SELECT COUNT(*) FROM events   WHERE sport_id = s.sport_id) AS event_count
         FROM sports s ORDER BY s.sport_id"
    )->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Sports Management';
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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>
    <div class="admin-content">

      <div class="page-heading">
        <div><h1>Sports Management</h1><p>This system supports exactly 3 sports — Cricket, Football, and Hockey.</p></div>
      </div>

      <div class="alert alert-info">
        <i class="fa-solid fa-circle-info me-1"></i>
        Per policy, sports can't be added or removed here — only renamed (e.g. to fix a typo). Every coach, player, team, event, and match is tied to one of these 3 sport records.
      </div>

      <?php if ($renamed): ?><div class="alert alert-success">Sport renamed successfully.</div><?php endif; ?>
      <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <div class="row g-3">
        <?php foreach ($sports as $s): ?>
          <div class="col-md-4">
            <div class="panel">
              <div class="panel-head"><h2><i class="fa-solid fa-futbol me-2"></i><?php echo safeOut($s['sport_name']); ?></h2></div>
              <ul class="list-unstyled small text-muted mb-3">
                <li><i class="fa-solid fa-whistle me-2"></i><?php echo $s['coach_count']; ?> coach(es)</li>
                <li><i class="fa-solid fa-person-running me-2"></i><?php echo $s['player_count']; ?> player(s)</li>
                <li><i class="fa-solid fa-people-group me-2"></i><?php echo $s['team_count']; ?> team(s)</li>
                <li><i class="fa-solid fa-trophy me-2"></i><?php echo $s['match_count']; ?> match(es)</li>
                <li><i class="fa-solid fa-calendar-days me-2"></i><?php echo $s['event_count']; ?> event(s)</li>
              </ul>
              <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="sport_id" value="<?php echo $s['sport_id']; ?>">
                <input type="text" name="sport_name" class="form-control form-control-sm" value="<?php echo safeOut($s['sport_name']); ?>" required maxlength="50">
                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="fa-solid fa-pen me-1"></i>Rename</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
