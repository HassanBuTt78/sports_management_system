<?php
/**
 * ============================================================
 * includes/performance_engine.php
 * ------------------------------------------------------------
 * All Module 11 scoring logic lives here. Reads its weights
 * exclusively from config/performance_weights.php — nothing in
 * this file hard-codes a weight.
 *
 * CORE PRINCIPLE ("use available metrics only", "never invent
 * data"): every component calculator returns either a 0-100
 * number or NULL. The master calculator then redistributes the
 * configured weights proportionally across whichever components
 * actually returned a number — a player with no coach rating
 * yet still gets a fair score built entirely from what IS known,
 * rather than being penalized with an implicit zero.
 *
 * HONESTY NOTE ON FOOTBALL/HOCKEY: the brief lists "shots,
 * tackles, clean sheets" as trackable stats, but player_scores
 * has no columns for these — they were never part of this
 * project's schema and are not invented here. Both sports'
 * defensive_contribution uses `saves` only.
 * ============================================================
 */

function performanceWeights(): array {
    static $weights = null;
    if ($weights === null) {
        $weights = require __DIR__ . '/../config/performance_weights.php';
    }
    return $weights;
}

/** Clamp $value into [0, $cap], then scale to a 0-100 range. */
function pfNormalize(float $value, float $cap): float {
    if ($cap <= 0) return 0.0;
    return max(0.0, min(100.0, ($value / $cap) * 100));
}

function pfStddev(array $values): float {
    $n = count($values);
    if ($n < 2) return 0.0;
    $mean = array_sum($values) / $n;
    $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / $n;
    return sqrt($variance);
}

/**
 * Combines a list of [weight, value|null] pairs, redistributing
 * any NULL sub-component's weight proportionally across the
 * rest. Returns null only if every sub-component is null.
 *
 * IMPORTANT: takes a list of pairs, NOT an associative array
 * keyed by weight — PHP silently truncates float array keys to
 * integers (0.25 and 0.35 would both become key 0 and collide),
 * which would otherwise make every weight collapse to the same
 * bucket and only the last component survive.
 */
function pfCombineWeighted(array $pairs): ?float {
    $weightSum = 0.0;
    foreach ($pairs as [$weight, $value]) {
        if ($value !== null) $weightSum += $weight;
    }
    if ($weightSum <= 0) return null;
    $score = 0.0;
    foreach ($pairs as [$weight, $value]) {
        if ($value !== null) $score += ($weight / $weightSum) * $value;
    }
    return $score;
}

/**
 * Computes a single sport-specific 0-100 score from ONE
 * player_scores row (used both for aggregate averaging and for
 * per-match / consistency calculations).
 */
function pfSportScoreFromRow(string $sportName, array $row, array $allRows = []): ?float {
    $w = performanceWeights()['sport_weights'];

    switch ($sportName) {
        case 'Cricket':
            $sw = $w['Cricket'];
            $batting = pfNormalize((float) $row['runs'], 60);
            if (!empty($row['strike_rate'])) {
                $batting = ($batting * 0.7) + (pfNormalize((float) $row['strike_rate'], 150) * 0.3);
            }
            $bowling = pfNormalize((float) $row['wickets'], 4);
            if (!empty($row['economy'])) {
                $bowling = ($bowling * 0.7) + ((100 - pfNormalize((float) $row['economy'], 12)) * 0.3);
            }
            $fielding = pfNormalize((float) $row['catches'] + (float) ($row['run_outs'] ?? 0), 2);
            return pfCombineWeighted([[$sw['batting'], $batting], [$sw['bowling'], $bowling], [$sw['fielding'], $fielding]]);

        case 'Football':
            $sw = $w['Football'];
            $goalContribution = pfNormalize((float) $row['goals'] + (float) $row['assists'], 2);
            $defensive = !empty($row['saves']) || $row['saves'] === '0' || $row['saves'] === 0
                ? pfNormalize((float) ($row['saves'] ?? 0), 5) : null;
            $discipline = max(0, 100 - ((float) ($row['yellow_cards'] ?? 0) * 5) - ((float) ($row['red_cards'] ?? 0) * 15));
            return pfCombineWeighted([
                [$sw['goal_contribution'], $goalContribution],
                [$sw['defensive_contribution'], $defensive],
                [$sw['discipline'], $discipline],
            ]);

        case 'Hockey':
            $sw = $w['Hockey'];
            $goalContribution = pfNormalize((float) $row['goals'] + (float) $row['assists'], 2);
            $defensive = !empty($row['saves']) || $row['saves'] === '0' || $row['saves'] === 0
                ? pfNormalize((float) ($row['saves'] ?? 0), 5) : null;
            $discipline = max(0, 100 - ((float) ($row['yellow_cards'] ?? 0) * 5) - ((float) ($row['red_cards'] ?? 0) * 15));
            return pfCombineWeighted([
                [$sw['goal_contribution'], $goalContribution],
                [$sw['defensive_contribution'], $defensive],
                [$sw['discipline'], $discipline],
            ]);

        default:
            return null;
    }
}

/** Aggregate (average) sport score across every row a player has for their sport. */
function pfAggregateSportScore(string $sportName, array $rows): ?float {
    if (empty($rows)) return null;
    $scores = [];
    foreach ($rows as $row) {
        $s = pfSportScoreFromRow($sportName, $row, $rows);
        if ($s !== null) $scores[] = $s;
    }
    return !empty($scores) ? array_sum($scores) / count($scores) : null;
}

/** Consistency: inverse of the standard deviation across a player's per-match sport scores. Needs >=2 matches. */
function pfConsistencyComponent(string $sportName, array $rows): ?float {
    if (count($rows) < 2) return null;
    $scores = [];
    foreach ($rows as $row) {
        $s = pfSportScoreFromRow($sportName, $row, $rows);
        if ($s !== null) $scores[] = $s;
    }
    if (count($scores) < 2) return null;
    return 100 - pfNormalize(pfStddev($scores), 30);
}

/** Improvement: recent-N vs everything-before, centered at 50 (0 change = neutral). Needs enough history to compare. */
function pfImprovementComponent(string $sportName, array $rowsChronological, int $recentCount = 3): ?float {
    $n = count($rowsChronological);
    if ($n < 2) return null;
    $recentCount = min($recentCount, intdiv($n, 2)) ?: 1;
    if ($n - $recentCount < 1) return null;

    $earlier = array_slice($rowsChronological, 0, $n - $recentCount);
    $recent = array_slice($rowsChronological, $n - $recentCount);

    $earlierAvg = pfAggregateSportScore($sportName, $earlier);
    $recentAvg = pfAggregateSportScore($sportName, $recent);
    if ($earlierAvg === null || $recentAvg === null || $earlierAvg <= 0) return null;

    $pctChange = (($recentAvg - $earlierAvg) / $earlierAvg) * 100;
    return max(0.0, min(100.0, 50 + $pctChange));
}

/** Win/loss/draw record component, from `matches` — team-based outcome, same logic for every sport. */
function pfMatchResultComponent(mysqli $conn, int $playerId, int $teamId, array $matchIds): ?array {
    if (empty($matchIds) || !$teamId) return ['component' => null, 'won' => 0, 'lost' => 0, 'drawn' => 0];
    $points = performanceWeights()['match_result_points'];
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $types = str_repeat('i', count($matchIds));
    $stmt = $conn->prepare("SELECT team_one, team_two, winner_team FROM matches WHERE match_id IN ($placeholders)");
    $stmt->bind_param($types, ...$matchIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $won = $lost = $drawn = 0;
    $scores = [];
    foreach ($rows as $m) {
        $onThisTeam = (int) $m['team_one'] === $teamId || (int) $m['team_two'] === $teamId;
        if (!$onThisTeam) continue;
        if ($m['winner_team'] === null) {
            $drawn++; $scores[] = $points['draw'];
        } elseif ((int) $m['winner_team'] === $teamId) {
            $won++; $scores[] = $points['win'];
        } else {
            $lost++; $scores[] = $points['loss'];
        }
    }
    return [
        'component' => !empty($scores) ? array_sum($scores) / count($scores) : null,
        'won' => $won, 'lost' => $lost, 'drawn' => $drawn,
    ];
}

/**
 * Master calculator. $asOfDate (optional) restricts which
 * completed matches count — used to build genuine point-in-time
 * history entries rather than repeating today's snapshot for
 * every past match.
 */
function calculatePlayerPerformance(mysqli $conn, int $playerId, ?string $asOfDate = null): array {
    $stmt = $conn->prepare('SELECT p.sport_id, p.team_id, s.sport_name FROM players p JOIN sports s ON p.sport_id = s.sport_id WHERE p.player_id = ? LIMIT 1');
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$player) {
        return ['has_data' => false, 'message' => 'No performance data available yet.'];
    }
    $sportName = $player['sport_name'];
    $sportId = (int) $player['sport_id'];
    $teamId = $player['team_id'] ? (int) $player['team_id'] : 0;

    $dateFilter = $asOfDate ? 'AND m.match_date <= ?' : '';
    $sql = "SELECT ps.*, m.match_id, m.match_date FROM player_scores ps
            JOIN matches m ON ps.match_id = m.match_id
            WHERE ps.player_id = ? AND m.status = 'Completed' {$dateFilter}
            ORDER BY m.match_date ASC, m.match_id ASC";
    $stmt = $conn->prepare($sql);
    if ($asOfDate) { $stmt->bind_param('is', $playerId, $asOfDate); } else { $stmt->bind_param('i', $playerId); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $matchesPlayed = count($rows);
    if ($matchesPlayed === 0) {
        return ['has_data' => false, 'message' => 'No performance data available yet.', 'matches_played' => 0];
    }

    // Coach rating component (as-of cutoff respected too).
    $ratingSql = "SELECT rating FROM player_ratings WHERE player_id = ?" . ($asOfDate ? " AND created_at <= ?" : "");
    $stmt = $conn->prepare($ratingSql);
    if ($asOfDate) { $stmt->bind_param('is', $playerId, $asOfDate); } else { $stmt->bind_param('i', $playerId); }
    $stmt->execute();
    $ratings = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'rating');
    $stmt->close();
    $avgRating = !empty($ratings) ? array_sum($ratings) / count($ratings) : null;
    $coachRatingComponent = $avgRating !== null ? $avgRating * 20 : null;

    $matchIds = array_column($rows, 'match_id');
    $matchResult = pfMatchResultComponent($conn, $playerId, $teamId, $matchIds);
    $sportStatsComponent = pfAggregateSportScore($sportName, $rows);
    $consistencyComponent = pfConsistencyComponent($sportName, $rows);
    $improvementComponent = pfImprovementComponent($sportName, $rows);

    $w = performanceWeights();
    $finalScore = pfCombineWeighted([
        [$w['coach_rating_weight'], $coachRatingComponent],
        [$w['match_performance_weight'], $matchResult['component']],
        [$w['sport_statistics_weight'], $sportStatsComponent],
        [$w['consistency_weight'], $consistencyComponent],
        [$w['improvement_weight'], $improvementComponent],
    ]);

    $stars = null;
    $level = 'Insufficient Data';
    if ($finalScore !== null) {
        foreach ($w['star_thresholds'] as $t) { if ($finalScore >= $t['min']) { $stars = $t['stars']; break; } }
        foreach ($w['level_thresholds'] as $t) { if ($finalScore >= $t['min']) { $level = $t['level']; break; } }
    }

    $trend = 'Insufficient Data';
    if ($improvementComponent !== null) {
        if ($improvementComponent > 57) $trend = 'Improving';
        elseif ($improvementComponent < 43) $trend = 'Declining';
        else $trend = 'Stable';
    } elseif ($matchesPlayed >= 1) {
        $trend = 'New';
    }

    return [
        'has_data' => true,
        'sport_id' => $sportId,
        'sport_name' => $sportName,
        'team_id' => $teamId ?: null,
        'matches_played' => $matchesPlayed,
        'matches_won' => $matchResult['won'],
        'matches_lost' => $matchResult['lost'],
        'matches_drawn' => $matchResult['drawn'],
        'average_rating' => $avgRating,
        'performance_score' => $finalScore,
        'star_rating' => $stars,
        'performance_level' => $level,
        'trend' => $trend,
        'limited_data' => $matchesPlayed < $w['limited_data_match_threshold'],
        'components' => [
            'coach_rating' => $coachRatingComponent,
            'match_performance' => $matchResult['component'],
            'sport_statistics' => $sportStatsComponent,
            'consistency' => $consistencyComponent,
            'improvement' => $improvementComponent,
        ],
        'weights' => [
            'coach_rating' => $w['coach_rating_weight'],
            'match_performance' => $w['match_performance_weight'],
            'sport_statistics' => $w['sport_statistics_weight'],
            'consistency' => $w['consistency_weight'],
            'improvement' => $w['improvement_weight'],
        ],
    ];
}

/**
 * Full system recalculation — run automatically whenever a match
 * is marked Completed (see admin/matches/results.php and
 * coach/matches/results.php), and available as a manual admin
 * action (performance/recalculate.php). Returns a summary for
 * display: players processed, records updated, completion time.
 */
function recalculateAllPerformance(mysqli $conn): array {
    $startTime = microtime(true);
    $playersProcessed = 0;
    $recordsUpdated = 0;

    $players = $conn->query('SELECT player_id FROM players')->fetch_all(MYSQLI_ASSOC);
    $scored = [];

    foreach ($players as $p) {
        $playerId = (int) $p['player_id'];
        $playersProcessed++;
        $result = calculatePlayerPerformance($conn, $playerId);
        if (!$result['has_data']) continue;

        $scored[$playerId] = $result;

        // Preserve the old rank before this player's row is overwritten.
        $stmt = $conn->prepare('SELECT `rank` FROM performance_analysis WHERE player_id = ? LIMIT 1');
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $oldRank = $stmt->get_result()->fetch_assoc()['rank'] ?? null;
        $stmt->close();

        $stmt = $conn->prepare(
            'INSERT INTO performance_analysis
             (player_id, sport_id, team_id, average_rating, total_score, matches_played, performance_level,
              performance_score, star_rating, coach_rating_component, match_component, sport_stats_component,
              consistency_component, improvement_component, matches_won, matches_lost, matches_drawn, previous_rank, trend)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
              sport_id=VALUES(sport_id), team_id=VALUES(team_id), average_rating=VALUES(average_rating),
              total_score=VALUES(total_score), matches_played=VALUES(matches_played), performance_level=VALUES(performance_level),
              performance_score=VALUES(performance_score), star_rating=VALUES(star_rating),
              coach_rating_component=VALUES(coach_rating_component), match_component=VALUES(match_component),
              sport_stats_component=VALUES(sport_stats_component), consistency_component=VALUES(consistency_component),
              improvement_component=VALUES(improvement_component), matches_won=VALUES(matches_won),
              matches_lost=VALUES(matches_lost), matches_drawn=VALUES(matches_drawn), previous_rank=VALUES(previous_rank),
              trend=VALUES(trend)'
        );
        $avgRating = $result['average_rating'] ?? 0;
        $score = $result['performance_score'] ?? 0;
        $stmt->bind_param(
            'iiiddisdddddddiiiis',
            $playerId, $result['sport_id'], $result['team_id'], $avgRating, $score, $result['matches_played'],
            $result['performance_level'], $result['performance_score'], $result['star_rating'],
            $result['components']['coach_rating'], $result['components']['match_performance'],
            $result['components']['sport_statistics'], $result['components']['consistency'],
            $result['components']['improvement'], $result['matches_won'], $result['matches_lost'],
            $result['matches_drawn'], $oldRank, $result['trend']
        );
        $stmt->execute();
        $stmt->close();
        $recordsUpdated++;

        // Historical record — one immutable row per (player, completed match), only inserted once.
        $stmt = $conn->prepare('SELECT match_id FROM player_performance_history WHERE player_id = ?');
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $alreadyRecorded = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'match_id');
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT m.match_id, m.match_date FROM player_scores ps JOIN matches m ON ps.match_id = m.match_id
             WHERE ps.player_id = ? AND m.status = 'Completed' ORDER BY m.match_date ASC"
        );
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $completedMatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($completedMatches as $cm) {
            if (in_array((int) $cm['match_id'], array_map('intval', $alreadyRecorded), true)) continue;
            $pointInTime = calculatePlayerPerformance($conn, $playerId, $cm['match_date']);
            if (!$pointInTime['has_data']) continue;
            $stmt = $conn->prepare(
                'INSERT IGNORE INTO player_performance_history
                 (player_id, match_id, sport_id, performance_score, coach_rating, sport_score, consistency_score, improvement_score, performance_level)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param(
                'iiiddddds',
                $playerId, $cm['match_id'], $pointInTime['sport_id'], $pointInTime['performance_score'],
                $pointInTime['average_rating'], $pointInTime['components']['sport_statistics'],
                $pointInTime['components']['consistency'], $pointInTime['components']['improvement'],
                $pointInTime['performance_level']
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    // ---- Ranking pass: score desc, then avg_rating desc, then matches_played desc (tie-break via "recent performance"). ----
    uasort($scored, function ($a, $b) {
        $scoreCmp = ($b['performance_score'] ?? 0) <=> ($a['performance_score'] ?? 0);
        if ($scoreCmp !== 0) return $scoreCmp;
        $ratingCmp = ($b['average_rating'] ?? 0) <=> ($a['average_rating'] ?? 0);
        if ($ratingCmp !== 0) return $ratingCmp;
        return ($b['matches_played'] ?? 0) <=> ($a['matches_played'] ?? 0);
    });
    $rank = 1;
    foreach (array_keys($scored) as $playerId) {
        $stmt = $conn->prepare('UPDATE performance_analysis SET `rank` = ?, rank_change = CASE WHEN previous_rank IS NULL THEN NULL ELSE CAST(previous_rank AS SIGNED) - ? END WHERE player_id = ?');
        $stmt->bind_param('iii', $rank, $rank, $playerId);
        $stmt->execute();
        $stmt->close();
        $rank++;
    }

    recalculateTeamPerformance($conn);
    recalculateCoachPerformance($conn);

    return [
        'players_processed' => $playersProcessed,
        'records_updated' => $recordsUpdated,
        'completed_at' => date('Y-m-d H:i:s'),
        'seconds_taken' => round(microtime(true) - $startTime, 2),
    ];
}

function recalculateTeamPerformance(mysqli $conn): void {
    $teams = $conn->query('SELECT team_id FROM teams')->fetch_all(MYSQLI_ASSOC);
    $teamStats = [];
    foreach ($teams as $t) {
        $teamId = (int) $t['team_id'];
        $stmt = $conn->prepare("SELECT winner_team FROM matches WHERE (team_one = ? OR team_two = ?) AND status = 'Completed'");
        $stmt->bind_param('ii', $teamId, $teamId);
        $stmt->execute();
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $played = count($matches);
        $wins = count(array_filter($matches, fn($m) => (int) $m['winner_team'] === $teamId));
        $draws = count(array_filter($matches, fn($m) => $m['winner_team'] === null));
        $losses = $played - $wins - $draws;
        $winPct = $played > 0 ? round(($wins / $played) * 100, 2) : null;

        $stmt = $conn->prepare('SELECT pa.performance_score, p.player_id FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id WHERE p.team_id = ?');
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $playerRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $validScores = array_filter(array_column($playerRows, 'performance_score'), fn($s) => $s !== null);
        $avgTeamPerf = !empty($validScores) ? array_sum($validScores) / count($validScores) : null;

        $stmt = $conn->prepare('SELECT AVG(rating) a FROM player_ratings pr JOIN players p ON pr.player_id = p.player_id WHERE p.team_id = ?');
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $avgRating = $stmt->get_result()->fetch_assoc()['a'];
        $stmt->close();

        $topPlayerId = null;
        if (!empty($validScores)) {
            $best = null;
            foreach ($playerRows as $pr) {
                if ($pr['performance_score'] !== null && ($best === null || $pr['performance_score'] > $best['performance_score'])) $best = $pr;
            }
            $topPlayerId = $best ? (int) $best['player_id'] : null;
        }

        $teamStats[$teamId] = compact('played', 'wins', 'losses', 'draws', 'winPct', 'avgTeamPerf', 'avgRating', 'topPlayerId');
    }

    uasort($teamStats, fn($a, $b) => ($b['avgTeamPerf'] ?? -1) <=> ($a['avgTeamPerf'] ?? -1));
    $rank = 1;
    foreach ($teamStats as $teamId => $s) {
        $stmt = $conn->prepare(
            'INSERT INTO team_performance (team_id, matches_played, wins, losses, draws, win_percentage, avg_player_rating, avg_team_performance, team_rank, top_player_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE matches_played=VALUES(matches_played), wins=VALUES(wins), losses=VALUES(losses),
              draws=VALUES(draws), win_percentage=VALUES(win_percentage), avg_player_rating=VALUES(avg_player_rating),
              avg_team_performance=VALUES(avg_team_performance), team_rank=VALUES(team_rank), top_player_id=VALUES(top_player_id)'
        );
        $stmt->bind_param(
            'iiiiidddii',
            $teamId, $s['played'], $s['wins'], $s['losses'], $s['draws'], $s['winPct'], $s['avgRating'], $s['avgTeamPerf'], $rank, $s['topPlayerId']
        );
        $stmt->execute();
        $stmt->close();
        $rank++;
    }
}

function recalculateCoachPerformance(mysqli $conn): void {
    $coaches = $conn->query('SELECT coach_id FROM coaches')->fetch_all(MYSQLI_ASSOC);
    foreach ($coaches as $c) {
        $coachId = (int) $c['coach_id'];

        $stmt = $conn->prepare('SELECT pa.performance_score, pa.improvement_component FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id WHERE p.coach_id = ?');
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $playerRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $playersManaged = $conn->query('SELECT COUNT(*) c FROM players WHERE coach_id = ' . $coachId)->fetch_assoc()['c'];

        $scores = array_filter(array_column($playerRows, 'performance_score'), fn($s) => $s !== null);
        $avgPlayerPerf = !empty($scores) ? array_sum($scores) / count($scores) : null;
        $improvements = array_filter(array_column($playerRows, 'improvement_component'), fn($s) => $s !== null);
        $avgImprovement = !empty($improvements) ? array_sum($improvements) / count($improvements) : null;

        $stmt = $conn->prepare('SELECT AVG(win_percentage) a FROM team_performance tp JOIN teams t ON tp.team_id = t.team_id WHERE t.coach_id = ?');
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $teamWinPct = $stmt->get_result()->fetch_assoc()['a'];
        $stmt->close();

        $stmt = $conn->prepare('SELECT AVG(rating) a FROM player_ratings WHERE coach_id = ?');
        $stmt->bind_param('i', $coachId);
        $stmt->execute();
        $avgRatingGiven = $stmt->get_result()->fetch_assoc()['a'];
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT COUNT(DISTINCT m.match_id) c FROM matches m
             LEFT JOIN teams t1 ON m.team_one = t1.team_id LEFT JOIN teams t2 ON m.team_two = t2.team_id
             WHERE (t1.coach_id = ? OR t2.coach_id = ? OR m.coach_id = ?) AND m.status = 'Completed'"
        );
        $stmt->bind_param('iii', $coachId, $coachId, $coachId);
        $stmt->execute();
        $completedMatches = $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        $stmt = $conn->prepare(
            'INSERT INTO coach_performance (coach_id, players_managed, avg_player_performance, player_improvement, team_win_percentage, avg_coach_rating, completed_matches)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE players_managed=VALUES(players_managed), avg_player_performance=VALUES(avg_player_performance),
              player_improvement=VALUES(player_improvement), team_win_percentage=VALUES(team_win_percentage),
              avg_coach_rating=VALUES(avg_coach_rating), completed_matches=VALUES(completed_matches)'
        );
        $stmt->bind_param('iiddddi', $coachId, $playersManaged, $avgPlayerPerf, $avgImprovement, $teamWinPct, $avgRatingGiven, $completedMatches);
        $stmt->execute();
        $stmt->close();
    }
}
