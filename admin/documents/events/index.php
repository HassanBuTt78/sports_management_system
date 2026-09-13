<?php
/**
 * ============================================================
 * admin/events/index.php
 * ------------------------------------------------------------
 * Event Management listing. Admin sees every event, including
 * coach-created ones still awaiting approval (highlighted).
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$filterSport  = (int) ($_GET['sport_id'] ?? 0);
$filterCoach  = (int) ($_GET['coach_id'] ?? 0);
$filterStatus = cleanInput($_GET['status'] ?? '');
$filterVenue  = cleanInput($_GET['venue'] ?? '');
$filterDate   = cleanInput($_GET['date'] ?? '');

$sports = [];
$coaches = [];
$events = [];
$stats = ['total' => 0, 'upcoming' => 0, 'running' => 0, 'completed' => 0, 'cancelled' => 0, 'participants' => 0];

if ($conn) {
    $sports  = $conn->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetch_all(MYSQLI_ASSOC);
    $coaches = $conn->query('SELECT coach_id, full_name FROM coaches ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);

    $stats['total']       = (int) $conn->query('SELECT COUNT(*) c FROM events')->fetch_assoc()['c'];
    $stats['upcoming']    = (int) $conn->query("SELECT COUNT(*) c FROM events WHERE status IN ('Upcoming','Registration Open','Registration Closed')")->fetch_assoc()['c'];
    $stats['running']     = (int) $conn->query("SELECT COUNT(*) c FROM events WHERE status='Running'")->fetch_assoc()['c'];
    $stats['completed']   = (int) $conn->query("SELECT COUNT(*) c FROM events WHERE status='Completed'")->fetch_assoc()['c'];
    $stats['cancelled']   = (int) $conn->query("SELECT COUNT(*) c FROM events WHERE status='Cancelled'")->fetch_assoc()['c'];
    $stats['participants'] = (int) $conn->query("SELECT COUNT(*) c FROM event_participants WHERE participation_status != 'Rejected'")->fetch_assoc()['c'];

    $where = [];
    $types = '';
    $params = [];
    if ($filterSport > 0)  { $where[] = 'e.sport_id = ?'; $types .= 'i'; $params[] = $filterSport; }
    if ($filterCoach > 0)  { $where[] = 'e.coach_id = ?'; $types .= 'i'; $params[] = $filterCoach; }
    if (in_array($filterStatus, ['Upcoming','Registration Open','Registration Closed','Running','Completed','Cancelled'], true)) {
        $where[] = 'e.status = ?'; $types .= 's'; $params[] = $filterStatus;
    }
    if ($filterVenue !== '') { $where[] = 'e.venue LIKE ?'; $types .= 's'; $params[] = '%' . $filterVenue . '%'; }
    if ($filterDate !== '')  { $where[] = 'e.event_date = ?'; $types .= 's'; $params[] = $filterDate; }

    $sql = "SELECT e.*, s.sport_name, c.full_name AS coach_name,
                   (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.event_id AND ep.participation_status != 'Rejected') AS participant_count
            FROM events e
            LEFT JOIN sports s ON e.sport_id = s.sport_id
            LEFT JOIN coaches c ON e.coach_id = c.coach_id";
    if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY e.event_date DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pendingApprovalCount = 0;
foreach ($events as $e) {
    if ($e['created_by_role'] === 'coach' && !$e['is_approved']) $pendingApprovalCount++;
}

$pageTitle = 'Event Management';
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
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/events.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>

<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Event Management</h1><p>Create, publish, and manage events across all four sports.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/events/create.php" class="btn btn-auth-submit" style="width:auto;"><i class="fa-solid fa-calendar-plus me-2"></i>Create Event</a>
      </div>

      <?php if (!$conn): ?>
        <div class="alert alert-warning">Database not connected — import the schema files first (including <code>schema_module8.sql</code>).</div>
      <?php endif; ?>

      <?php if ($pendingApprovalCount > 0): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-triangle-exclamation me-1"></i><strong><?php echo $pendingApprovalCount; ?></strong> coach-created event(s) awaiting your approval.</span>
        </div>
      <?php endif; ?>

      <!-- ============ DASHBOARD CARDS ============ -->
      <div class="stat-grid">
        <div class="stat-card stat-1"><div class="stat-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['total']; ?>">0</div><div class="stat-card-label">Total Events</div></div>
        <div class="stat-card stat-5"><div class="stat-card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['upcoming']; ?>">0</div><div class="stat-card-label">Upcoming Events</div></div>
        <div class="stat-card" style="background:linear-gradient(135deg,#c1443c,#8a2d27);"><div class="stat-card-icon"><i class="fa-solid fa-person-running"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['running']; ?>">0</div><div class="stat-card-label">Running Events</div></div>
        <div class="stat-card stat-2"><div class="stat-card-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['completed']; ?>">0</div><div class="stat-card-label">Completed Events</div></div>
        <div class="stat-card" style="background:linear-gradient(135deg,#6c757d,#495057);"><div class="stat-card-icon"><i class="fa-solid fa-ban"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['cancelled']; ?>">0</div><div class="stat-card-label">Cancelled Events</div></div>
        <div class="stat-card stat-4"><div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value" data-counter="<?php echo $stats['participants']; ?>">0</div><div class="stat-card-label">Total Participants</div></div>
      </div>

      <div class="d-flex justify-content-end mb-3">
        <a href="<?php echo BASE_URL; ?>/admin/events/statistics.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chart-column me-2"></i>Charts &amp; Trends</a>
      </div>

      <!-- ============ SEARCH & FILTER ============ -->
      <div class="panel">
        <div class="panel-head"><h2>Search &amp; Filter</h2></div>
        <form method="GET" class="row g-3">
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Sport</label>
            <select name="sport_id" class="form-select form-select-sm">
              <option value="0">All Sports</option>
              <?php foreach ($sports as $s): ?>
                <option value="<?php echo $s['sport_id']; ?>" <?php echo $filterSport === (int) $s['sport_id'] ? 'selected' : ''; ?>><?php echo safeOut($s['sport_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Coach</label>
            <select name="coach_id" class="form-select form-select-sm">
              <option value="0">All Coaches</option>
              <?php foreach ($coaches as $c): ?>
                <option value="<?php echo $c['coach_id']; ?>" <?php echo $filterCoach === (int) $c['coach_id'] ? 'selected' : ''; ?>><?php echo safeOut($c['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">All</option>
              <?php foreach (['Upcoming','Registration Open','Registration Closed','Running','Completed','Cancelled'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Venue</label>
            <input type="text" name="venue" class="form-control form-control-sm" value="<?php echo safeOut($filterVenue); ?>" placeholder="Search venue...">
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold">Date</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo safeOut($filterDate); ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Apply</button>
            <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>

      <!-- ============ EVENTS TABLE ============ -->
      <div class="panel">
        <div class="panel-head"><h2>All Events <span class="panel-sub">(<?php echo count($events); ?> shown)</span></h2></div>
        <div class="table-responsive">
          <table class="table align-middle event-table" id="eventsTable">
            <thead><tr><th>Banner</th><th>Title</th><th>Sport</th><th>Coach</th><th>Venue</th><th>Date</th><th>Participants</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($events as $e): ?>
                <tr>
                  <td><img src="<?php echo $e['banner_image'] ? UPLOADS_URL . '/' . $e['banner_image'] : ASSETS_URL . '/images/logo.svg'; ?>" alt="" style="width:44px; height:32px; border-radius:6px; object-fit:cover;"></td>
                  <td>
                    <?php echo safeOut($e['title']); ?>
                    <?php if ($e['created_by_role'] === 'coach' && !$e['is_approved']): ?>
                      <span class="badge bg-warning text-dark ms-1">Pending Approval</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo safeOut($e['sport_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($e['coach_name'] ?? '—'); ?></td>
                  <td><?php echo safeOut($e['venue'] ?? '—'); ?></td>
                  <td><?php echo date('M j, Y', strtotime($e['event_date'])); ?></td>
                  <td><?php echo (int) $e['participant_count']; ?><?php echo $e['max_participants'] ? ' / ' . $e['max_participants'] : ''; ?></td>
                  <td><span class="event-mini-badge badge-<?php echo str_replace(' ', '', $e['status']); ?>"><?php echo safeOut($e['status']); ?></span></td>
                  <td class="text-nowrap">
                    <a href="<?php echo BASE_URL; ?>/admin/events/view.php?id=<?php echo $e['event_id']; ?>" class="table-action-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/events/edit.php?id=<?php echo $e['event_id']; ?>" class="table-action-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <a href="<?php echo BASE_URL; ?>/admin/events/participants.php?id=<?php echo $e['event_id']; ?>" class="table-action-btn" title="Participants"><i class="fa-solid fa-users"></i></a>
                    <?php if ($e['created_by_role'] === 'coach' && !$e['is_approved']): ?>
                      <button type="button" class="table-action-btn event-action-btn" data-action="approve" data-id="<?php echo $e['event_id']; ?>" title="Approve"><i class="fa-solid fa-check text-success"></i></button>
                    <?php endif; ?>
                    <?php if ($e['status'] === 'Upcoming'): ?>
                      <button type="button" class="table-action-btn event-action-btn" data-action="publish" data-id="<?php echo $e['event_id']; ?>" title="Open Registration"><i class="fa-solid fa-door-open"></i></button>
                    <?php endif; ?>
                    <?php if (!in_array($e['status'], ['Completed', 'Cancelled'], true)): ?>
                      <button type="button" class="table-action-btn event-action-btn" data-action="cancel" data-id="<?php echo $e['event_id']; ?>" title="Cancel Event"><i class="fa-solid fa-ban text-danger"></i></button>
                    <?php endif; ?>
                    <button type="button" class="table-action-btn event-action-btn" data-action="delete" data-id="<?php echo $e['event_id']; ?>" data-name="<?php echo safeOut($e['title']); ?>" title="Delete"><i class="fa-solid fa-trash"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  <?php
  $extraFooterScripts = '
    <script>window.SMS_CSRF_TOKEN = ' . json_encode(csrfToken()) . ';</script>
    <script src="' . BASE_URL . '/assets/js/events.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
