<?php
/**
 * ============================================================
 * config/performance_weights.php
 * ------------------------------------------------------------
 * Every number that affects how a performance score is
 * calculated lives here. includes/performance_engine.php reads
 * this file and nowhere else — change a weight here and every
 * page/report picks it up automatically.
 *
 * The five top-level weights must sum to 1.0 (100%). If a
 * component is unavailable for a player (e.g. no coach rating
 * yet), the engine redistributes that weight proportionally
 * across the remaining available components rather than just
 * treating the missing one as a zero — see
 * calculatePlayerPerformance() in performance_engine.php.
 * ============================================================
 */

return [

    // ---------------------------------------------------------
    // Top-level component weights (must sum to 1.0)
    // ---------------------------------------------------------
    'coach_rating_weight'      => 0.25,
    'match_performance_weight' => 0.35,
    'sport_statistics_weight'  => 0.25,
    'consistency_weight'       => 0.10,
    'improvement_weight'       => 0.05,

    // ---------------------------------------------------------
    // Star rating thresholds (0-100 score -> 0-5 stars)
    // Checked top-down; first match wins.
    // ---------------------------------------------------------
    'star_thresholds' => [
        ['min' => 90, 'stars' => 5.0],
        ['min' => 80, 'stars' => 4.5],
        ['min' => 70, 'stars' => 4.0],
        ['min' => 60, 'stars' => 3.5],
        ['min' => 50, 'stars' => 3.0],
        ['min' => 40, 'stars' => 2.5],
        ['min' => 30, 'stars' => 2.0],
        ['min' => 20, 'stars' => 1.5],
        ['min' => 10, 'stars' => 1.0],
        ['min' => 0,  'stars' => 0.5],
    ],

    // ---------------------------------------------------------
    // Performance level classification (0-100 score)
    // ---------------------------------------------------------
    'level_thresholds' => [
        ['min' => 85, 'level' => 'Excellent'],
        ['min' => 70, 'level' => 'Very Good'],
        ['min' => 55, 'level' => 'Good'],
        ['min' => 40, 'level' => 'Average'],
        ['min' => 0,  'level' => 'Needs Improvement'],
    ],

    // ---------------------------------------------------------
    // Minimum completed matches before a player gets a full
    // score instead of "Insufficient Data."
    // ---------------------------------------------------------
    'minimum_matches_for_ranking' => 1,
    'limited_data_match_threshold' => 3, // below this: "ranking may change as more matches are completed"

    // ---------------------------------------------------------
    // Match performance component sub-weights (win/loss/draw
    // contribute to the 0-100 match_performance_weight bucket,
    // blended with the match's own sport-stat score if present)
    // ---------------------------------------------------------
    'match_result_points' => [
        'win'  => 100,
        'draw' => 55,
        'loss' => 25,
    ],

    // ---------------------------------------------------------
    // Per-sport statistic weights. Each sub-weight set must sum
    // to 1.0. Only stats with actual data contribute — see
    // calculateSportScore()'s "use available metrics only" rule.
    // ---------------------------------------------------------
    'sport_weights' => [
        'Cricket' => [
            'batting'  => 0.4,   // runs, strike rate
            'bowling'  => 0.35,  // wickets, economy
            'fielding' => 0.25,  // catches, run outs
        ],
        'Football' => [
            'goal_contribution'      => 0.5,  // goals + assists
            'defensive_contribution' => 0.3,  // tackles, saves, clean sheets
            'discipline'             => 0.2,  // inverse of cards
        ],
        'Hockey' => [
            'goal_contribution'      => 0.5,  // goals + assists
            'defensive_contribution' => 0.3,  // tackles, saves, clean sheets
            'discipline'             => 0.2,  // inverse of cards
        ],
    ],
];
