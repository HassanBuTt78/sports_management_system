<?php
/**
 * ============================================================
 * coach/matches/player_rating.php
 * ------------------------------------------------------------
 * "Coach gives ★★★★★ Rating 1-5, and also writes remarks."
 * Each save is an UPSERT keyed on (player_id, match_id) — see
 * schema_module9.sql's uq_rating_player_match — so re-rating a
 * player for the same match updates rather than duplicates.
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
        'SELECT m.*, ta.team_name AS team_a_name, tb.team_name AS team_b_name
         FROM matches m LEFT JOIN teams ta ON m.team_one = ta.team_id LEFT JOIN teams tb ON m.team_two = tb.team_id
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

$players = [];
$existingRatings = [];
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
    $stmt = $conn->prepare('SELECT player_id, rating, review FROM player_ratings WHERE match_id = ?');
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $existingRatings[$row['player_id']] = $row;
    }
    $stmt->close();
}

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Please try again.';
    } else {
        foreach ($players as $p) {
            $pid = (int) $p['player_id'];
            $rawRating = cleanInput((string) ($_POST['rating'][$pid] ?? ''));
            $review = cleanInput((string) ($_POST['review'][$pid] ?? ''));

            if ($rawRating === '' || !ctype_digit($rawRating) || (int) $rawRating < 1 || (int) $rawRating > 5) {
                continue; // No rating given for this player — skip, don't force a default.
            }
            $rating = (int) $rawRating;
            $reviewVal = $review !== '' ? $review : null;

            $stmt = $conn->prepare(
                'INSERT INTO player_ratings (player_id, coach_id, match_id, rating, review) VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE coach_id=VALUES(coach_id), rating=VALUES(rating), review=VALUES(review)'
            );
            $stmt->bind_param('iiiis', $pid, $coachId, $matchId, $rating, $reviewVal);
            $stmt->execute();
            $stmt->close();

            notifyUser($conn, 'player', $pid, 'New Match Rating', 'Your coach rated your performance for a recent match.');
        }

        logActivity($conn, 'coach', $coachId, "Rated players for match #{$matchId}");
        $saved = true;

        $stmt = $conn->prepare('SELECT player_id, rating, review FROM player_ratings WHERE match_id = ?');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $existingRatings = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $existingRatings[$row['player_id']] = $row;
        }
        $stmt->close();
    }
}

$pageTitle = 'Player Ratings';
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
    <div><h1>Player Ratings</h1><p class="text-muted"><?php echo safeOut($match['team_a_name'] ?? 'TBD') . ($match['team_b_name'] ? ' vs ' . safeOut($match['team_b_name']) : ''); ?></p></div>
    <a href="<?php echo BASE_URL; ?>/coach/matches/index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if ($saved): ?><div class="alert alert-success">Ratings saved and players notified.</div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo safeOut($err); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <?php if (empty($players)): ?>
    <div class="panel text-center py-5"><p class="text-muted mb-0">No players found on either team.</p></div>
  <?php else: ?>
    <form method="POST">
      <?php echo csrfField(); ?>
      <div class="row g-3">
        <?php foreach ($players as $p):
          $pid = $p['player_id'];
          $existing = $existingRatings[$pid] ?? null;
        ?>
          <div class="col-md-6">
            <div class="panel">
              <div class="d-flex align-items-center gap-2 mb-2">
                <img src="<?php echo $p['profile_image'] ? UPLOADS_URL . '/' . $p['profile_image'] : ASSETS_URL . '/images/default-avatar.svg'; ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                <strong><?php echo safeOut($p['full_name']); ?></strong>
              </div>
              <div class="star-rating-input mb-2" data-player="<?php echo $pid; ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label>
                    <input type="radio" name="rating[<?php echo $pid; ?>]" value="<?php echo $i; ?>" <?php echo ($existing['rating'] ?? 0) == $i ? 'checked' : ''; ?> class="d-none">
                    <i class="fa-solid fa-star star-icon <?php echo ($existing['rating'] ?? 0) >= $i ? 'active' : ''; ?>" data-value="<?php echo $i; ?>"></i>
                  </label>
                <?php endfor; ?>
              </div>
              <textarea name="review[<?php echo $pid; ?>]" class="form-control form-control-sm" rows="2" placeholder="Remarks (optional)"><?php echo safeOut((string) ($existing['review'] ?? '')); ?></textarea>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary mt-3"><i class="fa-solid fa-floppy-disk me-2"></i>Save Ratings</button>
    </form>
  <?php endif; ?>
</div>
<script src="<?php echo BASE_URL; ?>/assets/js/matches.js"></script>
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
