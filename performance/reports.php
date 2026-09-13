<?php
/**
 * ============================================================
 * performance/reports.php
 * ------------------------------------------------------------
 * §26/§27: Player/Team/Coach/Sport/Top 10 reports, exportable as
 * CSV (real file, no dependency) and Print. No PDF/Excel-writing
 * library (TCPDF, PhpSpreadsheet, etc.) is installed anywhere in
 * this project, and §27 explicitly says not to introduce
 * unnecessary dependencies — so "PDF" here is the browser's own
 * native Print-to-PDF via a print stylesheet, not a server-
 * generated file. If true server-side PDF/XLSX becomes a real
 * requirement later, that's a one-line composer install away,
 * but it isn't added speculatively here.
 * ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole('admin', 'coach');

$myRole = currentRole();
$myId = (int) $_SESSION['user_id'];
$reportType = cleanInput($_GET['type'] ?? 'top10');
$format = cleanInput($_GET['format'] ?? '');

$sql = '';
$headers = [];
$title = '';

switch ($reportType) {
    case 'team':
        $title = 'Team Performance Report';
        $headers = ['Team', 'Sport', 'Matches', 'Wins', 'Losses', 'Draws', 'Win %', 'Avg Performance', 'Rank'];
        $sql = "SELECT t.team_name, s.sport_name, tp.matches_played, tp.wins, tp.losses, tp.draws, tp.win_percentage, tp.avg_team_performance, tp.team_rank
                FROM team_performance tp JOIN teams t ON tp.team_id = t.team_id JOIN sports s ON t.sport_id = s.sport_id
                ORDER BY tp.team_rank ASC";
        break;
    case 'coach':
        if ($myRole !== 'admin') { $reportType = 'top10'; break; }
        $title = 'Coach Performance Report';
        $headers = ['Coach', 'Sport', 'Players Managed', 'Avg Player Performance', 'Team Win %', 'Completed Matches'];
        $sql = "SELECT c.full_name, s.sport_name, cp.players_managed, cp.avg_player_performance, cp.team_win_percentage, cp.completed_matches
                FROM coach_performance cp JOIN coaches c ON cp.coach_id = c.coach_id JOIN sports s ON c.sport_id = s.sport_id
                ORDER BY cp.avg_player_performance DESC";
        break;
    case 'sport':
        $title = 'Sport Performance Report';
        $headers = ['Sport', 'Players With Data', 'Avg Performance', 'Avg Rating'];
        $sql = "SELECT s.sport_name, COUNT(pa.player_id) players, AVG(pa.performance_score) avg_perf, AVG(pa.average_rating) avg_rating
                FROM sports s LEFT JOIN performance_analysis pa ON s.sport_id = pa.sport_id AND pa.performance_score IS NOT NULL
                GROUP BY s.sport_id";
        break;
    case 'player':
        $title = 'Player Performance Report';
        $headers = ['Player', 'Sport', 'Team', 'Matches', 'Avg Rating', 'Performance Score', 'Level', 'Rank'];
        $sql = "SELECT p.full_name, s.sport_name, t.team_name, pa.matches_played, pa.average_rating, pa.performance_score, pa.performance_level, pa.`rank`
                FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id JOIN sports s ON pa.sport_id = s.sport_id
                LEFT JOIN teams t ON pa.team_id = t.team_id";
        if ($myRole === 'coach') {
            $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
            $stmt->bind_param('i', $myId);
            $stmt->execute();
            $mySportId = (int) ($stmt->get_result()->fetch_assoc()['sport_id'] ?? 0);
            $stmt->close();
            $sql .= " WHERE pa.sport_id = {$mySportId}";
        }
        $sql .= ' ORDER BY pa.performance_score DESC';
        break;
    case 'top10':
    default:
        $reportType = 'top10';
        $title = 'Top 10 Report';
        $headers = ['Rank', 'Player', 'Sport', 'Performance Score', 'Avg Rating'];
        $sql = "SELECT pa.`rank`, p.full_name, s.sport_name, pa.performance_score, pa.average_rating
                FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id JOIN sports s ON pa.sport_id = s.sport_id
                WHERE pa.performance_score IS NOT NULL ORDER BY pa.performance_score DESC LIMIT 10";
        break;
}

$rows = $conn ? $conn->query($sql)->fetch_all(MYSQLI_ASSOC) : [];

// Distinguish "performance hasn't been calculated at all yet" from
// "calculated, but this particular report/filter has nothing to show" —
// a bare "no data" message left admins with no idea what to do next.
$analysisTableEmpty = false;
if ($conn && empty($rows)) {
    $totalAnalysisRows = (int) $conn->query('SELECT COUNT(*) c FROM performance_analysis')->fetch_assoc()['c'];
    $analysisTableEmpty = $totalAnalysisRows === 0;
}

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_map(fn($v) => is_float($v) ? round($v, 2) : $v, array_values($row)));
    }
    fclose($out);
    exit;
}

$pageTitle = 'Performance Reports';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/performance.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../includes/role_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../includes/role_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading d-print-none">
    <div><h1><i class="fa-solid fa-file-invoice me-2"></i>Performance Reports</h1></div>
    <a href="<?php echo BASE_URL; ?>/performance/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="panel d-print-none mb-3">
    <div class="d-flex gap-2 flex-wrap mb-3">
      <a href="?type=top10" class="btn btn-sm <?php echo $reportType === 'top10' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Top 10</a>
      <a href="?type=player" class="btn btn-sm <?php echo $reportType === 'player' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Player</a>
      <a href="?type=team" class="btn btn-sm <?php echo $reportType === 'team' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Team</a>
      <?php if ($myRole === 'admin'): ?><a href="?type=coach" class="btn btn-sm <?php echo $reportType === 'coach' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Coach</a><?php endif; ?>
      <a href="?type=sport" class="btn btn-sm <?php echo $reportType === 'sport' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Sport</a>
    </div>
    <div class="d-flex gap-2">
      <a href="?type=<?php echo $reportType; ?>&format=csv" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</a>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print / Save as PDF</button>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?php echo safeOut($title); ?></h2></div>
    <?php if (empty($rows)): ?>
      <?php if ($analysisTableEmpty): ?>
        <p class="text-muted mb-2">Performance hasn't been calculated yet — this happens automatically the first time a match is completed, or you can trigger it manually right now.</p>
        <?php if ($myRole === 'admin'): ?>
          <a href="<?php echo BASE_URL; ?>/performance/recalculate.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate Now</a>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted mb-0">No results for this specific report/filter yet.</p>
      <?php endif; ?>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><?php foreach ($headers as $h): ?><th><?php echo safeOut($h); ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr><?php foreach ($row as $v): ?><td><?php echo $v !== null ? safeOut(is_float($v) ? (string) round($v, 2) : (string) $v) : '—'; ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
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
