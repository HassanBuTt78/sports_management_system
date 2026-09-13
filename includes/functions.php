<?php
/**
 * ============================================================
 * functions.php
 * ------------------------------------------------------------
 * General-purpose helper functions shared by every auth file.
 * No session or database logic lives here directly (see
 * session.php / auth.php) — this file is pure utilities so it
 * can be required anywhere without side effects.
 * ============================================================
 */

/**
 * Central role -> table map. Every auth file (login pages,
 * auth.php, middleware.php) pulls from this single source of
 * truth instead of hardcoding table/column names, so adding a
 * 5th role later only means editing this one array.
 */
function roleConfig(?string $role = null) {
    $map = [
        'admin' => [
            'table'      => 'admins',
            'id_column'  => 'admin_id',
            'label'      => 'Administrator',
            'dashboard'  => BASE_URL . '/admin/dashboard.php',
            'login_page' => BASE_URL . '/login/admin_login.php',
        ],
        'coach' => [
            'table'      => 'coaches',
            'id_column'  => 'coach_id',
            'label'      => 'Coach',
            'dashboard'  => BASE_URL . '/coach/dashboard.php',
            'login_page' => BASE_URL . '/login/coach_login.php',
        ],
        'player' => [
            'table'      => 'players',
            'id_column'  => 'player_id',
            'label'      => 'Player',
            'dashboard'  => BASE_URL . '/player/dashboard.php',
            'login_page' => BASE_URL . '/login/player_login.php',
        ],
    ];

    if ($role === null) {
        return $map;
    }
    return $map[$role] ?? null;
}

/** Trim + strip tags; the first line of defense against XSS on any input. */
function cleanInput(string $value): string {
    return trim(strip_tags($value));
}

/** Escape output for safe HTML display (defense-in-depth against stored/reflected XSS). */
function safeOut(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Basic but strict email format check. */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Minimum password policy for new/reset passwords. */
function isStrongEnough(string $password): bool {
    return strlen($password) >= 8;
}

/** Best-effort real client IP (falls back through common proxy headers). */
function getClientIp(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            return trim($ip);
        }
    }
    return 'UNKNOWN';
}

/** Very small user-agent parser — good enough for an activity log line, not a full library. */
function getBrowserName(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') return 'Unknown Browser';

    $browsers = [
        'Edg'     => 'Microsoft Edge',
        'OPR'     => 'Opera',
        'Chrome'  => 'Google Chrome',
        'CriOS'   => 'Chrome (iOS)',
        'Firefox' => 'Mozilla Firefox',
        'Safari'  => 'Safari',
    ];
    foreach ($browsers as $needle => $name) {
        if (stripos($ua, $needle) !== false) return $name;
    }
    return 'Unknown Browser';
}

/**
 * Insert one row into activity_logs (Module 2 table — untouched
 * schema). Used for both successful logins and other tracked
 * actions. `$activity` carries the human-readable detail (e.g.
 * browser name) since activity_logs has no dedicated browser column.
 */
function logActivity(mysqli $conn, string $role, int $userId, string $activity): void {
    $stmt = $conn->prepare(
        'INSERT INTO activity_logs (user_role, user_id, activity, ip_address) VALUES (?, ?, ?, ?)'
    );
    $ip = getClientIp();
    $stmt->bind_param('siss', $role, $userId, $activity, $ip);
    $stmt->execute();
    $stmt->close();
}

/** Generate a random 6-digit numeric OTP as a zero-padded string. */
function generateOtp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/** Cryptographically random token string (hex), used for remember-me selector/validator. */
function randomToken(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

/** Redirect helper — always calls exit so nothing executes after it by accident. */
function redirectTo(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * ============================================================
 * MODULE 4 ADDITIONS (below this line)
 * ------------------------------------------------------------
 * Both functions below are new, read-only helpers added for the
 * Admin Dashboard. Nothing above this line was changed — auth
 * and session logic from Module 3 is untouched.
 * ============================================================
 */

/**
 * Resolves a display name for a polymorphic (role, id) pair —
 * e.g. rows from activity_logs or messages, where the actual
 * name lives in one of four different tables depending on role.
 * Small per-row lookup; fine for the handful of rows a "Recent
 * Activity" widget shows.
 */
function resolveUserName(mysqli $conn, string $role, int $id): string {
    $cfg = roleConfig($role);
    if (!$cfg) return 'Unknown User';

    $stmt = $conn->prepare("SELECT full_name FROM {$cfg['table']} WHERE {$cfg['id_column']} = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['full_name'] ?? 'Unknown User';
}

/** Human-friendly "3 hours ago" style relative time for activity/message feeds. */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff / 60) . ' min ago';
    if ($diff < 86400)   return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800)  return floor($diff / 86400) . ' day(s) ago';
    return date('M j, Y', strtotime($datetime));
}

/**
 * ============================================================
 * MODULE 5 ADDITIONS (below this line)
 * ------------------------------------------------------------
 * Generic, reusable helpers added for Player Management. Written
 * to be reused as-is by Coach/Team Management later — nothing
 * player-specific lives in these functions.
 * ============================================================
 */

/** Generates a random, readable secure password (upper+lower+digit+symbol mix). */
function generateSecurePassword(int $length = 12): string {
    $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // no I/O to avoid look-alikes
    $lower  = 'abcdefghijkmnopqrstuvwxyz';   // no l
    $digits = '23456789';                    // no 0/1
    $symbols = '!@#$%&*';
    $all = $upper . $lower . $digits . $symbols;

    // Guarantee at least one of each character class.
    $password = $upper[random_int(0, strlen($upper) - 1)]
        . $lower[random_int(0, strlen($lower) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $symbols[random_int(0, strlen($symbols) - 1)];

    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($password);
}

/**
 * ============================================================
 * ADMIN-RECOVERABLE CREDENTIAL ENCRYPTION (AES-256-GCM)
 * ------------------------------------------------------------
 * The `password` column (bcrypt, via password_hash/password_verify)
 * is what actually authenticates logins and can NEVER be reversed
 * — that's the whole point of bcrypt. Coaches/players also get an
 * `encrypted_password` column: a SEPARATE, symmetrically-encrypted
 * copy of the same plaintext, which only encryptCredential() /
 * decryptCredential() below ever touch, using a key that lives in
 * includes/env.php (outside the database entirely). This is what
 * lets Admin's "Show Password" action work without ever storing
 * anything in plain text in MySQL.
 *
 * Every caller that sets a coach/player password should write BOTH
 * columns together from the same plaintext, in the same request —
 * see admin/coach/create.php, admin/player/create.php,
 * admin/coach/reset_password.php, admin/player/reset_password.php,
 * coach/profile.php, and player/profile.php.
 * ============================================================
 */

/** Encrypts $plaintext for admin-recoverable storage. Returns a base64 string safe for a TEXT column. */
function encryptCredential(string $plaintext): string {
    $key = base64_decode(CREDENTIAL_ENC_KEY);
    $iv = random_bytes(12); // 96-bit GCM nonce, unique per encryption
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Credential encryption failed.');
    }
    // iv (12 bytes) + auth tag (16 bytes) + ciphertext, then base64 for safe TEXT storage.
    return base64_encode($iv . $tag . $ciphertext);
}

/** Decrypts a value produced by encryptCredential(). Returns null (never throws) if it can't be decrypted. */
function decryptCredential(?string $stored): ?string {
    if (!$stored) return null;
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 29) return null; // 12 iv + 16 tag + >=1 byte ciphertext

    $iv         = substr($raw, 0, 12);
    $tag        = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $key        = base64_decode(CREDENTIAL_ENC_KEY);

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plaintext === false ? null : $plaintext;
}

/**
 * Checks whether $value is unique in $table.$column, optionally
 * excluding one row (for edit forms checking "unique except mine").
 * Used at the application layer for columns without a DB UNIQUE
 * constraint (e.g. players.phone) as well as ones that do have one
 * (giving a friendly error instead of a raw DB duplicate-key crash).
 */
function isFieldUnique(mysqli $conn, string $table, string $column, string $value, ?int $excludeId = null, string $idColumn = 'id'): bool {
    if ($excludeId !== null) {
        $stmt = $conn->prepare("SELECT 1 FROM {$table} WHERE {$column} = ? AND {$idColumn} != ? LIMIT 1");
        $stmt->bind_param('si', $value, $excludeId);
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1");
        $stmt->bind_param('s', $value);
    }
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return !$exists;
}

/**
 * Validates an uploaded image ($_FILES['field']) against the
 * brief's rules: JPG/PNG/JPEG only, max 2MB, and a real image
 * (checked via getimagesize(), not just the file extension —
 * extension alone can't be trusted for upload security).
 * Returns ['valid' => bool, 'error' => ?string].
 */
function validateUploadedImage(array $file): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'error' => null]; // no file chosen = fine, it's optional
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['valid' => false, 'error' => 'Image must be smaller than 2MB.'];
    }
    $allowedExt = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['valid' => false, 'error' => 'Only JPG, JPEG, and PNG images are allowed.'];
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || !in_array($imageInfo['mime'], ['image/jpeg', 'image/png'], true)) {
        return ['valid' => false, 'error' => 'The uploaded file is not a valid image.'];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Moves an already-validated upload into uploads/{$subfolder}/ with
 * a collision-proof generated filename, and returns the RELATIVE
 * path to store in the database (e.g. "players/abc123.jpg") — this
 * matches how currentProfileImage() and the dashboard already build
 * image URLs as UPLOADS_URL . '/' . $storedPath.
 */
function storeUploadedImage(array $file, string $subfolder): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'error' => null];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $subfolder . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/' . $subfolder . '/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
        return ['success' => false, 'path' => null, 'error' => 'Could not save the uploaded image.'];
    }
    return ['success' => true, 'path' => $subfolder . '/' . $filename, 'error' => null];
}

/** Deletes a previously-uploaded file (e.g. when replacing a profile image), if it exists. */
function deleteUploadedFile(?string $relativePath): void {
    if (!$relativePath) return;
    $fullPath = __DIR__ . '/../uploads/' . ltrim($relativePath, '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

/**
 * ============================================================
 * MODULE 6 ADDITIONS (below this line)
 * ------------------------------------------------------------
 */

/**
 * Formats a human-friendly "Employee ID" (e.g. "COA-0001") from a
 * table's real auto-increment primary key, rather than storing a
 * second ID column. This is purely a display format — no schema
 * change needed, and it can never drift out of sync with the
 * real ID the database already uses everywhere else.
 */
function formatEmployeeId(string $prefix, int $id, int $digits = 4): string {
    return strtoupper($prefix) . '-' . str_pad((string) $id, $digits, '0', STR_PAD_LEFT);
}

/**
 * ============================================================
 * MODULE 7 ADDITIONS (below this line)
 * ------------------------------------------------------------
 */

/**
 * Computes a team's live statistics (player count, matches played,
 * wins/losses/draws, average rating/score) with a handful of
 * read-only queries. Centralized here so admin/coach/player team
 * pages and all three dashboards compute these identically instead
 * of re-deriving slightly different logic in each file.
 */
function getTeamStats(mysqli $conn, int $teamId): array {
    $stats = [
        'player_count' => 0, 'matches_played' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0,
        'avg_rating' => 0, 'avg_score' => 0,
    ];

    $stmt = $conn->prepare('SELECT COUNT(*) c FROM players WHERE team_id = ?');
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $stats['player_count'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM matches WHERE status = 'Completed' AND (team_one = ? OR team_two = ?)"
    );
    $stmt->bind_param('ii', $teamId, $teamId);
    $stmt->execute();
    $stats['matches_played'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM matches WHERE status = 'Completed' AND winner_team = ?");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $stats['wins'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM matches
         WHERE status = 'Completed' AND winner_team IS NOT NULL AND winner_team != ?
         AND (team_one = ? OR team_two = ?)"
    );
    $stmt->bind_param('iii', $teamId, $teamId, $teamId);
    $stmt->execute();
    $stats['losses'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM matches
         WHERE status = 'Completed' AND winner_team IS NULL AND team_two IS NOT NULL
         AND (team_one = ? OR team_two = ?)"
    );
    $stmt->bind_param('ii', $teamId, $teamId);
    $stmt->execute();
    $stats['draws'] = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT AVG(pa.average_rating) avg_r, AVG(pa.total_score) avg_s
         FROM performance_analysis pa JOIN players p ON pa.player_id = p.player_id
         WHERE p.team_id = ?'
    );
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $stats['avg_rating'] = $row['avg_r'] ? round((float) $row['avg_r'], 2) : 0;
    $stats['avg_score']  = $row['avg_s'] ? round((float) $row['avg_s'], 2) : 0;

    return $stats;
}

/**
 * ============================================================
 * MODULE 8 ADDITIONS (below this line)
 * ------------------------------------------------------------
 */

// ---------------- CSRF protection ----------------
// New for Module 8's forms only, per the brief. Not retrofitted
// onto Modules 5-7's forms — "do not modify existing modules."

/** Returns the current CSRF token, generating one into the session if needed. */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Renders a ready-to-use hidden CSRF field for a <form>. */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . safeOut(csrfToken()) . '">';
}

/** Verifies a submitted token against the session's, using a timing-safe comparison. */
function verifyCsrfToken(?string $submitted): bool {
    return !empty($submitted) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted);
}

// ---------------- Notifications ----------------

/**
 * Inserts a notification row. $receiverId = null broadcasts to every
 * user of $receiverRole (matches the `notifications` table's own
 * documented convention from Module 2).
 */
function notifyUser(mysqli $conn, string $receiverRole, ?int $receiverId, string $title, string $message): void {
    $stmt = $conn->prepare(
        'INSERT INTO notifications (title, message, receiver_role, receiver_id) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('sssi', $title, $message, $receiverRole, $receiverId);
    // receiver_id may legitimately be NULL (broadcast) — mysqli sends
    // SQL NULL correctly regardless of the declared type character.
    $stmt->execute();
    $stmt->close();
}

// ---------------- Event helpers ----------------

/** Current registered (non-rejected) participant count for an event. */
function getEventParticipantCount(mysqli $conn, int $eventId): int {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM event_participants WHERE event_id = ? AND participation_status != 'Rejected'"
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count;
}

/**
 * Checks every rule from the brief's "Player clicks Join Event" flow
 * in one place, so join.php doesn't duplicate this logic. Returns
 * ['eligible' => bool, 'reason' => ?string].
 */
function checkJoinEligibility(mysqli $conn, array $event, int $playerId, int $playerSportId): array {
    if ($event['sport_id'] != $playerSportId) {
        return ['eligible' => false, 'reason' => 'This event is not for your sport.'];
    }
    if (!in_array($event['status'], ['Upcoming', 'Registration Open'], true)) {
        return ['eligible' => false, 'reason' => 'Registration is not open for this event.'];
    }
    if ($event['registration_deadline'] && strtotime($event['registration_deadline']) < time()) {
        return ['eligible' => false, 'reason' => 'The registration deadline has passed.'];
    }
    if ($event['max_participants']) {
        $current = getEventParticipantCount($conn, (int) $event['event_id']);
        if ($current >= (int) $event['max_participants']) {
            return ['eligible' => false, 'reason' => 'This event is full.'];
        }
    }
    $stmt = $conn->prepare('SELECT 1 FROM event_participants WHERE event_id = ? AND player_id = ? LIMIT 1');
    $stmt->bind_param('ii', $event['event_id'], $playerId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        $stmt->close();
        return ['eligible' => false, 'reason' => 'You are already registered for this event.'];
    }
    $stmt->close();

    return ['eligible' => true, 'reason' => null];
}

/**
 * ============================================================
 * MODULE 9 ADDITIONS (below this line)
 * ------------------------------------------------------------
 * Generic (non-image) file upload helpers — match media (video/
 * scoresheet) needs broader type support than
 * validateUploadedImage()/storeUploadedImage() (Module 5) were
 * written for.
 * ============================================================
 */

/** Validates any uploaded file against an extension whitelist + max size. Returns ['valid'=>bool,'error'=>?string]. */
function validateUploadedFile(array $file, array $allowedExt, int $maxBytes): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'error' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['valid' => false, 'error' => 'File must be smaller than ' . round($maxBytes / 1024 / 1024, 1) . 'MB.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['valid' => false, 'error' => 'That file type is not allowed. Allowed: ' . implode(', ', $allowedExt) . '.'];
    }
    return ['valid' => true, 'error' => null];
}

/** Moves an already-validated arbitrary file into uploads/{$subfolder}/ with a collision-proof name. */
function storeUploadedFile(array $file, string $subfolder): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'error' => null];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $subfolder . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/' . $subfolder . '/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
        return ['success' => false, 'path' => null, 'error' => 'Could not save the uploaded file.'];
    }
    return ['success' => true, 'path' => $subfolder . '/' . $filename, 'error' => null];
}

/**
 * ============================================================
 * MODULE 10 ADDITIONS (below this line)
 * ------------------------------------------------------------
 */

/**
 * Who (role,id) is allowed to privately message, per Module 10's
 * rules:
 *   admin  -> everyone (all coaches + all players)
 *   coach  -> admin + own sport's players + other coaches
 *   player -> admin + own coach + own team's teammates
 */
function getChatContacts(mysqli $conn, string $role, int $id): array {
    $contacts = [];
    $add = function (string $table, string $idCol, string $roleLabel, string $where = '', array $params = [], string $types = '') use ($conn, &$contacts) {
        $sql = "SELECT {$idCol} AS id, full_name, profile_image FROM {$table}" . ($where ? " WHERE {$where}" : '');
        $stmt = $conn->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $contacts[] = ['role' => $roleLabel, 'id' => (int) $row['id'], 'name' => $row['full_name'], 'image' => $row['profile_image']];
        }
        $stmt->close();
    };

    if ($role === 'admin') {
        $add('admins', 'admin_id', 'admin', 'admin_id != ?', [$id], 'i');
        $add('coaches', 'coach_id', 'coach');
        $add('players', 'player_id', 'player');
    } elseif ($role === 'coach') {
        $add('admins', 'admin_id', 'admin');
        $add('coaches', 'coach_id', 'coach', 'coach_id != ?', [$id], 'i');
        $stmt = $conn->prepare('SELECT sport_id FROM coaches WHERE coach_id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sportId = $stmt->get_result()->fetch_assoc()['sport_id'] ?? null;
        $stmt->close();
        if ($sportId) $add('players', 'player_id', 'player', 'sport_id = ?', [$sportId], 'i');
    } elseif ($role === 'player') {
        $add('admins', 'admin_id', 'admin');
        $stmt = $conn->prepare('SELECT coach_id, team_id FROM players WHERE player_id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $me = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($me['coach_id'])) $add('coaches', 'coach_id', 'coach', 'coach_id = ?', [(int) $me['coach_id']], 'i');
        if (!empty($me['team_id'])) $add('players', 'player_id', 'player', 'team_id = ? AND player_id != ?', [(int) $me['team_id'], $id], 'ii');
    }

    return $contacts;
}

/** True if (role,id) is allowed to privately message (targetRole,targetId), per getChatContacts()'s rules. */
function isAllowedChatContact(mysqli $conn, string $role, int $id, string $targetRole, int $targetId): bool {
    foreach (getChatContacts($conn, $role, $id) as $c) {
        if ($c['role'] === $targetRole && $c['id'] === $targetId) return true;
    }
    return false;
}

/** True if (role,id) belongs to $conversationId (any type). */
function isConversationMember(mysqli $conn, int $conversationId, string $role, int $id): bool {
    $stmt = $conn->prepare('SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_role = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('isi', $conversationId, $role, $id);
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $found;
}

/** Finds an existing private conversation between two users, or creates one. Returns conversation_id. */
function getOrCreatePrivateConversation(mysqli $conn, string $roleA, int $idA, string $roleB, int $idB): int {
    $stmt = $conn->prepare(
        "SELECT cm1.conversation_id FROM conversation_members cm1
         JOIN conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id
         JOIN conversations c ON c.conversation_id = cm1.conversation_id
         WHERE c.conversation_type = 'private'
           AND cm1.user_role = ? AND cm1.user_id = ? AND cm2.user_role = ? AND cm2.user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('sisi', $roleA, $idA, $roleB, $idB);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) return (int) $existing['conversation_id'];

    $stmt = $conn->prepare("INSERT INTO conversations (conversation_type, created_by_role, created_by_id) VALUES ('private', ?, ?)");
    $stmt->bind_param('si', $roleA, $idA);
    $stmt->execute();
    $conversationId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO conversation_members (conversation_id, user_role, user_id) VALUES (?,?,?),(?,?,?)');
    $stmt->bind_param('isiisi', $conversationId, $roleA, $idA, $conversationId, $roleB, $idB);
    $stmt->execute();
    $stmt->close();

    return $conversationId;
}

/**
 * Finds (or lazily creates) the discussion conversation tied to an
 * event, auto-syncing membership to: the event's coach + admins who
 * created it + every APPROVED participant — matches the brief's
 * "only organizer, participating coaches, approved players."
 */
function getOrCreateEventConversation(mysqli $conn, int $eventId): int {
    $stmt = $conn->prepare("SELECT conversation_id FROM conversations WHERE conversation_type = 'event' AND event_id = ? LIMIT 1");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $conversationId = (int) $existing['conversation_id'];
    } else {
        $stmt = $conn->prepare('SELECT title, coach_id, created_by_role, created_by_id FROM events WHERE event_id = ? LIMIT 1');
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$event) return 0;

        $title = $event['title'] . ' — Discussion';
        $creatorRole = $event['created_by_role'] ?: 'admin';
        $creatorId = (int) ($event['created_by_id'] ?: 1);
        $stmt = $conn->prepare("INSERT INTO conversations (conversation_type, title, event_id, created_by_role, created_by_id) VALUES ('event', ?, ?, ?, ?)");
        $stmt->bind_param('sisi', $title, $eventId, $creatorRole, $creatorId);
        $stmt->execute();
        $conversationId = $stmt->insert_id;
        $stmt->close();
        if ($event['coach_id']) {
            addConversationMemberIfMissing($conn, $conversationId, 'coach', (int) $event['coach_id']);
        }
    }

    // Sync approved participants every time (keeps membership current as approvals come in).
    $stmt = $conn->prepare("SELECT player_id FROM event_participants WHERE event_id = ? AND participation_status = 'Approved'");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        addConversationMemberIfMissing($conn, $conversationId, 'player', (int) $row['player_id']);
    }
    $stmt->close();

    return $conversationId;
}

/** Same idea as getOrCreateEventConversation(), for matches — members: both teams' coaches + assigned coach + both teams' players. */
function getOrCreateMatchConversation(mysqli $conn, int $matchId): int {
    $stmt = $conn->prepare("SELECT conversation_id FROM conversations WHERE conversation_type = 'match' AND match_id = ? LIMIT 1");
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $conversationId = (int) $existing['conversation_id'];
    } else {
        $stmt = $conn->prepare('SELECT match_title, coach_id, team_one, team_two, created_by_role, created_by_id FROM matches WHERE match_id = ? LIMIT 1');
        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $match = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$match) return 0;

        $title = ($match['match_title'] ?: 'Match #' . $matchId) . ' — Discussion';
        $creatorRole = $match['created_by_role'] ?: 'admin';
        $creatorId = (int) ($match['created_by_id'] ?: 1);
        $stmt = $conn->prepare("INSERT INTO conversations (conversation_type, title, match_id, created_by_role, created_by_id) VALUES ('match', ?, ?, ?, ?)");
        $stmt->bind_param('sisi', $title, $matchId, $creatorRole, $creatorId);
        $stmt->execute();
        $conversationId = $stmt->insert_id;
        $stmt->close();

        if ($match['coach_id']) addConversationMemberIfMissing($conn, $conversationId, 'coach', (int) $match['coach_id']);
        foreach (array_filter([$match['team_one'], $match['team_two']]) as $tid) {
            $stmt2 = $conn->prepare('SELECT coach_id FROM teams WHERE team_id = ? LIMIT 1');
            $stmt2->bind_param('i', $tid);
            $stmt2->execute();
            $cid = $stmt2->get_result()->fetch_assoc()['coach_id'] ?? null;
            $stmt2->close();
            if ($cid) addConversationMemberIfMissing($conn, $conversationId, 'coach', (int) $cid);

            $stmt2 = $conn->prepare('SELECT player_id FROM players WHERE team_id = ?');
            $stmt2->bind_param('i', $tid);
            $stmt2->execute();
            foreach ($stmt2->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                addConversationMemberIfMissing($conn, $conversationId, 'player', (int) $row['player_id']);
            }
            $stmt2->close();
        }
    }

    return $conversationId;
}

/** Inserts a conversation_members row only if that (role,id) isn't already in it. */
function addConversationMemberIfMissing(mysqli $conn, int $conversationId, string $role, int $id, bool $isAdmin = false): void {
    $stmt = $conn->prepare('INSERT IGNORE INTO conversation_members (conversation_id, user_role, user_id, is_admin) VALUES (?,?,?,?)');
    $adminFlag = $isAdmin ? 1 : 0;
    $stmt->bind_param('isii', $conversationId, $role, $id, $adminFlag);
    $stmt->execute();
    $stmt->close();
}

/** Simple rate limit: blocks a sender who has posted more than $max messages in the last $seconds. */
function isSenderRateLimited(mysqli $conn, string $role, int $id, int $max = 10, int $seconds = 10): bool {
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM messages WHERE sender_role = ? AND sender_id = ? AND sent_at >= (NOW() - INTERVAL ? SECOND)');
    $stmt->bind_param('sii', $role, $id, $seconds);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count >= $max;
}

/** Updates (role,id)'s last-seen timestamp — call on every chat-related AJAX ping. */
function touchPresence(mysqli $conn, string $role, int $id): void {
    $stmt = $conn->prepare('INSERT INTO user_presence (user_role, user_id, last_seen) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()');
    $stmt->bind_param('si', $role, $id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Maps a role to its real table name and ID column. Never
 * pluralize a role naively with `. 's'` — 'coach' needs
 * 'coaches', not 'coachs'. Always use this instead.
 */
function roleTable(string $role): array {
    $map = [
        'admin'     => ['table' => 'admins', 'id_col' => 'admin_id'],
        'coach'     => ['table' => 'coaches', 'id_col' => 'coach_id'],
        'player'    => ['table' => 'players', 'id_col' => 'player_id'],
    ];
    return $map[$role] ?? ['table' => null, 'id_col' => null];
}

/** True if (role,id) was seen within the last 60 seconds. */
function isUserOnline(mysqli $conn, string $role, int $id): bool {
    $stmt = $conn->prepare('SELECT last_seen FROM user_presence WHERE user_role = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('si', $role, $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && (time() - strtotime($row['last_seen'])) <= 60;
}

/**
 * ============================================================
 * SECURITY QUESTION PASSWORD RECOVERY
 * ------------------------------------------------------------
 * Replaces the Module 3 OTP-simulation flow. Registration forms
 * also now let admin/coach set a password directly instead of
 * an auto-generated one shown on screen.
 * ============================================================
 */

/** Preset security questions offered on every registration form and the forgot-password flow. */
function securityQuestionOptions(): array {
    return [
        "What is your favorite sport?",
        "What was the name of your first coach?",
        "What city were you born in?",
        "What is your mother's maiden name?",
        "What was the name of your first school?",
        "What is your favorite team?",
    ];
}

/** Hashes a security answer, normalized (lowercased + trimmed) so answer comparison isn't case/whitespace sensitive. */
function hashSecurityAnswer(string $answer): string {
    return password_hash(mb_strtolower(trim($answer)), PASSWORD_DEFAULT);
}

/** Verifies a submitted answer against the stored hash, using the same normalization as hashSecurityAnswer(). */
function verifySecurityAnswer(string $answer, ?string $hash): bool {
    if (!$hash) return false;
    return password_verify(mb_strtolower(trim($answer)), $hash);
}

/** Password strength check shared by every registration/reset/change-password form. Returns an error string, or null if valid. */
function validatePasswordStrength(string $password): ?string {
    if (strlen($password) < 8) return 'Password must be at least 8 characters long.';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one digit.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return 'Password must contain at least one special character.';
    return null;
}

/**
 * ============================================================
 * COACH "RATE PLAYER" WORKFLOW
 * ------------------------------------------------------------
 * Reuses player_scores.custom_score (the "Score") and
 * player_ratings.rating/review (the "Rating"/"Comment") — no new
 * tables or columns. This classifier uses the simple 1-5 star
 * scale specified for that workflow, distinct from the 0-100
 * scale used by includes/performance_engine.php's weighted
 * system — the two are independent views over the same
 * underlying data, not two competing scoring systems.
 */
function classifyStarPerformanceLevel(?float $avgRating): string {
    if ($avgRating === null) return 'Insufficient Data';
    if ($avgRating >= 4.5) return 'Excellent';
    if ($avgRating >= 3.5) return 'Very Good';
    if ($avgRating >= 2.5) return 'Good';
    if ($avgRating >= 1.5) return 'Average';
    return 'Needs Improvement';
}
