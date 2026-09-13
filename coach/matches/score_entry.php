<?php
/**
 * ============================================================
 * coach/matches/score_entry.php
 * ------------------------------------------------------------
 * Score Entry — the visible fields change per sport, per
 * the brief (Cricket/Football/Hockey each have their
 * own stat set). Scoped to the coach's own sport in the match
 * lookup itself, and each save is an UPSERT keyed on the
 * existing (player_id, match_id) unique constraint from
 * Module 2's schema, so re-saving updates rather than duplicates.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('coach');

$coachId = (int) $_SESSION['user_id'];
$mySportId = (int) ($_SESSION['sport_id'] ?? 0);
$matchId = (int) ($_GET['id'] ?? 0);

$match = null;
if ($conn && $matchId > 0) {
    $stmt = $conn->prepare(
        'SELECT m.*, s.sport_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name
         FROM matches m LEFT JOIN sports s ON m.sport_id = s.sport_id
         LEFT JOIN teams ta ON m.team_one = ta.team_id LEFT JOIN teams tb ON m.team_two = tb.team_id
         WHERE m.match_id = ? AND m.sport_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $matchId, $mySportId);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$match) {
    redirectTo(BASE_URL . '/coach/matches/index.php');
}

$sportName = strtolower($match['sport_name'] ?? '');
$fieldSets = [
    'cricket'  => ['runs' => 'Runs', 'balls' => 'Balls', 'wickets' => 'Wickets', 'overs' => 'Overs', 'catches' => 'Catches', 'run_outs' => 'Run Outs', 'strike_rate' => 'Strike Rate', 'economy' => 'Economy'],
    'football' => ['goals' => 'Goals', 'assists' => 'Assists', 'yellow_cards' => 'Yellow Cards', 'red_cards' => 'Red Cards', 'saves' => 'Saves'],
    'hockey'   => ['goals' => 'Goals', 'assists' => 'Assists', 'yellow_cards' => 'Yellow/Green Cards', 'red_cards' => 'Red Cards', 'saves' => 'Saves'],
];
$fields = $fieldSets[$sportName] ?? ['points' => 'Points', 'custom_score' => 'Score'];
$integerFields = ['runs','balls','wickets','run_outs','catches','goals','assists','yellow_cards','red_cards','saves','points'];

$players = [];
$existingScores = [];
if ($conn) {
    $ids = array_filter([$match['team_one'], $match['team_two']]);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT player_id, full_name, profile_image FROM players WHERE team_id IN ($placeholders) ORDER BY full_name");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $stmt = $conn->prepare('SELECT * FROM player_scores WHERE match_id = ?');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $existingScores[$row['player_id']] = $row;
    }
    $stmt->close();
}

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Please try again.';
    } else {
        $allCols = ['goals','runs','wickets','catches','assists','race_time','points','custom_score',
                    'balls','overs','run_outs','strike_rate','economy','yellow_cards','red_cards','saves',
                    'blocks','service_aces','digs','finish_position','lap_time'];

        foreach ($players as $p) {
            $pid = (int) $p['player_id'];
            $rowInput = $_POST['scores'][$pid] ?? [];
            $values = [];
            foreach ($allCols as $col) {
                if (array_key_exists($col, $fields)) {
                    $raw = cleanInput((string) ($rowInput[$col] ?? ''));
                    if ($raw === '') {
                        $values[$col] = in_array($col, $integerFields, true) ? 0 : null;
                    } else {
                        $values[$col] = in_array($col, $integerFields, true) ? (int) $raw : (float) $raw;
                    }
                } else {
                    // Not part of this sport's field set — keep any previously
                    // recorded value untouched rather than clobbering it with 0.
                    $values[$col] = $existingScores[$pid][$col] ?? (in_array($col, ['goals','runs','wickets','catches','assists','points'], true) ? 0 : null);
                }
            }

            $stmt = $conn->prepare(
                'INSERT INTO player_scores (player_id, coach_id, match_id, goals, runs, wickets, catches, assists, race_time, points, custom_score,
                    balls, overs, run_outs, strike_rate, economy, yellow_cards, red_cards, saves, blocks, service_aces, digs, finish_position, lap_time)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    coach_id=VALUES(coach_id), goals=VALUES(goals), runs=VALUES(runs), wickets=VALUES(wickets),
                    catches=VALUES(catches), assists=VALUES(assists), race_time=VALUES(race_time), points=VALUES(points),
                    custom_score=VALUES(custom_score), balls=VALUES(balls), overs=VALUES(overs), run_outs=VALUES(run_outs),
                    strike_rate=VALUES(strike_rate), economy=VALUES(economy), yellow_cards=VALUES(yellow_cards),
                    red_cards=VALUES(red_cards), saves=VALUES(saves), blocks=VALUES(blocks), service_aces=VALUES(service_aces),
                    digs=VALUES(digs), finish_position=VALUES(finish_position), lap_time=VALUES(lap_time)'
            );
            $stmt->bind_param(
                'iiiiiiiididididdiiiiiiid',
                $pid, $coachId, $matchId,
                $values['goals'], $values['runs'], $values['wickets'], $values['catches'], $values['assists'],
                $values['race_time'], $values['points'], $values['custom_score'],
                $values['balls'], $values['overs'], $values['run_outs'], $values['strike_rate'], $values['economy'],
                $values['yellow_cards'], $values['red_cards'], $values['saves'], $values['blocks'], $values['service_aces'],
                $values['digs'], $values['finish_position'], $values['lap_time']
            );
            $stmt->execute();
            $stmt->close();
        }

        logActivity($conn, 'coach', $coachId, "Entered scores for match #{$matchId}");
        $saved = true;

        $stmt = $conn->prepare('SELECT * FROM player_scores WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $existingScores = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $existingScores[$row['player_id']] = $row;
        }
        $stmt->close();
    }
}

$pageTitle = 'Score Entry';
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/matches.css">
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/coach_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/coach_topbar.php'; ?>
    <div class="admin-content">

<div class="container py-5">
  <div class="page-heading">
    <div><h1>Score Entry</h1><p class="text-muted"><?php echo safeOut($match['team_a_name'] ?? 'TBD') . ($match['team_b_name'] ? ' vs ' . safeOut($match['team_b_name']) : ''); ?> &middot; <?php echo safeOut($match['sport_name']); ?></p></div>
    <a href="<?php echo BASE_URL; ?>/coach/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if ($saved): ?><div class="alert alert-success">Scores saved.</div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <?php if (empty($players)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No players found on either team.</p></div>
  <?php else: ?>
    <div class="panel">
      <form method="POST">
        <?php echo csrfField(); ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Player</th><?php foreach ($fields as $label): ?><th><?php echo $label; ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($players as $p):
                $pid = $p['player_id'];
                $existing = $existingScores[$pid] ?? [];
              ?>
                <tr>
                  <td class="mini-profile"><img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;"><?php echo safeOut($p['full_name']); ?></td>
                  <?php foreach (array_keys($fields) as $col): ?>
                    <td><input type="number" step="any" min="0" name="scores[<?php echo $pid; ?>][<?php echo $col; ?>]" class="form-control form-control-sm" style="width:90px;" value="<?php echo safeOut((string) ($existing[$col] ?? '')); ?>"></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn btn-primary mt-2"><i class="fa-solid fa-floppy-disk me-2"></i>Save Scores</button>
      </form>
    </div>
  <?php endif; ?>
</div>
    </div><!-- /.admin-content -->
  </main>
</div><!-- /.admin-layout -->
<script>window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
</body>
</html>
