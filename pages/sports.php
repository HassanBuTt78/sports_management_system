<?php
/**
 * ============================================================
 * sports.php
 * ------------------------------------------------------------
 * Public Sports page. Lists exactly the 3 supported sports with
 * live coach/player/team counts pulled from the database.
 * ============================================================
 */
$pageTitle = 'Sports';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$sportInfo = [
    'Cricket'  => ['img' => 'sport-cricket.svg',  'desc' => 'Inter-department T20 and one-day league fixtures every season, with dedicated Cricket coaches tracking every player\'s scores and rankings.'],
    'Football' => ['img' => 'sport-football.svg', 'desc' => 'Full-contact 11-a-side league with a season-long knockout bracket, run by Football-specific coaches and teams.'],
    'Hockey'   => ['img' => 'sport-hockey.svg',   'desc' => 'Fast-paced field hockey league with inter-department fixtures all season.'],
];

$sports = [];
if ($conn) {
    $sports = $conn->query(
        "SELECT s.*,
                (SELECT COUNT(*) FROM coaches WHERE sport_id = s.sport_id) AS coach_count,
                (SELECT COUNT(*) FROM players WHERE sport_id = s.sport_id) AS player_count,
                (SELECT COUNT(*) FROM teams   WHERE sport_id = s.sport_id) AS team_count
         FROM sports s ORDER BY FIELD(s.sport_name, 'Cricket','Football','Hockey')"
    )->fetch_all(MYSQLI_ASSOC);
}
?>

<section class="section">
  <div class="container">
    <div class="section-header text-center" data-aos="fade-up">
      <span class="section-tag">What We Offer</span>
      <h1 class="section-title">Our Sports Programs</h1>
      <p class="section-subtitle">Three sports, each with its own coach, roster, matches, and season-long rankings.</p>
    </div>

    <div class="row g-4">
      <?php foreach ($sports as $i => $sport):
        $meta = $sportInfo[$sport['sport_name']] ?? ['img' => 'sport-cricket.svg', 'desc' => ''];
        $anchor = strtolower($sport['sport_name']);
      ?>
        <div class="col-lg-4 col-md-6" id="<?php echo $anchor; ?>" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
          <div class="sport-card">
            <div class="sport-card-img">
              <img src="<?php echo ASSETS_URL; ?>/images/<?php echo $meta['img']; ?>" alt="<?php echo safeOut($sport['sport_name']); ?>" loading="lazy">
            </div>
            <div class="sport-card-body">
              <h3><?php echo safeOut($sport['sport_name']); ?></h3>
              <p><?php echo safeOut($meta['desc']); ?></p>
              <ul class="list-unstyled small text-muted mb-0">
                <li><i class="fa-solid fa-whistle me-2"></i><?php echo (int) $sport['coach_count']; ?> coach(es)</li>
                <li><i class="fa-solid fa-person-running me-2"></i><?php echo (int) $sport['player_count']; ?> player(s)</li>
                <li><i class="fa-solid fa-people-group me-2"></i><?php echo (int) $sport['team_count']; ?> team(s)</li>
              </ul>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
