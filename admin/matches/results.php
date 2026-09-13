<?php
/**
 * ============================================================
 * admin/matches/results.php
 * ------------------------------------------------------------
 * Declare the final result: winner, runner-up, MVP, a text
 * summary, and sport-specific awards (Best Bowler/Best Batsman
 * for Cricket, Best Goalkeeper for Football/Hockey, Top Scorer
 * for any sport) into match_awards.
 * Marks the match Completed and notifies both teams' coaches +
 * the MVP's player account.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/performance_engine.php';

requireRole('admin');

$matchId = (int) ($_GET['id'] ?? 0);
$match = null;
if ($conn && $matchId > 0) {
    $stmt = $conn->prepare(
        'SELECT m.*, s.sport_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name
         FROM matches m LEFT JOIN sports s ON m.sport_id = s.sport_id
         LEFT JOIN teams ta ON m.team_one = ta.team_id LEFT JOIN teams tb ON m.team_two = tb.team_id
         WHERE m.match_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$match) {
    redirectTo(BASE_URL . '/admin/matches/index.php');
}

$sportName = strtolower($match['sport_name'] ?? '');
$awardTypes = ['Top Scorer'];
if ($sportName === 'cricket') $awardTypes = array_merge(['Best Bowler', 'Best Batsman'], $awardTypes);
if ($sportName === 'football') $awardTypes[] = 'Best Goalkeeper';
if ($sportName === 'hockey') $awardTypes[] = 'Best Goalkeeper';

$teamPlayers = [];
$existingAwards = [];
if ($conn) {
    $ids = array_filter([$match['team_one'], $match['team_two']]);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT player_id, full_name FROM players WHERE team_id IN ($placeholders) ORDER BY full_name");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $teamPlayers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $stmt = $conn->prepare('SELECT award_type, player_id FROM match_awards WHERE match_id = ?');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $existingAwards[$row['award_type']] = $row['player_id'];
    }
    $stmt->close();
}

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Please try again.';
    } else {
        $winnerTeam = cleanInput($_POST['winner_team'] ?? '');
        $runnerUp = cleanInput($_POST['runner_up_team'] ?? '');
        $mvpPlayer = cleanInput($_POST['mvp_player_id'] ?? '');
        $summary = cleanInput($_POST['result_summary'] ?? '');

        $winnerId = ($winnerTeam !== '' && ctype_digit($winnerTeam)) ? (int) $winnerTeam : null;
        $runnerUpId = ($runnerUp !== '' && ctype_digit($runnerUp)) ? (int) $runnerUp : null;
        $mvpId = ($mvpPlayer !== '' && ctype_digit($mvpPlayer)) ? (int) $mvpPlayer : null;
        $summaryVal = $summary !== '' ? $summary : null;

        // ---------- Required-workflow validation (cannot be bypassed) ----------
        if ($summaryVal === null) {
            $errors[] = 'Please enter the final score / result summary before completing the match.';
        }
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM player_scores WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        if ((int) $stmt->get_result()->fetch_assoc()['c'] === 0) {
            $errors[] = 'At least one participating player\'s score must be entered before completing the match.';
        }
        $stmt->close();
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM player_ratings WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        if ((int) $stmt->get_result()->fetch_assoc()['c'] === 0) {
            $errors[] = 'At least one participating player must be rated before completing the match.';
        }
        $stmt->close();
        if ($match['status'] === 'Completed') {
            $errors[] = 'This match has already been completed.';
        }

        if (!empty($errors)) {
            // fall through to the template below with $errors set
        } else {
        $stmt = $conn->prepare(
            "UPDATE matches SET winner_team=?, runner_up_team=?, mvp_player_id=?, result_summary=?, status='Completed' WHERE match_id = ?"
        );
        $stmt->bind_param('iiisi', $winnerId, $runnerUpId, $mvpId, $summaryVal, $matchId);

        if ($stmt->execute()) {
            $stmt->close();

            // Sport-specific awards: replace whatever was set before.
            $del = $conn->prepare('DELETE FROM match_awards WHERE match_id = ?');
            $del->bind_param('i', $matchId);
            $del->execute();
            $del->close();

            foreach ($awardTypes as $type) {
                $fieldName = 'award_' . preg_replace('/[^a-z]/', '', strtolower($type));
                $playerVal = cleanInput($_POST[$fieldName] ?? '');
                if ($playerVal !== '' && ctype_digit($playerVal)) {
                    $pid = (int) $playerVal;
                    $ins = $conn->prepare('INSERT INTO match_awards (match_id, award_type, player_id) VALUES (?,?,?)');
                    $ins->bind_param('isi', $matchId, $type, $pid);
                    $ins->execute();
                    $ins->close();
                }
            }

            logActivity($conn, 'admin', (int) $_SESSION['user_id'], "Declared result for match #{$matchId}");

            // §24 — automatic recalculation whenever a match becomes Completed, no manual step required.
            recalculateAllPerformance($conn);

            foreach (array_filter([$match['team_one'], $match['team_two']]) as $tid) {
                $stmt2 = $conn->prepare('SELECT coach_id FROM teams WHERE team_id = ? LIMIT 1');
                $stmt2->bind_param('i', $tid);
                $stmt2->execute();
                $cid = $stmt2->get_result()->fetch_assoc()['coach_id'] ?? null;
                $stmt2->close();
                if ($cid) notifyUser($conn, 'coach', (int) $cid, 'Match Result Posted', 'The result for your match has been posted.');
            }
            if ($mvpId) notifyUser($conn, 'player', $mvpId, "You're the MVP!", 'You were named Most Valuable Player for a recent match.');

            $saved = true;
            $match['winner_team'] = $winnerId;
            $match['runner_up_team'] = $runnerUpId;
            $match['mvp_player_id'] = $mvpId;
            $match['result_summary'] = $summaryVal;
            $match['status'] = 'Completed';

            $stmt = $conn->prepare('SELECT award_type, player_id FROM match_awards WHERE match_id = ?');
            $stmt->bind_param('i', $matchId);
            $stmt->execute();
            $existingAwards = [];
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $existingAwards[$row['award_type']] = $row['player_id'];
            }
            $stmt->close();
        } else {
            $stmt->close();
            $errors[] = 'Could not save the result.';
        }
        }
    }
}

$pageTitle = 'Match Results';
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
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>

    <div class="admin-content">
      <div class="page-heading">
        <div><h1>Match Results</h1><p><?php echo safeOut($match['team_a_name'] ?? 'TBD') . ($match['team_b_name'] ? ' vs ' . safeOut($match['team_b_name']) : ''); ?></p></div>
        <a href="<?php echo BASE_URL; ?>/admin/matches/view.php?id=<?php echo $matchId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
      </div>

      <?php if ($saved): ?><div class="alert alert-success">Result saved — match marked Completed and coaches notified.</div><?php endif; ?>
      <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <div class="panel">
        <form method="POST" class="needs-validation" novalidate>
          <?php echo csrfField(); ?>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Winner Team</label>
              <select name="winner_team" class="form-select">
                <option value="">— None —</option>
                <?php if ($match['team_one']): ?><option value="<?php echo $match['team_one']; ?>" <?php echo (int) $match['winner_team'] === (int) $match['team_one'] ? 'selected' : ''; ?>><?php echo safeOut($match['team_a_name']); ?></option><?php endif; ?>
                <?php if ($match['team_two']): ?><option value="<?php echo $match['team_two']; ?>" <?php echo (int) $match['winner_team'] === (int) $match['team_two'] ? 'selected' : ''; ?>><?php echo safeOut($match['team_b_name']); ?></option><?php endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Runner Up</label>
              <select name="runner_up_team" class="form-select">
                <option value="">— None —</option>
                <?php if ($match['team_one']): ?><option value="<?php echo $match['team_one']; ?>" <?php echo (int) $match['runner_up_team'] === (int) $match['team_one'] ? 'selected' : ''; ?>><?php echo safeOut($match['team_a_name']); ?></option><?php endif; ?>
                <?php if ($match['team_two']): ?><option value="<?php echo $match['team_two']; ?>" <?php echo (int) $match['runner_up_team'] === (int) $match['team_two'] ? 'selected' : ''; ?>><?php echo safeOut($match['team_b_name']); ?></option><?php endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">MVP (Best Player)</label>
              <select name="mvp_player_id" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($teamPlayers as $p): ?><option value="<?php echo $p['player_id']; ?>" <?php echo (int) $match['mvp_player_id'] === (int) $p['player_id'] ? 'selected' : ''; ?>><?php echo safeOut($p['full_name']); ?></option><?php endforeach; ?>
              </select>
            </div>

            <?php foreach ($awardTypes as $type):
              $fieldName = 'award_' . preg_replace('/[^a-z]/', '', strtolower($type));
              $currentPlayer = $existingAwards[$type] ?? null;
            ?>
              <div class="col-md-4">
                <label class="form-label fw-semibold"><?php echo safeOut($type); ?> <span class="text-muted small">(<?php echo safeOut($match['sport_name']); ?>)</span></label>
                <select name="<?php echo $fieldName; ?>" class="form-select">
                  <option value="">— None —</option>
                  <?php foreach ($teamPlayers as $p): ?><option value="<?php echo $p['player_id']; ?>" <?php echo (int) $currentPlayer === (int) $p['player_id'] ? 'selected' : ''; ?>><?php echo safeOut($p['full_name']); ?></option><?php endforeach; ?>
                </select>
              </div>
            <?php endforeach; ?>

            <div class="col-12">
              <label class="form-label fw-semibold">Result Summary</label>
              <textarea name="result_summary" class="form-control" rows="3" placeholder="e.g. Titans won 3-1 in a closely fought match."><?php echo safeOut((string) $match['result_summary']); ?></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-auth-submit mt-4" style="width:auto; padding-left:28px; padding-right:28px;"><i class="fa-solid fa-medal me-2"></i>Save Result &amp; Mark Completed</button>
        </form>
      </div>

    </div>
  <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
