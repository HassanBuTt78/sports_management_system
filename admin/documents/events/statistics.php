<?php
/**
 * ============================================================
 * admin/events/statistics.php
 * ------------------------------------------------------------
 * Chart.js: Events Per Sport, Monthly Events, Participation Trend.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$chartEventsPerSport = ['labels' => [], 'values' => []];
$chartMonthlyEvents = ['labels' => [], 'values' => []];
$chartParticipationTrend = ['labels' => [], 'values' => []];

if ($conn) {
    $res = $conn->query(
        'SELECT s.sport_name, COUNT(e.event_id) c FROM sports s LEFT JOIN events e ON e.sport_id = s.sport_id GROUP BY s.sport_id ORDER BY s.sport_name'
    );
    while ($row = $res->fetch_assoc()) {
        $chartEventsPerSport['labels'][] = $row['sport_name'];
        $chartEventsPerSport['values'][] = (int) $row['c'];
    }

    $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $monthCounts = array_fill(1, 12, 0);
    $res = $conn->query('SELECT MONTH(event_date) m, COUNT(*) c FROM events GROUP BY MONTH(event_date)');
    while ($row = $res->fetch_assoc()) {
        $monthCounts[(int) $row['m']] = (int) $row['c'];
    }
    $chartMonthlyEvents['labels'] = $monthNames;
    $chartMonthlyEvents['values'] = array_values($monthCounts);

    // Participation trend: registrations per month.
    $regCounts = array_fill(1, 12, 0);
    $res = $conn->query("SELECT MONTH(registered_at) m, COUNT(*) c FROM event_participants WHERE participation_status != 'Rejected' GROUP BY MONTH(registered_at)");
    while ($row = $res->fetch_assoc()) {
        $regCounts[(int) $row['m']] = (int) $row['c'];
    }
    $chartParticipationTrend['labels'] = $monthNames;
    $chartParticipationTrend['values'] = array_values($regCounts);
}

$pageTitle = 'Event Statistics';
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
        <div><h1>Event Statistics</h1><p>Charts across all four sports.</p></div>
        <a href="<?php echo BASE_URL; ?>/admin/events/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Events</a>
      </div>

      <div class="grid-charts">
        <div class="panel">
          <div class="panel-head"><h2>Events Per Sport</h2></div>
          <div class="chart-wrap"><canvas id="chartEventsPerSport"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Monthly Events</h2></div>
          <div class="chart-wrap"><canvas id="chartMonthlyEvents"></canvas></div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Participation Trend</h2></div>
        <div class="chart-wrap"><canvas id="chartParticipationTrend"></canvas></div>
      </div>

    </div>
  <?php
  $extraFooterScripts = '
    <script>
      window.eventStatsCharts = {
        eventsPerSport: ' . json_encode($chartEventsPerSport) . ',
        monthlyEvents: ' . json_encode($chartMonthlyEvents) . ',
        participationTrend: ' . json_encode($chartParticipationTrend) . '
      };
    </script>
    <script src="' . BASE_URL . '/assets/js/events.js"></script>
  ';
  require_once __DIR__ . '/../../includes/admin_footer.php';
  ?>
